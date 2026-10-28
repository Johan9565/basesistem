<?php

namespace Tests\Unit;

use App\Models\CalendarEvent;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WorkspaceNote;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantEntityUpdateEndpointTest extends TestCase
{
    protected User $user;
    protected string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = 'test_user_endpoint_' . uniqid();
        $this->user = new User();
        $this->user->id = $this->userId;
        $this->user->name = 'Johan Endpoint';
        $this->user->email = "test_{$this->userId}@example.com";
    }

    protected function tearDown(): void
    {
        CalendarEvent::where('user_id', $this->userId)->delete();
        Reminder::where('user_id', $this->userId)->delete();
        WorkspaceNote::where('user_id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_update_endpoints_require_authentication(): void
    {
        $response = $this->patchJson('/api/v1/assistant/entities/reminder/fake_id', [
            'title' => 'No auth',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_update_reminder_via_generic_entities_endpoint(): void
    {
        Sanctum::actingAs($this->user);

        $reminder = Reminder::create([
            'user_id'      => $this->userId,
            'title'        => 'Recordatorio inicial',
            'scheduled_at' => Carbon::tomorrow('America/Cancun')->setHour(9),
            'status'       => 'pending',
            'priority'     => 'low',
        ]);

        $id = (string) ($reminder->_id ?? $reminder->id);

        $response = $this->patchJson("/api/v1/assistant/entities/reminder/{$id}", [
            'title'    => 'Recordatorio modificado',
            'priority' => 'high',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'       => $id,
                    'title'    => 'Recordatorio modificado',
                    'priority' => 'high',
                ],
            ]);

        $this->assertDatabaseHas('reminders', [
            'user_id' => $this->userId,
            'title'   => 'Recordatorio modificado',
        ]);
    }

    public function test_can_update_event_via_specific_event_endpoint(): void
    {
        Sanctum::actingAs($this->user);

        $start = Carbon::tomorrow('America/Cancun')->setHour(10);
        $event = CalendarEvent::create([
            'user_id'    => $this->userId,
            'title'      => 'Reunión sprint',
            'start_time' => $start,
            'end_time'   => (clone $start)->addHour(),
            'location'   => 'Meet',
            'status'     => 'scheduled',
        ]);

        $id = (string) ($event->_id ?? $event->id);

        $response = $this->patchJson("/api/v1/assistant/events/{$id}", [
            'title'    => 'Sprint Planning Q4',
            'location' => 'Sala 1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'       => $id,
                    'title'    => 'Sprint Planning Q4',
                    'location' => 'Sala 1',
                ],
            ]);
    }

    public function test_event_update_rejects_inverted_dates(): void
    {
        Sanctum::actingAs($this->user);

        $start = Carbon::tomorrow('America/Cancun')->setHour(10);
        $event = CalendarEvent::create([
            'user_id'    => $this->userId,
            'title'      => 'Reunión',
            'start_time' => $start,
            'end_time'   => (clone $start)->addHour(),
            'status'     => 'scheduled',
        ]);

        $id = (string) ($event->_id ?? $event->id);

        $response = $this->patchJson("/api/v1/assistant/events/{$id}", [
            'start_time' => $start->toIso8601String(),
            'end_time'   => (clone $start)->subHour()->toIso8601String(),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_update_workspace_note_and_task(): void
    {
        Sanctum::actingAs($this->user);

        $note = WorkspaceNote::create([
            'user_id'   => $this->userId,
            'title'     => 'Lista de compras',
            'content'   => 'Comprar café',
            'tags'      => ['todo', 'compras'],
            'is_pinned' => false,
        ]);

        $id = (string) ($note->_id ?? $note->id);

        $response = $this->patchJson("/api/v1/assistant/notes/{$id}", [
            'title'     => 'Lista de compras semanal',
            'content'   => 'Comprar café y té',
            'is_pinned' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'        => $id,
                    'title'     => 'Lista de compras semanal',
                    'content'   => 'Comprar café y té',
                    'is_pinned' => true,
                ],
            ]);
    }
}

