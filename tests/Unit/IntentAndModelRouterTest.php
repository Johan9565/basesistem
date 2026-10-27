<?php

namespace Tests\Unit;

use App\Models\AssistantAiModel;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WorkspaceNote;
use App\Services\Assistant\AssistantOrchestratorService;
use App\Services\Assistant\IntentRouterService;
use App\Services\Assistant\ModelRouterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntentAndModelRouterTest extends TestCase
{
    protected IntentRouterService $intentRouter;
    protected ModelRouterService $modelRouter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intentRouter = app(IntentRouterService::class);
        $this->modelRouter = app(ModelRouterService::class);
    }

    public function test_intent_router_classifies_read_queries(): void
    {
        $readPrompts = [
            "hola cami que pendientes Tengo",
            "¿Cuáles son mis recordatorios para hoy?",
            "¿Qué eventos tengo agendados hoy?",
            "Hola, buenos días Cami",
            "Busca mis notas de desarrollo",
            "Dime mis tareas pendientes",
            "Resumen del día",
            "¿Qué tengo programado para mañana?",
        ];

        foreach ($readPrompts as $prompt) {
            $classification = $this->intentRouter->classify($prompt);
            $this->assertEquals(
                "read_intent",
                $classification["route"],
                "Failed asserting prompt is read_intent: {$prompt}"
            );
        }
    }

    public function test_intent_router_classifies_write_queries(): void
    {
        $writePrompts = [
            "Recordarme enviar reporte mañana a las 9 AM",
            "Crear tarea: revisar logs del servidor",
            "Anotar nota técnica sobre WebGPU y Shaders",
            "Guarda esto: comprar 1 kg de tomate",
            "Tengo que pagar el recibo de luz antes del viernes",
            "Nueva nota: Graphify grafo de conocimiento persistente",
            "Agrega a mis pendientes llamar al cliente a las 4pm",
        ];

        foreach ($writePrompts as $prompt) {
            $classification = $this->intentRouter->classify($prompt);
            $this->assertEquals(
                "write_intent",
                $classification["route"],
                "Failed asserting prompt is write_intent: {$prompt}"
            );
        }
    }

    public function test_intent_router_fallback_for_ambiguous_requests(): void
    {
        $ambiguousPrompts = [
            "¿Por qué el cielo es azul y qué opinas de la gravedad cuántica?",
            "Revisa mi agenda de hoy y si hay espacio agrega una reunión y mándale un correo a Pedro",
            "Analiza el siguiente código y refactorízalo",
        ];

        foreach ($ambiguousPrompts as $prompt) {
            $classification = $this->intentRouter->classify($prompt);
            $this->assertContains(
                $classification["route"],
                ["reasoning_fallback", "write_intent"],
                "Failed ambiguous classification: {$prompt}"
            );
        }
    }

    public function test_assistant_ai_model_encrypts_and_decrypts_api_key(): void
    {
        $rawKey = "sk-test-secret-key-123456";
        $model = new AssistantAiModel([
            "alias"       => "Ruta Rápida - TTS",
            "role"        => "read_intent",
            "base_url"    => "https://api.mimo.ai/v1",
            "api_key"     => $rawKey,
            "model"       => "mimo-v2.6-pro-ultraspeed",
            "temperature" => 0.5,
            "timeout_ms"  => 15000,
            "status"      => "active",
        ]);

        // Raw encrypted value stored in attributes
        $encryptedInDb = $model->getAttributes()["api_key"] ?? null;
        $this->assertNotEmpty($encryptedInDb);
        $this->assertNotEquals($rawKey, $encryptedInDb);

        // Accessor returns decrypted key
        $this->assertEquals($rawKey, $model->api_key);

        // Masked key method for UI security
        $this->assertEquals("sk-...3456", $model->getMaskedApiKey());
    }

    public function test_model_router_falls_back_to_env_when_no_model_configured(): void
    {
        Cache::flush();

        $activeModel = $this->modelRouter->getActiveModelConfig("write_intent");

        $this->assertIsArray($activeModel);
        $this->assertArrayHasKey("base_url", $activeModel);
        $this->assertArrayHasKey("api_key", $activeModel);
        $this->assertArrayHasKey("model", $activeModel);
        $this->assertNotEmpty($activeModel["base_url"]);
    }

    public function test_model_router_hot_reload_clears_cache_on_model_save(): void
    {
        Cache::flush();

        $cacheKey = "assistant:model_router:read_intent";
        Cache::put($cacheKey, ["model" => "cached-old-model"], 3600);
        $this->assertTrue(Cache::has($cacheKey));

        // When hot reload is invoked
        $this->modelRouter->invalidateCache();

        $this->assertFalse(Cache::has($cacheKey));
    }
}
