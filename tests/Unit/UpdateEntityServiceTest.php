<?php

namespace Tests\Unit;

use App\Models\CalendarEvent;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WorkspaceNote;
use App\Services\Assistant\UpdateEntityService;
use Carbon\Carbon;
use Tests\TestCase;

class UpdateEntityServiceTest extends TestCase
{
    protected UpdateEntityService $service;
    protected User $user;
    protected string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UpdateEntityService::class);

        $this->userId = 'test_user_update_' . uniqid();
        $this->user = new User();
        $this->user->id = $this->userId;
        $this->user->name = 'Carlos Test';
    }

    protected function tearDown(): void
    {
        CalendarEvent::where('user_id', $this->userId)->delete();
        Reminder::where('user_id', $this->userId)->delete();
        WorkspaceNote::where('user_id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_updates_reminder_and_resets_completed_status_on_reschedule(): void
    {
        $reminder = Reminder::create([
            'user_id'      => $this->userId,
            'title'        => 'Tomar medicamento',
            'scheduled_at' => Carbon::yesterday('America/Cancun')->setHour(10),
            'status'       => 'completed',
            'priority'     => 'medium',
        ]);

        $newDate = Carbon::tomorrow('America/Cancun')->setHour(18)->setMinute(30)->setSecond(0);

        $result = $this->service->update(
            $this->user,
            'reminder',
            (string) ($reminder->_id ?? $reminder->id),
            [
                'scheduled_at' => $newDate->toIso8601String(),
                'title'        => 'Tomar medicamento nocturno',
                'priority'     => 'high',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('reminder', $result['entity_type']);
        $this->assertEquals('Tomar medicamento nocturno', $result['data']['title']);
        $this->assertEquals('high', $result['data']['priority']);
        $this->assertEquals('pending', $result['data']['status'], 'Status should reset to pending when rescheduled to future');

        $fresh = Reminder::find($reminder->_id ?? $reminder->id);
        $this->assertEquals('pending', $fresh->status);
        $this->assertEquals('Tomar medicamento nocturno', $fresh->title);
    }

    public function test_updates_calendar_event_with_chronological_adjustment(): void
    {
        $start = Carbon::tomorrow('America/Cancun')->setHour(10)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHour();

        $event = CalendarEvent::create([
            'user_id'     => $this->userId,
            'title'       => 'Reunión de equipo',
            'start_time'  => $start,
            'end_time'    => $end,
            'location'    => 'Oficina A',
            'status'      => 'scheduled',
        ]);

        $newStart = Carbon::tomorrow('America/Cancun')->setHour(15)->setMinute(0)->setSecond(0);

        $result = $this->service->update(
            $this->user,
            'event',
            (string) ($event->_id ?? $event->id),
            [
                'start_time' => $newStart->toIso8601String(),
                'location'   => 'Sala B',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Sala B', $result['data']['location']);

        $fresh = CalendarEvent::find($event->_id ?? $event->id);
        $this->assertEquals('Sala B', $fresh->location);
        $this->assertTrue(Carbon::parse($fresh->end_time)->greaterThan(Carbon::parse($fresh->start_time)));
    }

    public function test_rejects_calendar_event_when_end_time_is_before_start_time(): void
    {
        $start = Carbon::tomorrow('America/Cancun')->setHour(10)->setMinute(0)->setSecond(0);

        $event = CalendarEvent::create([
            'user_id'     => $this->userId,
            'title'       => 'Cita dental',
            'start_time'  => $start,
            'end_time'    => (clone $start)->addHour(),
            'status'      => 'scheduled',
        ]);

        $invalidEnd = (clone $start)->subHours(2);

        $result = $this->service->update(
            $this->user,
            'event',
            (string) ($event->_id ?? $event->id),
            [
                'start_time' => $start->toIso8601String(),
                'end_time'   => $invalidEnd->toIso8601String(),
            ]
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(422, $result['status_code']);
        $this->assertStringContainsString('posterior', strtolower($result['message']));
    }

    public function test_updates_workspace_note_and_tags(): void
    {
        $note = WorkspaceNote::create([
            'user_id'   => $this->userId,
            'title'     => 'Idea proyecto',
            'content'   => 'Desarrollar asistente inteligente',
            'tags'      => ['ideas', 'ia'],
            'is_pinned' => false,
        ]);

        $result = $this->service->update(
            $this->user,
            'note',
            (string) ($note->_id ?? $note->id),
            [
                'title'     => 'Idea proyecto V2',
                'content'   => 'Desarrollar asistente inteligente con Tool Calling',
                'tags'      => ['ideas', 'ia', 'tools'],
                'is_pinned' => true,
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Idea proyecto V2', $result['data']['title']);
        $this->assertTrue($result['data']['is_pinned']);
        $this->assertContains('tools', $result['data']['tags']);
    }

    public function test_updates_task_stored_in_workspace_note(): void
    {
        $task = WorkspaceNote::create([
            'user_id'   => $this->userId,
            'title'     => 'Comprar leche',
            'content'   => 'Comprar leche deslactosada',
            'tags'      => ['todo', 'compras'],
            'is_pinned' => false,
        ]);

        $result = $this->service->update(
            $this->user,
            'task',
            (string) ($task->_id ?? $task->id),
            [
                'title'   => 'Comprar leche de almendras',
                'status'  => 'completed',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Comprar leche de almendras', $result['data']['title']);
        $this->assertEquals('completed', $result['data']['status']);
    }

    public function test_returns_404_when_entity_does_not_exist(): void
    {
        $result = $this->service->update(
            $this->user,
            'reminder',
            'non_existent_id_12345',
            ['title' => 'Nuevo']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(404, $result['status_code']);
    }

    public function test_resolves_entity_by_exact_id(): void
    {
        $reminder = Reminder::create([
            'user_id'      => $this->userId,
            'title'        => 'Pagar tarjeta',
            'scheduled_at' => Carbon::tomorrow('America/Cancun')->setHour(12),
            'status'       => 'pending',
        ]);

        $id = (string) ($reminder->_id ?? $reminder->id);
        $resolution = $this->service->resolveEntityByIdentifier($this->user, $id);

        $this->assertEquals('exact', $resolution['status']);
        $this->assertEquals($id, (string) ($resolution['match']->_id ?? $resolution['match']->id));
        $this->assertEquals('reminder', $resolution['type']);
    }

    public function test_detects_ambiguity_when_multiple_matches_exist(): void
    {
        CalendarEvent::create([
            'user_id'    => $this->userId,
            'title'      => 'Cita con el doctor Pérez',
            'start_time' => Carbon::tomorrow('America/Cancun')->setHour(9),
            'end_time'   => Carbon::tomorrow('America/Cancun')->setHour(10),
        ]);

        CalendarEvent::create([
            'user_id'    => $this->userId,
            'title'      => 'Cita con el doctor Gómez',
            'start_time' => Carbon::tomorrow('America/Cancun')->addDays(2)->setHour(16),
            'end_time'   => Carbon::tomorrow('America/Cancun')->addDays(2)->setHour(17),
        ]);

        $resolution = $this->service->resolveEntityByIdentifier($this->user, 'cita con el doctor');

        $this->assertEquals('ambiguous', $resolution['status']);
        $this->assertCount(2, $resolution['candidates']);
        $this->assertStringContainsString('coincidencias', strtolower($resolution['message']));
    }

    public function test_resolves_unique_text_query_as_exact_match(): void
    {
        CalendarEvent::create([
            'user_id'    => $this->userId,
            'title'      => 'Dentista limpieza semestral',
            'start_time' => Carbon::tomorrow('America/Cancun')->setHour(11),
            'end_time'   => Carbon::tomorrow('America/Cancun')->setHour(12),
        ]);

        $resolution = $this->service->resolveEntityByIdentifier($this->user, 'dentista');

        $this->assertEquals('exact', $resolution['status']);
        $this->assertEquals('Dentista limpieza semestral', $resolution['match']->title);
    }
}

