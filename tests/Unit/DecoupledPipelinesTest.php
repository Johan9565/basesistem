<?php

namespace Tests\Unit;

use App\Models\AssistantAiModel;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WorkspaceNote;
use App\Services\Assistant\AssistantOrchestratorService;
use App\Services\Assistant\IntentRouterService;
use App\Services\Assistant\ModelRouterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DecoupledPipelinesTest extends TestCase
{
    protected AssistantOrchestratorService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssistantOrchestratorService::class);

        $this->user = new User();
        $this->user->id = 'test_user_pipe_' . uniqid();
        $this->user->name = 'Johan Test';
    }

    public function test_read_pipeline_queries_database_and_calls_fast_model(): void
    {
        // 1. Arrange: Seed a note and reminder for this user
        WorkspaceNote::create([
            'user_id' => $this->user->id,
            'title'   => 'Test Note Architecture',
            'content' => 'Microservicios con Model Router',
            'tags'    => ['todo', 'desarrollo'],
        ]);

        Reminder::create([
            'user_id'   => $this->user->id,
            'title'     => 'Revisar pipelines',
            'remind_at' => Carbon::now()->addHours(2),
            'status'    => 'pending',
        ]);

        // 2. Mock Fast Model HTTP response
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'display_text' => "Hola Johan, tienes 1 pendiente:
- Revisar pipelines",
                                'speech_text'  => "Hola Johan, tienes un pendiente: Revisar pipelines.",
                                'action'       => 'get_pending_reminders',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        // 3. Act: Execute read pipeline via interact
        $result = $this->service->interact($this->user, 'hola cami que pendientes tengo');

        // 4. Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('get_pending_reminders', $result['action']);
        $this->assertStringContainsString('Revisar pipelines', $result['display_text']);
        $this->assertStringContainsString('Revisar pipelines', $result['speech_text']);
        $this->assertStringNotContainsString('*', $result['speech_text']);
    }

    public function test_write_pipeline_forces_json_and_persists_entities_directly(): void
    {
        // 1. Mock Reasoning Model returning strict JSON with atomic entities
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'entities' => [
                                    [
                                        'type'     => 'reminder',
                                        'title'    => 'Enviar reporte financiero',
                                        'content'  => 'Enviar reporte financiero al contador',
                                        'due_date' => Carbon::tomorrow()->toDateString(),
                                        'time'     => '09:00',
                                        'category' => 'Finanzas/Admin',
                                        'tags'     => ['finanzas', 'reporte'],
                                    ],
                                    [
                                        'type'     => 'task',
                                        'title'    => 'Comprar cable HDMI',
                                        'content'  => 'Comprar cable HDMI de 2 metros',
                                        'category' => 'Personal/Compras',
                                        'tags'     => ['todo', 'compras'],
                                    ],
                                ],
                                'display_text' => "He registrado 2 pendientes:
- Recordatorio: Enviar reporte financiero a las 9 AM
- Tarea: Comprar cable HDMI",
                                'speech_text'  => "He registrado dos pendientes: enviar reporte financiero a las nueve de la mañana y comprar cable h d m i.",
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        // 2. Act: Execute write pipeline via interact
        $prompt = 'Recordarme enviar reporte financiero mañana a las 9 AM y comprar cable HDMI';
        $result = $this->service->interact($this->user, $prompt);

        // 3. Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('reporte financiero', $result['display_text']);

        // Check that entities were persisted directly into MongoDB
        $createdReminder = Reminder::where('user_id', $this->user->id)
            ->where('title', 'Enviar reporte financiero')
            ->first();
        $this->assertNotNull($createdReminder);
        $this->assertEquals('pending', $createdReminder->status);

        $createdNote = WorkspaceNote::where('user_id', $this->user->id)
            ->where('title', 'Comprar cable HDMI')
            ->first();
        $this->assertNotNull($createdNote);
        $this->assertContains('todo', $createdNote->tags ?? []);
    }

    public function test_read_pipeline_graceful_fallback_when_fast_model_fails(): void
    {
        // Arrange: Seed reminder
        Reminder::create([
            'user_id'   => $this->user->id,
            'title'     => 'Recordatorio de emergencia',
            'remind_at' => Carbon::now()->addHour(),
            'status'    => 'pending',
        ]);

        // Mock HTTP failure
        Http::fake([
            '*/chat/completions' => Http::response(['error' => 'Rate limit exceeded'], 429),
        ]);

        // Act
        $result = $this->service->interact($this->user, '¿cuáles son mis recordatorios para hoy?');

        // Assert: It should NOT crash, and should return formatted pre-queried records
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Recordatorio de emergencia', $result['display_text']);
        $this->assertStringContainsString('Recordatorio de emergencia', $result['speech_text']);
    }
}
