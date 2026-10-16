<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\PermissionCacheService;
use App\Services\Whatsapp\AppointmentOrchestrator;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardModulesAndChatTestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCacheService::class)->invalidateAll();
    }

    public function test_dashboard_disables_modules_when_company_has_no_active_modules(): void
    {
        $role = RoleModel::create([
            'name' => 'Admin ' . uniqid(),
            'role' => 'admin_' . uniqid(),
            'status' => 1,
            'permissions' => ['administration', 'companies'],
        ]);

        $user = User::create([
            'name' => 'Usuario Test',
            'email' => 'dash_test_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        // Empresa con TODOS los módulos desactivados
        $company = Company::create([
            'user_id' => (string) $user->_id,
            'name' => 'Empresa Sin Modulos ' . uniqid(),
            'slug' => 'empresa-sin-modulos-' . uniqid(),
            'status' => 'active',
            'modules' => [
                'inventory' => false,
                'services' => false,
                'appointments' => false,
                'whatsapp' => false,
            ],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->get('/dashboard');

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.whatsapp.enabled', false)
            ->where('stats.appointments.enabled', false)
            ->where('stats.inventory.enabled', false)
            ->where('stats.services.enabled', false)
            ->where('stats.has_active_company', true)
        );
    }

    public function test_dashboard_enables_only_active_modules(): void
    {
        $role = RoleModel::create([
            'name' => 'Admin ' . uniqid(),
            'role' => 'admin_' . uniqid(),
            'status' => 1,
            'permissions' => ['administration', 'companies'],
        ]);

        $user = User::create([
            'name' => 'Usuario Test Parcial',
            'email' => 'dash_partial_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        // Empresa con SOLO inventario y whatsapp activados
        $company = Company::create([
            'user_id' => (string) $user->_id,
            'name' => 'Empresa Solo Stock ' . uniqid(),
            'slug' => 'empresa-solo-stock-' . uniqid(),
            'status' => 'active',
            'modules' => [
                'inventory' => true,
                'services' => false,
                'appointments' => false,
                'whatsapp' => true,
            ],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->get('/dashboard');

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.whatsapp.enabled', true)
            ->where('stats.inventory.enabled', true)
            ->where('stats.appointments.enabled', false)
            ->where('stats.services.enabled', false)
        );
    }

    public function test_chat_test_page_renders_successfully(): void
    {
        $role = RoleModel::create([
            'name' => 'Admin Chat Test ' . uniqid(),
            'role' => 'admin_ct_' . uniqid(),
            'status' => 1,
            'permissions' => ['whatsapp', 'whatsapp.chat-test', 'whatsapp.chat-test.view', 'whatsapp.chat-test.send', 'whatsapp.chat-test.reset'],
        ]);

        $user = User::create([
            'name' => 'Usuario Chat Test',
            'email' => 'user_ct_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'user_id' => (string) $user->_id,
            'name' => 'Empresa Bot ' . uniqid(),
            'slug' => 'empresa-bot-' . uniqid(),
            'status' => 'active',
            'modules' => [
                'whatsapp' => true,
            ],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        WhatsappInstance::create([
            'company_id' => (string) $company->_id,
            'instance_name' => 'bot_clinica_' . uniqid(),
            'evolution_instance_name' => 'evo_' . uniqid(),
            'business_name' => 'Clínica Dental Test',
            'status' => 'active',
            'ai_provider' => 'deepseek',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->get('/whatsapp/chat-test');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Whatsapp/ChatTest')
            ->has('instances')
            ->has('selectedInstance')
            ->has('defaultPhone')
        );
    }

    public function test_chat_test_reset_endpoint_clears_simulated_session(): void
    {
        $role = RoleModel::create([
            'name' => 'Admin Chat Test ' . uniqid(),
            'role' => 'admin_ct2_' . uniqid(),
            'status' => 1,
            'permissions' => ['whatsapp', 'whatsapp.chat-test', 'whatsapp.chat-test.reset'],
        ]);

        $user = User::create([
            'name' => 'Usuario Reset Test',
            'email' => 'reset_ct_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'user_id' => (string) $user->_id,
            'name' => 'Empresa Reset ' . uniqid(),
            'slug' => 'empresa-reset-' . uniqid(),
            'status' => 'active',
            'modules' => ['whatsapp' => true],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        $instance = WhatsappInstance::create([
            'company_id' => (string) $company->_id,
            'instance_name' => 'bot_reset_' . uniqid(),
            'evolution_instance_name' => 'evo_reset_' . uniqid(),
            'status' => 'active',
        ]);

        // Insertar un mensaje simulado previo
        $sessionKey = 'sim:' . $instance->evolutionName();
        $phone = '5219990000000';

        WhatsappMessage::create([
            'instance_name' => $sessionKey,
            'user_phone' => $phone,
            'role' => 'user',
            'content' => 'Mensaje de prueba anterior',
        ]);

        $this->assertEquals(1, WhatsappMessage::where('instance_name', $sessionKey)->where('user_phone', $phone)->count());

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->postJson('/whatsapp/chat-test/reset', [
                'instance_id' => (string) $instance->_id,
                'phone' => '+52 999 000 0000',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        // Verificar que el mensaje simulado fue eliminado
        $this->assertEquals(0, WhatsappMessage::where('instance_name', $sessionKey)->where('user_phone', $phone)->count());
    }

    public function test_chat_test_send_calls_simulation_without_sending_whatsapp(): void
    {
        $role = RoleModel::create([
            'name' => 'Admin Chat Test ' . uniqid(),
            'role' => 'admin_ct3_' . uniqid(),
            'status' => 1,
            'permissions' => ['whatsapp', 'whatsapp.chat-test', 'whatsapp.chat-test.send'],
        ]);

        $user = User::create([
            'name' => 'Usuario Send Test',
            'email' => 'send_ct_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'user_id' => (string) $user->_id,
            'name' => 'Empresa Send ' . uniqid(),
            'slug' => 'empresa-send-' . uniqid(),
            'status' => 'active',
            'modules' => ['whatsapp' => true],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        $instance = WhatsappInstance::create([
            'company_id' => (string) $company->_id,
            'instance_name' => 'bot_send_' . uniqid(),
            'evolution_instance_name' => 'evo_send_' . uniqid(),
            'business_name' => 'Negocio Test',
            'status' => 'active',
        ]);

        // Mockear AppointmentOrchestrator para verificar que simulateIncoming es invocado y no handleIncoming ni Evolution
        $orchestratorMock = $this->createMock(AppointmentOrchestrator::class);
        $orchestratorMock->expects($this->once())
            ->method('simulateIncoming')
            ->willReturn([
                'reply' => '¡Hola! Soy tu asistente de prueba.',
                'tool_calls' => [
                    ['name' => 'query_company_services', 'arguments' => '{}', 'result' => []]
                ],
                'booking_state' => ['step' => 'GREETING'],
                'provider' => 'deepseek',
                'static_prompt' => 'Reglas base',
                'dynamic_prompt' => 'Contexto',
                'calendar_ok' => false,
            ]);

        $this->app->instance(AppointmentOrchestrator::class, $orchestratorMock);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->postJson('/whatsapp/chat-test/send', [
                'instance_id' => (string) $instance->_id,
                'phone' => '+52 999 000 0000',
                'message' => 'Hola, ¿qué servicios tienen?',
                'provider' => 'deepseek',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'reply' => '¡Hola! Soy tu asistente de prueba.',
            'provider' => 'deepseek',
        ]);

        $this->app->forgetInstance(AppointmentOrchestrator::class);
    }

    public function test_chat_test_conversations_endpoint_returns_threads_list(): void
    {
        $this->app->forgetInstance(AppointmentOrchestrator::class);
        $role = RoleModel::create([
            'name' => 'Admin Chat Test Conv ' . uniqid(),
            'role' => 'admin_ct_conv_' . uniqid(),
            'status' => 1,
            'permissions' => ['whatsapp', 'whatsapp.chat-test', 'whatsapp.chat-test.view'],
        ]);

        $user = User::create([
            'name' => 'Usuario Conv Test',
            'email' => 'conv_ct_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'user_id' => (string) $user->_id,
            'name' => 'Empresa Conv ' . uniqid(),
            'slug' => 'empresa-conv-' . uniqid(),
            'status' => 'active',
            'modules' => ['whatsapp' => true],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        $instance = WhatsappInstance::create([
            'company_id' => (string) $company->_id,
            'instance_name' => 'bot_conv_' . uniqid(),
            'evolution_instance_name' => 'evo_conv_' . uniqid(),
            'business_name' => 'Negocio Conv',
            'status' => 'active',
        ]);

        $simKey = 'sim:' . $instance->evolutionName();

        // Crear mensajes de 2 hilos simulados distintos
        WhatsappMessage::create([
            'instance_name' => $simKey,
            'user_phone' => '5219983334455',
            'role' => 'user',
            'content' => 'Hola quiero información',
        ]);
        WhatsappMessage::create([
            'instance_name' => $simKey,
            'user_phone' => '5219983334455',
            'role' => 'assistant',
            'content' => 'Claro, con gusto te ayudo.',
        ]);

        WhatsappMessage::create([
            'instance_name' => $simKey,
            'user_phone' => '5219987778899',
            'role' => 'user',
            'content' => '¿Tienen entrega a domicilio?',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->getJson('/whatsapp/chat-test/conversations?instance_id=' . (string) $instance->_id);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
        $data = $response->json('conversations');

        $this->assertCount(2, $data);
        $phones = array_column($data, 'phone');
        $this->assertContains('5219983334455', $phones);
        $this->assertContains('5219987778899', $phones);

        // Probar endpoint de historial individual
        $histResponse = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->getJson('/whatsapp/chat-test/history?instance_id=' . (string) $instance->_id . '&phone=5219983334455&source=sim');

        $histResponse->assertStatus(200);
        $histResponse->assertJson(['ok' => true]);
        $this->assertCount(2, $histResponse->json('history'));
    }
}
