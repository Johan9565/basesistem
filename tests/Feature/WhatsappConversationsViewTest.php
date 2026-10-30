<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\RoleModel;
use App\Models\User;
use App\Models\WhatsappBookingState;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use Tests\TestCase;

class WhatsappConversationsViewTest extends TestCase
{
    protected User $user;
    protected Company $company;
    protected WhatsappInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $role = RoleModel::create([
            'name' => 'Admin WA ' . uniqid(),
            'role' => 'admin_wa_' . uniqid(),
            'status' => 1,
            'permissions' => ['whatsapp', 'whatsapp.conversations', 'whatsapp.instances'],
        ]);

        $this->user = User::create([
            'name' => 'Admin WhatsApp Test',
            'email' => 'admin_wa_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $this->company = Company::create([
            'user_id' => (string) $this->user->_id,
            'name' => 'Empresa Test ' . uniqid(),
            'slug' => 'empresa-test-' . uniqid(),
            'status' => 'active',
            'modules' => ['whatsapp' => true],
        ]);

        CompanyUser::create([
            'user_id' => (string) $this->user->_id,
            'company_id' => (string) $this->company->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        $this->instance = WhatsappInstance::create([
            'company_id' => (string) $this->company->_id,
            'instance_name' => 'inst_test_' . uniqid(),
            'evolution_instance_name' => 'session_test_' . uniqid(),
            'business_name' => 'Test Business',
            'status' => 'active',
            'ai_provider' => 'deepseek',
        ]);
    }

    /**
     * Verifica que la vista agrupe los chats por teléfono y muestre tanto mensajes entrantes
     * del cliente (inbound) como salientes del bot (outbound), incluso si los mensajes
     * llegaron vía webhook sin company_id asignado previamente.
     */
    public function test_conversations_view_groups_threads_and_shows_inbound_and_outbound_messages(): void
    {
        $phone = '529981112233';
        $sessionKey = $this->instance->evolutionName();

        // 1. Mensaje del cliente que llegó vía webhook (con company_id nulo)
        WhatsappMessage::withoutGlobalScopes()->create([
            'company_id' => null,
            'instance_name' => $sessionKey,
            'user_phone' => $phone,
            'role' => 'user',
            'content' => 'Hola, me gustaría saber el precio de sus termos',
            'created_at' => now()->subMinutes(10),
        ]);

        // 2. Mensaje del bot respondiendo
        WhatsappMessage::withoutGlobalScopes()->create([
            'company_id' => (string) $this->company->_id,
            'instance_name' => $sessionKey,
            'user_phone' => $phone,
            'role' => 'assistant',
            'content' => '¡Hola! Claro que sí, el termo personalizado cuesta $350 MXN.',
            'created_at' => now()->subMinutes(9),
        ]);

        // 3. Mensaje interno de tool (debe marcarse como is_tool)
        WhatsappMessage::withoutGlobalScopes()->create([
            'company_id' => (string) $this->company->_id,
            'instance_name' => $sessionKey,
            'user_phone' => $phone,
            'role' => 'tool',
            'tool_name' => 'update_booking_state',
            'content' => '{"ok":true}',
            'created_at' => now()->subMinutes(8),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_company_id' => (string) $this->company->_id])
            ->get(route('whatsapp.conversations.show', ['instance' => $this->instance->instance_name]));

        $response->assertStatus(200);

        $pageProps = $response->viewData('page')['props'];

        // Verificar que threads agrupa la conversación única
        $this->assertNotEmpty($pageProps['threads']);
        $thread = collect($pageProps['threads'])->firstWhere('user_phone', $phone);
        $this->assertNotNull($thread, 'El thread para el teléfono debe existir en la lista.');
        $this->assertEquals(3, $thread['message_count']);
        $this->assertEquals('assistant', $thread['last_role']);
        $this->assertTrue($thread['is_last_outgoing']);

        // Verificar que la lista de mensajes contiene tanto el mensaje del usuario como el del bot
        $messages = collect($pageProps['messages']);
        $this->assertCount(3, $messages);

        $userMsg = $messages->firstWhere('role', 'user');
        $this->assertNotNull($userMsg, 'El mensaje del cliente no debe ser ignorado ni ocultado por CompanyScope.');
        $this->assertTrue($userMsg['is_incoming']);
        $this->assertFalse($userMsg['is_outgoing']);
        $this->assertEquals('Hola, me gustaría saber el precio de sus termos', $userMsg['content']);

        $botMsg = $messages->firstWhere('role', 'assistant');
        $this->assertNotNull($botMsg, 'El mensaje del bot debe estar presente.');
        $this->assertFalse($botMsg['is_incoming']);
        $this->assertTrue($botMsg['is_outgoing']);
        $this->assertEquals('¡Hola! Claro que sí, el termo personalizado cuesta $350 MXN.', $botMsg['content']);

        $toolMsg = $messages->firstWhere('role', 'tool');
        $this->assertNotNull($toolMsg);
        $this->assertTrue($toolMsg['is_tool']);
    }

    /**
     * Verifica que los hilos se ordenen del más reciente al más antiguo.
     */
    public function test_conversations_threads_are_ordered_by_most_recent_message(): void
    {
        $sessionKey = $this->instance->evolutionName();
        $olderPhone = '529981110001';
        $newerPhone = '529981110002';

        // Conversación más antigua
        $mOld = WhatsappMessage::withoutGlobalScopes()->create([
            'instance_name' => $sessionKey,
            'user_phone' => $olderPhone,
            'role' => 'user',
            'content' => 'Mensaje antiguo',
        ]);
        $mOld->timestamps = false;
        $mOld->created_at = now()->subHours(2);
        $mOld->saveQuietly();

        // Conversación más reciente
        $mNew = WhatsappMessage::withoutGlobalScopes()->create([
            'instance_name' => $sessionKey,
            'user_phone' => $newerPhone,
            'role' => 'user',
            'content' => 'Mensaje reciente',
        ]);
        $mNew->timestamps = false;
        $mNew->created_at = now()->subMinutes(5);
        $mNew->saveQuietly();

        $response = $this->actingAs($this->user)
            ->withSession(['active_company_id' => (string) $this->company->_id])
            ->get(route('whatsapp.conversations.show', ['instance' => $this->instance->instance_name]));

        $response->assertStatus(200);

        $threads = $response->viewData('page')['props']['threads'];
        $this->assertGreaterThanOrEqual(2, count($threads));

        // El primer elemento debe ser el más reciente
        $this->assertEquals($newerPhone, $threads[0]['user_phone']);
        $this->assertEquals($olderPhone, $threads[1]['user_phone']);
    }

    /**
     * Verifica que se pueda reactivar el bot pausado desde la vista.
     */
    public function test_can_resume_bot_for_phone(): void
    {
        $sessionKey = $this->instance->evolutionName();
        $phone = '529983334455';

        $state = WhatsappBookingState::withoutGlobalScopes()->create([
            'company_id' => (string) $this->company->_id,
            'instance_name' => $sessionKey,
            'user_phone' => $phone,
            'step' => 'COLLECTING',
            'bot_paused_at' => now(),
            'escalation_reason' => 'Cliente pidió humano',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_company_id' => (string) $this->company->_id])
            ->post(route('whatsapp.conversations.resume', [
                'instance' => $this->instance->instance_name,
                'phone' => $phone,
            ]));

        $response->assertRedirect();

        $fresh = WhatsappBookingState::withoutGlobalScopes()->find($state->_id);
        $this->assertNull($fresh->bot_paused_at, 'El bot debe haberse reanudado (bot_paused_at en null).');
    }
}
