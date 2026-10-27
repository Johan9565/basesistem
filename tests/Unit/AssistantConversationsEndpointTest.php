<?php

namespace Tests\Unit;

use App\Models\AssistantMessage;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantConversationsEndpointTest extends TestCase
{
    protected function tearDown(): void
    {
        AssistantMessage::where('prompt', 'like', '%CONV_TEST%')->delete();
        User::where('email', 'like', 'conv_test_%@example.com')->delete();
        parent::tearDown();
    }

    private function createTestUser(string $prefix = 'user1'): User
    {
        $user = new User();
        $user->name = 'Conv Tester ' . $prefix;
        $user->email = 'conv_test_' . $prefix . '_' . uniqid() . '@example.com';
        $user->password = bcrypt('secret');
        $user->save();
        return $user;
    }

    public function test_conversations_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/assistant/conversations');
        $response->assertStatus(401);
    }

    public function test_user_can_retrieve_their_conversations(): void
    {
        $user = $this->createTestUser('main');
        $otherUser = $this->createTestUser('other');

        // User messages
        AssistantMessage::create([
            'user_id'   => (string) $user->id,
            'channel'   => 'app',
            'direction' => 'inbound',
            'prompt'    => 'CONV_TEST Hola asistente',
            'reply'     => 'Hola, ¿en qué te puedo ayudar hoy?',
            'action'    => 'conversation',
            'status'    => 'completed',
        ]);

        AssistantMessage::create([
            'user_id'   => (string) $user->id,
            'channel'   => 'whatsapp',
            'direction' => 'inbound',
            'prompt'    => 'CONV_TEST Agendar cita mañana',
            'reply'     => 'Cita agendada exitosamente',
            'action'    => 'create_calendar_event',
            'status'    => 'completed',
        ]);

        // Other user message
        AssistantMessage::create([
            'user_id'   => (string) $otherUser->id,
            'channel'   => 'app',
            'direction' => 'inbound',
            'prompt'    => 'CONV_TEST Mensaje de otro usuario',
            'reply'     => 'Respuesta ajena',
            'action'    => 'conversation',
            'status'    => 'completed',
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/assistant/conversations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'conversations');

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Verify isolation: other user's message is not present
        $prompts = collect($data)->pluck('prompt')->all();
        $this->assertContains('CONV_TEST Hola asistente', $prompts);
        $this->assertContains('CONV_TEST Agendar cita mañana', $prompts);
        $this->assertNotContains('CONV_TEST Mensaje de otro usuario', $prompts);
    }

    public function test_user_can_filter_conversations_by_channel(): void
    {
        $user = $this->createTestUser('filter');

        AssistantMessage::create([
            'user_id'   => (string) $user->id,
            'channel'   => 'app',
            'direction' => 'inbound',
            'prompt'    => 'CONV_TEST Web interaction',
            'reply'     => 'Web response',
            'action'    => 'conversation',
            'status'    => 'completed',
        ]);

        AssistantMessage::create([
            'user_id'   => (string) $user->id,
            'channel'   => 'whatsapp',
            'direction' => 'inbound',
            'prompt'    => 'CONV_TEST WhatsApp interaction',
            'reply'     => 'WhatsApp response',
            'action'    => 'conversation',
            'status'    => 'completed',
        ]);

        Sanctum::actingAs($user, ['*']);

        // Filter whatsapp only
        $response = $this->getJson('/api/v1/assistant/conversations?channel=whatsapp');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('whatsapp', $data[0]['channel']);
        $this->assertEquals('CONV_TEST WhatsApp interaction', $data[0]['prompt']);

        // Filter app only
        $responseApp = $this->getJson('/api/v1/assistant/conversations?channel=app');
        $responseApp->assertOk();
        $dataApp = $responseApp->json('data');
        $this->assertCount(1, $dataApp);
        $this->assertEquals('app', $dataApp[0]['channel']);
        $this->assertEquals('CONV_TEST Web interaction', $dataApp[0]['prompt']);
    }

    public function test_user_can_search_conversations(): void
    {
        $user = $this->createTestUser('search');

        AssistantMessage::create([
            'user_id'   => (string) $user->id,
            'channel'   => 'app',
            'direction' => 'inbound',
            'prompt'    => 'CONV_TEST Buscar dentista en Cancun',
            'reply'     => 'Cita con el dentista confirmada',
            'action'    => 'conversation',
            'status'    => 'completed',
        ]);

        AssistantMessage::create([
            'user_id'   => (string) $user->id,
            'channel'   => 'whatsapp',
            'direction' => 'inbound',
            'prompt'    => 'CONV_TEST Comprar leche',
            'reply'     => 'Recordatorio creado para comprar leche',
            'action'    => 'create_reminder',
            'status'    => 'completed',
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/assistant/conversations?search=dentista');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('dentista', $data[0]['prompt']);
    }
}
