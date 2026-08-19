<?php

namespace Tests\Feature;

use App\Models\ExamAttemptModel;
use App\Models\ExamModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_an_exam_and_questions_do_not_leak_answers(): void
    {
        $exam = ExamModel::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('exams.attempts.store', $exam->getKey()))
            ->assertRedirect();

        $attempt = ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->first();

        $this->assertNotNull($attempt);

        $this->actingAs($user)
            ->get(route('exams.attempts.show', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Exams/Take')
                ->has('questions', 2)
                ->has('questions.0', fn (Assert $question) => $question
                    ->has('id')
                    ->has('prompt')
                    ->has('options')
                    ->has('subject')
                    ->has('type')
                    ->missing('correct_option_id')
                    ->missing('correctas')
                    ->missing('explanation')
                    ->missing('respuesta_modelo')
                    ->missing('respuesta_correcta')
                )
            );
    }

    public function test_starting_twice_resumes_the_same_attempt(): void
    {
        $exam = ExamModel::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('exams.attempts.store', $exam->getKey()));
        $firstId = ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->first()?->getKey();

        $this->actingAs($user)->post(route('exams.attempts.store', $exam->getKey()));

        $this->assertSame(1, ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->count());
        $this->assertSame(
            (string) $firstId,
            (string) ExamAttemptModel::query()
                ->where('user_id', (string) $user->getKey())
                ->where('exam_id', (string) $exam->getKey())
                ->first()?->getKey()
        );
    }

    public function test_user_can_submit_and_see_the_score(): void
    {
        $exam = ExamModel::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('exams.attempts.store', $exam->getKey()));
        $attempt = ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->first();
        $questions = $exam->questionRecords()->orderBy('orden')->get();

        $this->actingAs($user)
            ->post(route('exams.attempts.submit', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]), [
                'answers' => [
                    (string) $questions[0]->getKey() => '0',
                    (string) $questions[1]->getKey() => '0',
                ],
            ])
            ->assertRedirect(route('exams.attempts.result', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]));

        $attempt->refresh();
        $this->assertSame(ExamAttemptModel::STATUS_SUBMITTED, $attempt->status);
        $this->assertSame(50, $attempt->score);
        $this->assertSame(1, $attempt->correct_count);

        $this->actingAs($user)
            ->get(route('exams.attempts.result', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Exams/Result')
                ->where('attempt.score', 50)
                ->where('attempt.questions.0.is_correct', true)
                ->where('attempt.questions.1.is_correct', false)
            );
    }

    public function test_user_cannot_open_someone_elses_attempt(): void
    {
        $exam = ExamModel::factory()->create();
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->post(route('exams.attempts.store', $exam->getKey()));
        $attempt = ExamAttemptModel::query()
            ->where('user_id', (string) $owner->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->first();

        $this->actingAs($other)
            ->get(route('exams.attempts.show', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]))
            ->assertForbidden();
    }

    public function test_user_without_access_cannot_start_an_exam(): void
    {
        $exam = ExamModel::factory()->private()->create();
        $user = User::factory()->create(['exam_ids' => []]);

        $this->actingAs($user)
            ->post(route('exams.attempts.store', $exam->getKey()))
            ->assertForbidden();
    }

    public function test_review_exam_check_tells_if_the_answer_is_right_without_explaining_to_free_users(): void
    {
        $exam = ExamModel::factory()->repaso()->create();
        $user = User::factory()->create();
        $question = $exam->questionRecords()->orderBy('orden')->first();
        $questionId = (string) $question->getKey();

        $this->actingAs($user)->post(route('exams.attempts.store', $exam->getKey()));
        $attempt = ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->first();

        $this->actingAs($user)
            ->from(route('exams.attempts.show', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]))
            ->post(route('exams.attempts.check', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]), [
                'question_id' => $questionId,
                'answers' => [$questionId => '0'],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('exams.attempts.show', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Exams/Take')
                ->where('exam.tipo', 'repaso')
                ->where("attempt.feedback.{$questionId}.is_correct", true)
                ->missing("attempt.feedback.{$questionId}.explanation")
                ->missing("attempt.feedback.{$questionId}.correct_option_id")
                ->missing("attempt.feedback.{$questionId}.respuesta_correcta")
            );
    }

    public function test_review_exam_explains_the_answer_to_premium_users(): void
    {
        $exam = ExamModel::factory()->repaso()->create();
        $user = User::factory()->premium()->create();
        $question = $exam->questionRecords()->orderBy('orden')->first();
        $questionId = (string) $question->getKey();

        $this->actingAs($user)->post(route('exams.attempts.store', $exam->getKey()));
        $attempt = ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->first();

        $this->actingAs($user)
            ->post(route('exams.attempts.check', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]), [
                'question_id' => $questionId,
                'answers' => [$questionId => '1'],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('exams.attempts.show', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Exams/Take')
                ->where("attempt.feedback.{$questionId}.is_correct", false)
                ->where(
                    "attempt.feedback.{$questionId}.explanation",
                    'La opción A es la correcta porque coincide con el enunciado.'
                )
                ->where("attempt.feedback.{$questionId}.respuesta_correcta", 'Correcta')
            );
    }

    public function test_review_exam_does_not_change_a_revealed_answer(): void
    {
        $exam = ExamModel::factory()->repaso()->create();
        $user = User::factory()->create();
        $question = $exam->questionRecords()->orderBy('orden')->first();
        $questionId = (string) $question->getKey();

        $this->actingAs($user)->post(route('exams.attempts.store', $exam->getKey()));
        $attempt = ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->first();

        $this->actingAs($user)->post(route('exams.attempts.check', [
            'exam' => $exam->getKey(),
            'attempt' => $attempt->getKey(),
        ]), [
            'question_id' => $questionId,
            'answers' => [$questionId => '0'],
        ]);

        $this->actingAs($user)->patch(route('exams.attempts.update', [
            'exam' => $exam->getKey(),
            'attempt' => $attempt->getKey(),
        ]), [
            'answers' => [$questionId => '1'],
        ]);

        $attempt->refresh();
        $answers = json_decode(json_encode($attempt->answers), true) ?: [];
        $feedback = json_decode(json_encode($attempt->feedback), true) ?: [];
        $this->assertSame('0', (string) ($answers[$questionId] ?? ''));
        $this->assertTrue((bool) ($feedback[$questionId]['is_correct'] ?? false));
    }

    public function test_normal_exam_cannot_use_the_review_check_endpoint(): void
    {
        $exam = ExamModel::factory()->create();
        $user = User::factory()->create();
        $question = $exam->questionRecords()->orderBy('orden')->first();

        $this->actingAs($user)->post(route('exams.attempts.store', $exam->getKey()));
        $attempt = ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->first();

        $this->actingAs($user)
            ->post(route('exams.attempts.check', [
                'exam' => $exam->getKey(),
                'attempt' => $attempt->getKey(),
            ]), [
                'question_id' => (string) $question->getKey(),
                'answers' => [(string) $question->getKey() => '0'],
            ])
            ->assertNotFound();
    }
}
