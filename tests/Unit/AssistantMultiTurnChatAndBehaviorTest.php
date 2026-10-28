<?php

namespace Tests\Unit;

use App\Models\AssistantMessage;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WorkspaceNote;
use App\Services\Assistant\AssistantOrchestratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantMultiTurnChatAndBehaviorTest extends TestCase
{
    protected AssistantOrchestratorService $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = app(AssistantOrchestratorService::class);
    }

    protected function tearDown(): void
    {
        AssistantMessage::where('prompt', 'like', '%MULTI_TURN%')->delete();
        User::where('email', 'like', 'multi_turn_%@example.com')->delete();
        Reminder::where('title', 'like', '%MULTI_TURN%')->delete();
        WorkspaceNote::where('title', 'like', '%MULTI_TURN%')->delete();
        parent::tearDown();
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->name = 'MultiTurn Tester';
        $user->email = 'multi_turn_' . uniqid() . '@example.com';
        $user->password = bcrypt('secret');
        $user->save();
        return $user;
    }

    public function test_load_conversation_history_retrieves_chronological_turns(): void
    {
        $user = $this->createTestUser();
        $convId = 'conv_turn_test_' . uniqid();

        // Turn 1 (older)
        AssistantMessage::create([
            'user_id'         => (string) $user->id,
            'conversation_id' => $convId,
            'channel'         => 'app',
            'direction'       => 'inbound',
            'prompt'          => 'MULTI_TURN Hola Cami, recuérdame comprar tomates',
            'reply'           => 'Anotado: comprar tomates.',
            'display_text'    => 'Anotado: comprar tomates.',
            'status'          => 'completed',
            'created_at'      => Carbon::now()->subMinutes(10),
        ]);

        // Turn 2 (newer)
        AssistantMessage::create([
            'user_id'         => (string) $user->id,
            'conversation_id' => $convId,
            'channel'         => 'app',
            'direction'       => 'inbound',
            'prompt'          => 'MULTI_TURN También agrega cebollas',
            'reply'           => 'Anotado: cebollas.',
            'display_text'    => 'Anotado: cebollas.',
            'status'          => 'completed',
            'created_at'      => Carbon::now()->subMinutes(5),
        ]);

        $history = $this->orchestrator->loadConversationHistory($user, $convId, 'app', 20);

        $this->assertCount(4, $history);
        $this->assertEquals('user', $history[0]['role']);
        $this->assertStringContainsString('comprar tomates', $history[0]['content']);
        $this->assertEquals('assistant', $history[1]['role']);
        $this->assertEquals('Anotado: comprar tomates.', $history[1]['content']);

        $this->assertEquals('user', $history[2]['role']);
        $this->assertStringContainsString('cebollas', $history[2]['content']);
        $this->assertEquals('assistant', $history[3]['role']);
        $this->assertEquals('Anotado: cebollas.', $history[3]['content']);
    }

    public function test_build_chat_payload_assembles_system_history_and_prompt_correctly(): void
    {
        $systemPrompt = "Eres Cami, la asistente ejecutiva.";
        $history = [
            ['role' => 'user', 'content' => 'Hola Cami'],
            ['role' => 'assistant', 'content' => '¡Hola! ¿En qué te ayudo hoy?'],
            ['role' => 'user', 'content' => 'Anota comprar tomates'],
            ['role' => 'assistant', 'content' => 'Anotado: comprar tomates.'],
        ];

        $payload = $this->orchestrator->buildChatPayload($systemPrompt, '¿Qué te pedí que anotaras?', $history, 20);

        $this->assertCount(6, $payload);
        $this->assertEquals('system', $payload[0]['role']);
        $this->assertEquals($systemPrompt, $payload[0]['content']);

        $this->assertEquals('user', $payload[1]['role']);
        $this->assertEquals('Hola Cami', $payload[1]['content']);

        $this->assertEquals('assistant', $payload[2]['role']);
        $this->assertEquals('¡Hola! ¿En qué te ayudo hoy?', $payload[2]['content']);

        $this->assertEquals('user', $payload[3]['role']);
        $this->assertEquals('Anota comprar tomates', $payload[3]['content']);

        $this->assertEquals('assistant', $payload[4]['role']);
        $this->assertEquals('Anotado: comprar tomates.', $payload[4]['content']);

        $this->assertEquals('user', $payload[5]['role']);
        $this->assertEquals('¿Qué te pedí que anotaras?', $payload[5]['content']);
    }

    public function test_build_chat_payload_enforces_window_limit(): void
    {
        $systemPrompt = "Eres Cami.";
        $history = [];
        for ($i = 1; $i <= 30; $i++) {
            $history[] = ['role' => ($i % 2 === 1 ? 'user' : 'assistant'), 'content' => "Mensaje {$i}"];
        }

        // Limit window to 10 messages
        $payload = $this->orchestrator->buildChatPayload($systemPrompt, 'Nuevo mensaje', $history, 10);

        // Expected: 1 system + 10 history + 1 prompt = 12 messages
        $this->assertCount(12, $payload);
        $this->assertEquals('system', $payload[0]['role']);
        $this->assertEquals('user', $payload[11]['role']);
        $this->assertEquals('Nuevo mensaje', $payload[11]['content']);
        // Oldest preserved in history should be Mensaje 21
        $this->assertEquals('Mensaje 21', $payload[1]['content']);
    }

    public function test_interact_endpoint_persists_user_prompt_and_reply_with_conversation_id(): void
    {
        $user = $this->createTestUser();
        Sanctum::actingAs($user, ['*']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'entities' => [
                                    [
                                        'type'     => 'task',
                                        'title'    => 'MULTI_TURN comprar tomates',
                                        'content'  => 'Comprar tomates frescos',
                                        'category' => 'Personal/Compras',
                                    ],
                                ],
                                'display_text' => 'Anotado: comprar tomates.',
                                'speech_text'  => 'Anotado: comprar tomates.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/assistant/interact', [
            'prompt' => 'MULTI_TURN recuérdame comprar tomates',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.display_text', 'Anotado: comprar tomates.');

        $convId = $response->json('data.conversation_id');
        $this->assertNotEmpty($convId);

        $savedMsg = AssistantMessage::where('user_id', (string) $user->id)
            ->where('prompt', 'like', '%comprar tomates%')
            ->first();

        $this->assertNotNull($savedMsg);
        $this->assertEquals('completed', $savedMsg->status);
        $this->assertEquals($convId, $savedMsg->conversation_id);
        $this->assertEquals('Anotado: comprar tomates.', $savedMsg->reply);
    }

    public function test_interact_endpoint_injects_conversation_history_from_db_into_llm_call(): void
    {
        $user = $this->createTestUser();
        $convId = 'conv_session_' . uniqid();
        Sanctum::actingAs($user, ['*']);

        // Seed prior turn in DB
        AssistantMessage::create([
            'user_id'         => (string) $user->id,
            'conversation_id' => $convId,
            'channel'         => 'app',
            'direction'       => 'inbound',
            'prompt'          => 'MULTI_TURN Ayer compré tomates',
            'reply'           => 'Entendido, registrado.',
            'display_text'    => 'Entendido, registrado.',
            'status'          => 'completed',
            'created_at'      => Carbon::now()->subMinutes(15),
        ]);

        $recordedMessages = [];

        Http::fake([
            '*/chat/completions' => function ($request) use (&$recordedMessages) {
                $body = json_decode($request->body(), true);
                $recordedMessages = $body['messages'] ?? [];

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'display_text' => 'Compraste tomates ayer.',
                                    'speech_text'  => 'Compraste tomates ayer.',
                                    'action'       => 'conversation',
                                ]),
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $response = $this->postJson('/api/v1/assistant/interact', [
            'conversation_id' => $convId,
            'prompt'          => 'MULTI_TURN ¿Qué compré ayer?',
        ]);

        $response->assertOk();

        // Verify that the LLM payload received the full chronological history from DB
        $this->assertNotEmpty($recordedMessages);
        $this->assertEquals('system', $recordedMessages[0]['role']);

        $roles = array_column($recordedMessages, 'role');
        $contents = array_column($recordedMessages, 'content');

        // Prior turn from DB must be present
        $this->assertContains('MULTI_TURN Ayer compré tomates', $contents);
        $this->assertContains('Entendido, registrado.', $contents);

        // New prompt must be the last message
        $lastMessage = end($recordedMessages);
        $this->assertEquals('user', $lastMessage['role']);
        $this->assertEquals('MULTI_TURN ¿Qué compré ayer?', $lastMessage['content']);
    }

    public function test_system_prompt_enforces_concise_on_demand_and_no_duplicates(): void
    {
        $user = new User();
        $user->name = 'Johan Alberto';

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('buildSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->orchestrator, Carbon::now()->toIso8601String(), $user);

        // 1. Conversational and concise responses (no inventory dumps on task registration)
        $this->assertStringContainsString('RESPUESTAS CONVERSACIONALES Y CONCISAS AL CREAR O MODIFICAR', $prompt);
        $this->assertStringContainsString('NO imprimas un reporte completo, inventario o volcado de base de datos', $prompt);
        $this->assertStringContainsString('Anotado: comprar tomates', $prompt);

        // 2. On-demand listings only
        $this->assertStringContainsString('LISTADOS BAJO DEMANDA', $prompt);
        $this->assertStringContainsString('SOLO desglosa o resume pendientes cuando el usuario lo solicite EXPLÍCITAMENTE', $prompt);

        // 3. Elimination of duplicates between priorities and thematic categories
        $this->assertStringContainsString('ELIMINACIÓN ESTRICTA DE DUPLICADOS', $prompt);
        $this->assertStringContainsString('PROHIBIDO duplicar ítems entre "Prioridades" y sus categorías temáticas', $prompt);

        // 4. Natural tone
        $this->assertStringContainsString('TONO NATURAL', $prompt);
        $this->assertStringContainsString('Elimina saludos robóticos', $prompt);
    }

    public function test_format_local_read_fallback_deduplicates_titles_and_removes_robotic_intro(): void
    {
        $user = new User();
        $user->name = 'Johan';

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('formatLocalReadFallback');
        $method->setAccessible(true);

        $notes = [
            ['title' => 'Comprar tomates'],
            ['title' => 'Pagar internet'],
        ];

        // Reminder has identical title to one of the notes
        $reminders = [
            ['title' => 'Comprar tomates', 'remind_at' => '29/10/2026 09:00'],
            ['title' => 'Cita con dentista', 'remind_at' => '30/10/2026 11:00'],
        ];

        $result = $method->invoke($this->orchestrator, $user, $reminders, $notes, [], '¿qué pendientes tengo?');

        $displayText = $result['display_text'];

        // Must NOT have robotic intro
        $this->assertStringNotContainsString("esto es lo que tienes pendiente:", $displayText);

        // "Comprar tomates" should appear only once (deduplicated)
        $this->assertEquals(1, substr_count($displayText, 'Comprar tomates'));

        // All distinct items should be present
        $this->assertStringContainsString('Pagar internet', $displayText);
        $this->assertStringContainsString('Cita con dentista', $displayText);
    }
}

