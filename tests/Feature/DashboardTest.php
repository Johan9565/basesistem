<?php

namespace Tests\Feature;

use App\Models\ExamModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_lists_only_exams_the_user_can_access(): void
    {
        $publicExam = ExamModel::factory()->create([
            'name' => 'Constitucional',
            'slug' => 'constitucional',
            'is_public' => true,
            'status' => 1,
            'order_index' => 1,
        ]);

        $assignedExam = ExamModel::factory()->private()->create([
            'name' => 'Amparo',
            'slug' => 'amparo',
            'status' => 1,
            'order_index' => 2,
        ]);

        ExamModel::factory()->private()->create([
            'name' => 'Examen ajeno',
            'slug' => 'examen-ajeno',
            'status' => 1,
            'order_index' => 3,
        ]);

        ExamModel::factory()->inactive()->create([
            'name' => 'Inactivo',
            'slug' => 'inactivo',
            'is_public' => true,
            'order_index' => 4,
        ]);

        $user = User::factory()->create([
            'exam_ids' => [(string) $assignedExam->getKey()],
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('exams', 2)
                ->where('exams.0.id', (string) $publicExam->getKey())
                ->where('exams.0.name', 'Constitucional')
                ->where('exams.1.id', (string) $assignedExam->getKey())
                ->where('exams.1.name', 'Amparo')
            );
    }

    public function test_user_can_open_an_accessible_exam(): void
    {
        $exam = ExamModel::factory()->create([
            'name' => 'Civil',
            'is_public' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/exams/'.$exam->getKey())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Exams/Show')
                ->where('exam.id', (string) $exam->getKey())
                ->where('exam.name', 'Civil')
            );
    }

    public function test_user_cannot_open_an_exam_without_access(): void
    {
        $exam = ExamModel::factory()->private()->create();
        $user = User::factory()->create(['exam_ids' => []]);

        $this->actingAs($user)
            ->get('/exams/'.$exam->getKey())
            ->assertForbidden();
    }
}
