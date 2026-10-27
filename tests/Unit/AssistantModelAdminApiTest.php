<?php

namespace Tests\Unit;

use App\Models\AssistantAiModel;
use App\Models\User;
use App\Services\Assistant\ModelRouterService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantModelAdminApiTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        AssistantAiModel::truncate();
        $this->user = new User();
        $this->user->id = 'admin_user_' . uniqid();
        $this->user->name = 'Admin User';
    }

    public function test_admin_can_list_models(): void
    {
        AssistantAiModel::create([
            'alias'       => 'Test Model List',
            'role'        => 'write_intent',
            'base_url'    => 'https://api.deepseek.com',
            'api_key'     => 'sk-1234567890abcdef',
            'model'       => 'deepseek-chat',
            'temperature' => 0.2,
            'status'      => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/assistant/admin/models');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'alias',
                    'role',
                    'base_url',
                    'model',
                    'masked_api_key',
                    'status',
                ],
            ],
        ]);
        $response->assertJsonFragment(['alias' => 'Test Model List']);
        // Verify raw secret key is NOT leaked in API response
        $this->assertStringNotContainsString('sk-1234567890abcdef', $response->getContent());
    }

    public function test_admin_can_create_and_activate_model(): void
    {
        $payload = [
            'alias'       => 'Ruta Rápida MiMo',
            'role'        => 'read_intent',
            'base_url'    => 'https://api.mimo.ai/v1',
            'api_key'     => 'sk-mimo-secret-token-xyz',
            'model'       => 'mimo-v2.6-pro-ultraspeed',
            'temperature' => 0.5,
            'timeout_ms'  => 10000,
            'status'      => 'active',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/assistant/admin/models', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
        ]);

        $created = AssistantAiModel::where('alias', 'Ruta Rápida MiMo')->first();
        $this->assertNotNull($created);
        $this->assertEquals('read_intent', $created->role);
        $this->assertEquals('active', $created->status);
        $this->assertEquals('sk-mimo-secret-token-xyz', $created->api_key);

        // Check that ModelRouterService resolves this newly created model
        $router = app(ModelRouterService::class);
        $activeConfig = $router->getActiveModelConfig('read_intent');
        $this->assertEquals('Ruta Rápida MiMo', $activeConfig['alias']);
    }

    public function test_admin_can_test_model_connectivity(): void
    {
        Http::fake([
            'https://api.test-provider.com/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'pong']],
                ],
            ], 200),
        ]);

        $payload = [
            'base_url'   => 'https://api.test-provider.com',
            'api_key'    => 'sk-test-fake',
            'model'      => 'test-model-turbo',
            'timeout_ms' => 5000,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/assistant/admin/models/test', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertArrayHasKey('latency_ms', $response->json('data'));
    }
}
