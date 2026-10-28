<?php

namespace Tests\Unit;

use App\Models\AssistantMessage;
use App\Models\DailySummary;
use App\Models\User;
use App\Services\Assistant\AssistantOrchestratorService;
use App\Services\Assistant\DailySummaryService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class AssistantDailySummariesTest extends TestCase
{
    protected AssistantOrchestratorService $service;
    protected DailySummaryService $summaryService;
    protected User $user;
    protected string $userId;
    protected string $timezone = 'America/Cancun';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssistantOrchestratorService::class);
        $this->summaryService = app(DailySummaryService::class);

        $this->userId = 'test_user_daily_' . uniqid();
        $this->user = new User();
        $this->user->id = $this->userId;
        $this->user->name = 'Johan Palma';
    }

    protected function tearDown(): void
    {
        AssistantMessage::where('user_id', $this->userId)->delete();
        DailySummary::where('user_id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_daily_summary_model_persists_with_user_date_decisions_and_actions(): void
    {
        $summary = DailySummary::create([
            'user_id'       => $this->userId,
            'date'          => '2026-10-27',
            'summary'       => 'Revisión técnica de la arquitectura de pagos.',
            'key_decisions' => ['Usar Stripe para cargos recurrentes', 'Migrar base de datos el viernes'],
            'action_items'  => ['Enviar documentación al equipo', 'Actualizar credenciales'],
            'turns_count'   => 8,
        ]);

        $this->assertNotNull($summary->id ?? $summary->_id);
        $this->assertEquals($this->userId, $summary->user_id);
        $this->assertEquals('2026-10-27', $summary->date);
        $this->assertCount(2, $summary->key_decisions);
        $this->assertCount(2, $summary->action_items);

        $retrieved = DailySummary::where('user_id', $this->userId)->where('date', '2026-10-27')->first();
        $this->assertNotNull($retrieved);
        $this->assertEquals('Revisión técnica de la arquitectura de pagos.', $retrieved->summary);
        $this->assertContains('Usar Stripe para cargos recurrentes', $retrieved->key_decisions);
    }

    public function test_search_conversation_history_queries_daily_summaries_for_past_days(): void
    {
        $yesterday = Carbon::yesterday($this->timezone)->format('Y-m-d');

        DailySummary::create([
            'user_id'       => $this->userId,
            'date'          => $yesterday,
            'summary'       => 'Se acordó el despliegue a producción y la revisión con el cliente.',
            'key_decisions' => ['Despliegue a producción programado para el jueves a las 21:00'],
            'action_items'  => ['Confirmar con soporte técnico', 'Generar backup previo'],
            'turns_count'   => 6,
        ]);

        $startIso = Carbon::parse($yesterday, $this->timezone)->startOfDay()->toIso8601String();
        $endIso   = Carbon::parse($yesterday, $this->timezone)->endOfDay()->toIso8601String();

        $result = $this->service->executeSearchConversationHistory($this->user, [
            'start_date' => $startIso,
            'end_date'   => $endIso,
        ]);

        $this->assertTrue($result['success']);
        $text = $result['formatted_text'] ?? '';
        $this->assertStringContainsString('Resumen consolidado del día ' . $yesterday, $text);
        $this->assertStringContainsString('Se acordó el despliegue a producción', $text);
        $this->assertStringContainsString('Despliegue a producción programado', $text);
        $this->assertStringContainsString('Confirmar con soporte técnico', $text);
    }

    public function test_search_conversation_history_queries_active_session_messages_for_current_day(): void
    {
        $now = Carbon::now($this->timezone);
        $todayStr = $now->format('Y-m-d');

        // Turno de la sesión activa de hoy
        AssistantMessage::create([
            'user_id'    => $this->userId,
            'role'       => 'user',
            'content'    => '¿Cuál es el estatus del servidor de staging hoy en la mañana?',
            'created_at' => (clone $now)->subMinutes(10),
            'status'     => 'completed',
        ]);
        AssistantMessage::create([
            'user_id'    => $this->userId,
            'role'       => 'assistant',
            'content'    => 'El servidor de staging está operativo y sin errores.',
            'created_at' => (clone $now)->subMinutes(9),
            'status'     => 'completed',
        ]);

        $startIso = Carbon::parse($todayStr, $this->timezone)->startOfDay()->toIso8601String();
        $endIso   = Carbon::parse($todayStr, $this->timezone)->endOfDay()->toIso8601String();

        $result = $this->service->executeSearchConversationHistory($this->user, [
            'start_date' => $startIso,
            'end_date'   => $endIso,
        ]);

        $this->assertTrue($result['success']);
        $text = $result['formatted_text'] ?? '';
        $this->assertStringContainsString('estatus del servidor de staging hoy', $text);
        $this->assertStringContainsString('servidor de staging está operativo', $text);
        // Debe ser el diálogo turno a turno de la sesión activa
        $this->assertStringNotContainsString('Resumen consolidado del día', $text);
    }

    public function test_scheduler_contains_daily_summaries_command(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $hasSummaryJob = $events->contains(function ($event) {
            return str_contains($event->command ?? '', 'assistant:generate-daily-summaries');
        });

        $this->assertTrue($hasSummaryJob, 'Scheduler must register assistant:generate-daily-summaries command');
    }
}
