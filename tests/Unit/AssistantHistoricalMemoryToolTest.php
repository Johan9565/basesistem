<?php

namespace Tests\Unit;

use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\AssistantOrchestratorService;
use App\Services\Assistant\IntentRouterService;
use Carbon\Carbon;
use Tests\TestCase;

class AssistantHistoricalMemoryToolTest extends TestCase
{
    protected AssistantOrchestratorService $service;
    protected IntentRouterService $intentRouter;
    protected User $user;
    protected string $userId;
    protected string $timezone = 'America/Cancun';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssistantOrchestratorService::class);
        $this->intentRouter = app(IntentRouterService::class);

        $this->userId = 'test_user_memory_' . uniqid();
        $this->user = new User();
        $this->user->id = $this->userId;
        $this->user->name = 'Johan Palma';
    }

    protected function tearDown(): void
    {
        AssistantMessage::where('user_id', $this->userId)->delete();
        \App\Models\DailySummary::where('user_id', $this->userId)->delete();
        parent::tearDown();
    }

    protected function createHistoricalMessage(array $attributes): AssistantMessage
    {
        $createdAt = $attributes['created_at'] ?? Carbon::now($this->timezone);
        unset($attributes['created_at']);

        $msg = new AssistantMessage($attributes);
        $msg->timestamps = false;
        $msg->created_at = $createdAt;
        $msg->save();

        return $msg;
    }

    public function test_assistant_message_stores_role_content_and_isodate(): void
    {
        $now = Carbon::now($this->timezone);

        $msg = AssistantMessage::create([
            'user_id'    => $this->userId,
            'role'       => 'user',
            'content'    => 'Hola Cami, revisemos la propuesta técnica',
            'created_at' => $now,
            'channel'    => 'app',
            'status'     => 'completed',
        ]);

        $this->assertNotNull($msg->id ?? $msg->_id);
        $this->assertEquals('user', $msg->role);
        $this->assertEquals('Hola Cami, revisemos la propuesta técnica', $msg->content);
        $this->assertEquals($this->userId, $msg->user_id);

        $retrieved = AssistantMessage::find($msg->id ?? $msg->_id);
        $this->assertEquals('user', $retrieved->role);
        $this->assertEquals('Hola Cami, revisemos la propuesta técnica', $retrieved->content);
        $this->assertInstanceOf(\DateTimeInterface::class, $retrieved->created_at);
    }

    public function test_tools_schema_includes_search_conversation_history(): void
    {
        $schemas = $this->service->getToolsSchema();
        $historyTool = null;

        foreach ($schemas as $schema) {
            if (($schema['function']['name'] ?? '') === 'search_conversation_history') {
                $historyTool = $schema['function'];
                break;
            }
        }

        $this->assertNotNull($historyTool, 'Tool search_conversation_history must be registered in getToolsSchema()');
        $this->assertStringContainsString('historial', strtolower($historyTool['description']));
        $this->assertArrayHasKey('query', $historyTool['parameters']['properties']);
        $this->assertArrayHasKey('start_date', $historyTool['parameters']['properties']);
        $this->assertArrayHasKey('end_date', $historyTool['parameters']['properties']);
    }

    public function test_system_prompt_instructs_on_historical_memory_and_temporal_anchor(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildSystemPrompt');
        $method->setAccessible(true);

        $nowIso = Carbon::now($this->timezone)->toIso8601String();
        $prompt = $method->invoke($this->service, $nowIso, $this->user);

        $this->assertStringContainsString($nowIso, $prompt, 'Must inject live timestamp as temporal anchor');
        $this->assertStringContainsString($this->timezone, $prompt, 'Must include timezone explicitly');
        $this->assertStringContainsString('search_conversation_history', $prompt, 'Prompt must reference search_conversation_history tool');
        $this->assertStringContainsString('honestidad', strtolower($prompt), 'Prompt must instruct honesty if no records found');
    }

    public function test_intent_router_classifies_past_conversation_queries_to_reasoning_fallback(): void
    {
        $prompts = [
            '¿qué hablamos ayer?',
            '¿de qué hablamos el lunes pasado?',
            '¿recuerdas qué acordamos sobre el presupuesto?',
            '¿qué te dije la semana pasada acerca del servidor?',
            '¿qué platicamos ayer en la tarde?',
        ];

        foreach ($prompts as $prompt) {
            $classification = $this->intentRouter->classify($prompt);
            $this->assertEquals(
                'reasoning_fallback',
                $classification['route'],
                "Prompt should route to reasoning_fallback for historical memory: {$prompt}"
            );
        }
    }

    public function test_execute_search_conversation_history_with_date_range_only(): void
    {
        $now = Carbon::now($this->timezone);
        $yesterday = (clone $now)->subDay();
        $lastWeek = (clone $now)->subDays(7);

        // Turnos de ayer
        $this->createHistoricalMessage([
            'user_id'    => $this->userId,
            'role'       => 'user',
            'content'    => 'Ayer acordamos revisar los contratos con el abogado.',
            'created_at' => (clone $yesterday)->setTime(10, 30, 0),
            'status'     => 'completed',
        ]);
        $this->createHistoricalMessage([
            'user_id'    => $this->userId,
            'role'       => 'assistant',
            'content'    => 'Entendido, lo agendé para revisión con el área legal.',
            'created_at' => (clone $yesterday)->setTime(10, 30, 15),
            'status'     => 'completed',
        ]);

        // Turno de la semana pasada (fuera del rango de ayer)
        $this->createHistoricalMessage([
            'user_id'    => $this->userId,
            'role'       => 'user',
            'content'    => 'Comprar boletos para el cine la semana pasada.',
            'created_at' => (clone $lastWeek)->setTime(15, 0, 0),
            'status'     => 'completed',
        ]);

        $startIso = (clone $yesterday)->startOfDay()->toIso8601String();
        $endIso = (clone $yesterday)->endOfDay()->toIso8601String();

        $result = $this->service->executeSearchConversationHistory($this->user, [
            'start_date' => $startIso,
            'end_date'   => $endIso,
        ]);

        $this->assertTrue($result['success']);
        $text = $result['formatted_text'] ?? $result['data'] ?? '';
        $this->assertStringContainsString('contratos con el abogado', $text);
        $this->assertStringContainsString('área legal', $text);
        $this->assertStringNotContainsString('boletos para el cine', $text);
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $text);
    }

    public function test_execute_search_conversation_history_with_keyword_and_relevance(): void
    {
        $now = Carbon::now($this->timezone);

        $this->createHistoricalMessage([
            'user_id'    => $this->userId,
            'role'       => 'user',
            'content'    => 'Hablemos de la arquitectura del microservicio de pagos',
            'created_at' => (clone $now)->subDays(2),
            'status'     => 'completed',
        ]);
        $this->createHistoricalMessage([
            'user_id'    => $this->userId,
            'role'       => 'user',
            'content'    => 'La receta del pastel de zanahoria lleva canela',
            'created_at' => (clone $now)->subDays(3),
            'status'     => 'completed',
        ]);

        $result = $this->service->executeSearchConversationHistory($this->user, [
            'query' => 'microservicio pagos',
        ]);

        $this->assertTrue($result['success']);
        $text = $result['formatted_text'] ?? $result['data'] ?? '';
        $this->assertStringContainsString('arquitectura del microservicio de pagos', $text);
        $this->assertStringNotContainsString('pastel de zanahoria', $text);
    }

    public function test_execute_search_conversation_history_returns_clean_honest_message_when_no_records(): void
    {
        $result = $this->service->executeSearchConversationHistory($this->user, [
            'query' => 'un_termino_totalmente_inexistente_xyz_99',
        ]);

        $this->assertTrue($result['success']);
        $text = $result['formatted_text'] ?? $result['data'] ?? '';
        $this->assertStringContainsString('No se encontraron mensajes', $text);
    }

    public function test_execute_search_conversation_history_enforces_compact_limit(): void
    {
        $now = Carbon::now($this->timezone);

        // Crear 15 turnos para el usuario
        for ($i = 1; $i <= 15; $i++) {
            AssistantMessage::create([
                'user_id'    => $this->userId,
                'role'       => $i % 2 === 0 ? 'assistant' : 'user',
                'content'    => "Mensaje de prueba número {$i} sobre finanzas y presupuesto",
                'created_at' => (clone $now)->subHours(20 - $i),
                'status'     => 'completed',
            ]);
        }

        $result = $this->service->executeSearchConversationHistory($this->user, [
            'query' => 'finanzas presupuesto',
        ]);

        $this->assertTrue($result['success']);
        $records = $result['records'] ?? [];
        $this->assertLessThanOrEqual(10, count($records), 'Delivery must be restricted to between 5 and 10 turns');
        $this->assertGreaterThanOrEqual(5, count($records));
    }
}

