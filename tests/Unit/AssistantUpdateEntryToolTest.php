<?php

namespace Tests\Unit;

use App\Models\CalendarEvent;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WorkspaceNote;
use App\Services\Assistant\AssistantOrchestratorService;
use App\Services\Assistant\IntentRouterService;
use Carbon\Carbon;
use Tests\TestCase;

class AssistantUpdateEntryToolTest extends TestCase
{
    protected AssistantOrchestratorService $service;
    protected IntentRouterService $intentRouter;
    protected User $user;
    protected string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssistantOrchestratorService::class);
        $this->intentRouter = app(IntentRouterService::class);

        $this->userId = 'test_user_tool_' . uniqid();
        $this->user = new User();
        $this->user->id = $this->userId;
        $this->user->name = 'Johan Chin';
    }

    protected function tearDown(): void
    {
        CalendarEvent::where('user_id', $this->userId)->delete();
        Reminder::where('user_id', $this->userId)->delete();
        WorkspaceNote::where('user_id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_tools_schema_includes_update_entry_definition(): void
    {
        $schemas = $this->service->getToolsSchema();
        $updateTool = null;

        foreach ($schemas as $schema) {
            if (($schema['function']['name'] ?? '') === 'update_entry') {
                $updateTool = $schema['function'];
                break;
            }
        }

        $this->assertNotNull($updateTool, 'Tool update_entry must be registered in tools schema');
        $this->assertStringContainsString('editar o reprogramar', $updateTool['description']);
        $this->assertArrayHasKey('identifier', $updateTool['parameters']['properties']);
        $this->assertArrayHasKey('entity_type', $updateTool['parameters']['properties']);
        $this->assertArrayHasKey('updates', $updateTool['parameters']['properties']);
        $this->assertContains('identifier', $updateTool['parameters']['required']);
        $this->assertContains('updates', $updateTool['parameters']['required']);
    }

    public function test_system_prompt_instructs_to_use_update_entry_and_brief_confirmation(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->service, Carbon::now('America/Cancun')->toIso8601String(), $this->user);

        $this->assertStringContainsString('update_entry', $prompt);
        $this->assertStringContainsString('reprogramar', strtolower($prompt));
        $this->assertStringContainsString('Listo, moví la cita', $prompt);
    }

    public function test_intent_router_classifies_update_prompts_to_reasoning_fallback(): void
    {
        $prompts = [
            'cambia la hora de la cita con el doctor para las 5:00 PM',
            'reprograma la reunión con el cliente para el viernes',
            'mueve el recordatorio del medicamento a las 8 de la noche',
            'actualiza la nota de desarrollo WebGPU',
            'edita el pendiente de comprar leche deslactosada',
            'modifica la cita del dentista para las 4pm',
        ];

        foreach ($prompts as $prompt) {
            $classification = $this->intentRouter->classify($prompt);
            $this->assertEquals(
                'reasoning_fallback',
                $classification['route'],
                "Prompt should route to reasoning_fallback for tool calling: {$prompt}"
            );
        }
    }

    public function test_execute_update_entry_modifies_record_when_unique_match_and_confirms_naturally(): void
    {
        $event = CalendarEvent::create([
            'user_id'    => $this->userId,
            'title'      => 'Cita con el dentista',
            'start_time' => Carbon::parse('2026-10-29 10:00:00', 'America/Cancun'),
            'end_time'   => Carbon::parse('2026-10-29 11:00:00', 'America/Cancun'),
            'status'     => 'scheduled',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('executeUpdateEntry');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $this->user, [
            'identifier'  => 'cita con el dentista',
            'entity_type' => 'event',
            'updates'     => [
                'datetime' => '2026-10-29 17:00:00',
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('update_entry', $result['action']);
        $this->assertStringContainsString('Listo', $result['message']);
        $this->assertStringContainsString('17:00', $result['data']['start_time']);

        $fresh = CalendarEvent::find($event->_id ?? $event->id);
        $this->assertEquals('2026-10-29 17:00:00', Carbon::parse($fresh->start_time)->format('Y-m-d H:i:s'));
    }

    public function test_execute_update_entry_requests_clarification_on_ambiguity_without_modifying(): void
    {
        $event1 = CalendarEvent::create([
            'user_id'    => $this->userId,
            'title'      => 'Cita con el doctor Pérez',
            'start_time' => Carbon::parse('2026-10-29 09:00:00', 'America/Cancun'),
            'end_time'   => Carbon::parse('2026-10-29 10:00:00', 'America/Cancun'),
        ]);

        $event2 = CalendarEvent::create([
            'user_id'    => $this->userId,
            'title'      => 'Cita con el doctor López',
            'start_time' => Carbon::parse('2026-10-31 16:00:00', 'America/Cancun'),
            'end_time'   => Carbon::parse('2026-10-31 17:00:00', 'America/Cancun'),
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('executeUpdateEntry');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $this->user, [
            'identifier' => 'cita con el doctor',
            'updates'    => [
                'time' => '18:00',
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('needs_clarification', $result['action']);
        $this->assertStringContainsString('coincidencias', strtolower($result['message']));
        $this->assertStringContainsString('¿Cuál de ellas deseas modificar?', $result['message']);

        // Verify neither record was modified
        $this->assertEquals('09:00', Carbon::parse($event1->fresh()->start_time)->setTimezone('America/Cancun')->format('H:i'));
        $this->assertEquals('16:00', Carbon::parse($event2->fresh()->start_time)->setTimezone('America/Cancun')->format('H:i'));
    }

    public function test_handle_tool_calls_dispatches_update_entry(): void
    {
        $reminder = Reminder::create([
            'user_id'      => $this->userId,
            'title'        => 'Tomar pastillas',
            'scheduled_at' => Carbon::parse('2026-10-29 08:00:00', 'America/Cancun'),
            'status'       => 'pending',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('handleToolCalls');
        $method->setAccessible(true);

        $toolCalls = [
            [
                'id'       => 'call_update_1',
                'type'     => 'function',
                'function' => [
                    'name'      => 'update_entry',
                    'arguments' => json_encode([
                        'identifier' => 'Tomar pastillas',
                        'updates'    => [
                            'scheduled_at' => '2026-10-29 20:00:00',
                        ],
                    ]),
                ],
            ],
        ];

        $result = $method->invoke(
            $this->service,
            $this->user,
            $toolCalls,
            [],
            'https://fake-endpoint.test',
            ['message' => []],
            Carbon::now('America/Cancun')
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('update_entry', $result['action']);
        $this->assertStringContainsString('Listo', $result['message']);

        $fresh = Reminder::find($reminder->_id ?? $reminder->id);
        $this->assertEquals('20:00', Carbon::parse($fresh->scheduled_at)->format('H:i'));
    }
}
