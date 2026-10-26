<?php

namespace Tests\Unit;

use App\Models\Reminder;
use App\Models\User;
use App\Models\WorkspaceNote;
use App\Services\Assistant\AssistantOrchestratorService;
use Carbon\Carbon;
use Tests\TestCase;

class AssistantTaskClassificationAndAtomicityTest extends TestCase
{
    protected AssistantOrchestratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssistantOrchestratorService::class);
    }

    public function test_system_prompt_includes_classification_and_atomicity_rules(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->service, Carbon::now()->toIso8601String());

        $this->assertStringContainsString('MÓDULO: CLASIFICACIÓN Y DIVISIÓN DE TAREAS / RECORDATORIOS', $prompt);
        $this->assertStringContainsString('Tipo "TODO"', $prompt);
        $this->assertStringContainsString('Tipo "RECORDATORIO_TEMPORAL"', $prompt);
        $this->assertStringContainsString('REGLA DE ATOMICIDAD (DESCOMPOSICIÓN ESTRICTA)', $prompt);
        $this->assertStringContainsString('Anotados en pendientes: 1. Comprar paraguas | 2. Comprar impermeable.', $prompt);
    }

    public function test_system_prompt_includes_cami_identity_and_greeting_rule(): void
    {
        $user = new User();
        $user->name = 'Johan Alberto Chin';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->service, Carbon::now()->toIso8601String(), $user);

        $this->assertStringContainsString('Te llamas Cami', $prompt);
        $this->assertStringContainsString('Johan', $prompt);
        $this->assertStringContainsString('NUNCA llames al usuario "Cami"', $prompt);
        $this->assertStringContainsString('CONVERSACIÓN Y SALUDOS', $prompt);
    }

    public function test_tools_schema_supports_concise_workspace_notes(): void
    {
        $schemas = $this->service->getToolsSchema();
        $noteTool = null;

        foreach ($schemas as $schema) {
            if (($schema['function']['name'] ?? '') === 'create_workspace_note') {
                $noteTool = $schema['function'];
                break;
            }
        }

        $this->assertNotNull($noteTool);
        $this->assertEquals(['title'], $noteTool['parameters']['required']);
    }

    public function test_execute_get_pending_reminders_retrieves_both_reminders_and_notes(): void
    {
        $userId = 'test-unified-' . uniqid();
        $user = new User();
        $user->id = $userId;

        // Create 1 scheduled reminder
        $reminder = Reminder::create([
            'user_id'      => $userId,
            'title'        => 'Revisar reportes contables',
            'scheduled_at' => Carbon::now()->addDays(2),
            'status'       => 'pending',
            'priority'     => 'high',
        ]);

        // Create 2 atemporal To-Dos
        $note1 = WorkspaceNote::create([
            'user_id'   => $userId,
            'title'     => 'Comprar pan de muerto',
            'content'   => 'Comprar pan de muerto',
            'tags'      => ['todo', 'compras'],
            'is_pinned' => false,
        ]);

        $note2 = WorkspaceNote::create([
            'user_id'   => $userId,
            'title'     => 'Comprar tomates',
            'content'   => 'Comprar tomates',
            'tags'      => ['todo', 'compras'],
            'is_pinned' => false,
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('executeGetPendingReminders');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $user, []);

        $this->assertArrayHasKey('recordatorios_programados', $result);
        $this->assertArrayHasKey('tareas_y_pendientes_atemporales', $result);
        $this->assertCount(1, $result['recordatorios_programados']);
        $this->assertCount(2, $result['tareas_y_pendientes_atemporales']);
        $this->assertEquals('Revisar reportes contables', $result['recordatorios_programados'][0]['title']);
        $this->assertEquals(3, $result['total_pendientes']);

        // Clean up
        $reminder->delete();
        $note1->delete();
        $note2->delete();
    }

    public function test_friendly_retrieval_fallback_does_not_leak_technical_strings(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildFriendlyRetrievalFallback');
        $method->setAccessible(true);

        $pendingData = [
            'recordatorios_programados' => [
                ['title' => 'Revisar reportes', 'scheduled_at' => '2026-10-30 09:00'],
            ],
            'tareas_y_pendientes_atemporales' => [
                ['title' => 'Comprar tomates'],
            ],
        ];

        $fallback = $method->invoke($this->service, $pendingData, 'get_pending_reminders');

        $this->assertStringNotContainsString('Consulta realizada', $fallback);
        $this->assertStringNotContainsString('registros coincidentes', $fallback);
        $this->assertStringContainsString('Revisar reportes', $fallback);
        $this->assertStringContainsString('Comprar tomates', $fallback);
    }

    public function test_handle_tool_calls_executes_multiple_atomized_notes_and_formats_concise_response(): void
    {
        $user = new User();
        $user->id = 'test-user-' . uniqid();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('handleToolCalls');
        $method->setAccessible(true);

        $toolCalls = [
            [
                'id'       => 'call_1',
                'type'     => 'function',
                'function' => [
                    'name'      => 'create_workspace_note',
                    'arguments' => json_encode(['title' => 'Comprar paraguas']),
                ],
            ],
            [
                'id'       => 'call_2',
                'type'     => 'function',
                'function' => [
                    'name'      => 'create_workspace_note',
                    'arguments' => json_encode(['title' => 'Comprar impermeable']),
                ],
            ],
        ];

        $result = $method->invoke(
            $this->service,
            $user,
            $toolCalls,
            [],
            'https://fake-endpoint.test',
            ['message' => []],
            Carbon::now('America/Cancun')
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Anotados en pendientes: 1. Comprar paraguas | 2. Comprar impermeable.', $result['message']);
        $this->assertCount(2, $result['data']);

        // Clean up created test notes
        WorkspaceNote::where('user_id', (string) $user->id)->delete();
    }

    public function test_handle_tool_calls_executes_multiple_atomized_reminders(): void
    {
        $user = new User();
        $user->id = 'test-user-rem-' . uniqid();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('handleToolCalls');
        $method->setAccessible(true);

        $toolCalls = [
            [
                'id'       => 'call_1',
                'type'     => 'function',
                'function' => [
                    'name'      => 'create_reminder',
                    'arguments' => json_encode([
                        'title'        => 'Comprar paraguas',
                        'scheduled_at' => '2026-10-23T17:00:00',
                    ]),
                ],
            ],
            [
                'id'       => 'call_2',
                'type'     => 'function',
                'function' => [
                    'name'      => 'create_reminder',
                    'arguments' => json_encode([
                        'title'        => 'Comprar impermeable',
                        'scheduled_at' => '2026-10-23T17:00:00',
                    ]),
                ],
            ],
        ];

        $result = $method->invoke(
            $this->service,
            $user,
            $toolCalls,
            [],
            'https://fake-endpoint.test',
            ['message' => []],
            Carbon::now('America/Cancun')
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Recordatorios programados: 1. Comprar paraguas | 2. Comprar impermeable.', $result['message']);
        $this->assertCount(2, $result['data']);

        // Clean up created test reminders
        Reminder::where('user_id', (string) $user->id)->delete();
    }
}
