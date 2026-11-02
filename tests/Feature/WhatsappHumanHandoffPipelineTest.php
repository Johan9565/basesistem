<?php

namespace Tests\Feature;

use App\Jobs\FlushChatBuffer;
use App\Jobs\ProcessWhatsappMessage;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\RoleModel;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\Whatsapp\AppointmentOrchestrator;
use App\Services\Whatsapp\BookingStateRepository;
use App\Services\Whatsapp\ChatSessionManager;
use App\Services\Whatsapp\ChatStateStore;
use App\Services\Whatsapp\ConversationRepository;
use App\Services\Whatsapp\Contracts\MessengerGatewayInterface;
use App\Services\Whatsapp\DeepSeekClient;
use App\Services\Whatsapp\EvolutionApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Pipeline de handoff humano: pausa por TTL, ciclo de vida de sesión,
 * búfer anti-metralleta y protección contra bucles con mensajes_bot.
 */
class WhatsappHumanHandoffPipelineTest extends TestCase
{
    protected User $user;
    protected Company $company;
    protected WhatsappInstance $instance;
    protected ChatStateStore $store;
    protected string $phone;

    protected function setUp(): void
    {
        parent::setUp();

        // Teléfono único por test: la BD Mongo es compartida entre corridas.
        $this->phone = '52199' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);

        config([
            'services.evolution.webhook_secret' => 'test-secret',
            'services.whatsapp.buffer_window_seconds' => 0,
            'services.whatsapp.tiempo_pausa_humano' => 15,
            'services.whatsapp.tiempo_expiracion_sesion' => 24,
            'services.whatsapp.auto_welcome' => false,
            'services.whatsapp.auto_media_reply' => false,
            'services.whatsapp.human_typing' => false,
            'services.whatsapp.split_messages' => false,
        ]);

        $role = RoleModel::create([
            'name' => 'Admin WA ' . uniqid(),
            'role' => 'admin_wa_' . uniqid(),
            'status' => 1,
            'permissions' => ['whatsapp'],
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

        $this->store = app(ChatStateStore::class);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    protected function sessionKey(): string
    {
        return $this->instance->evolutionName();
    }

    /**
     * @return array<string, mixed>
     */
    protected function incomingMessage(string $text, string $id = 'in-1'): array
    {
        return [
            'key' => [
                'id' => $id,
                'fromMe' => false,
                'remoteJid' => $this->phone . '@s.whatsapp.net',
            ],
            'message' => ['conversation' => $text],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function outgoingMessage(string $text, string $id = 'out-1'): array
    {
        return [
            'key' => [
                'id' => $id,
                'fromMe' => true,
                'remoteJid' => $this->phone . '@s.whatsapp.net',
            ],
            'message' => ['conversation' => $text],
        ];
    }

    protected function postWebhook(array $message)
    {
        return $this->postJson('/api/evolution/webhook', [
            'event' => 'MESSAGES_UPSERT',
            'instance' => $this->sessionKey(),
            'data' => $message,
        ], ['X-Evolution-Secret' => 'test-secret']);
    }

    /**
     * Orquestador con doble estricto: cualquier llamada inesperada falla el test.
     */
    protected function bindOrchestrator(): \Mockery\MockInterface
    {
        $orch = Mockery::mock(AppointmentOrchestrator::class);
        $this->app->instance(AppointmentOrchestrator::class, $orch);

        return $orch;
    }

    protected function runQueuedProcessJobs(): void
    {
        foreach (Queue::pushed(ProcessWhatsappMessage::class) as $job) {
            $job->handle(
                app(ChatStateStore::class),
                app(ChatSessionManager::class),
                app(ConversationRepository::class),
                app(AppointmentOrchestrator::class),
                app(BookingStateRepository::class),
                app(EvolutionApiClient::class),
                app(\App\Services\Whatsapp\AudioTranscriptionService::class),
            );
        }
    }

    protected function runFlushJob(): void
    {
        (new FlushChatBuffer($this->sessionKey(), $this->phone))->handle(
            app(ChatStateStore::class),
            app(AppointmentOrchestrator::class),
        );
    }

    // ---------------------------------------------------------------
    // 1. Filtros de descarte inmediato
    // ---------------------------------------------------------------

    public function test_webhook_ignores_groups_and_returns_200(): void
    {
        $this->bindOrchestrator();

        $group = $this->incomingMessage('hola a todos');
        $group['key']['remoteJid'] = '1203630431@g.us';

        $this->postWebhook($group)->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(0, WhatsappMessage::withoutGlobalScopes()
            ->where('instance_name', $this->sessionKey())
            ->where('user_phone', $this->phone)
            ->count());
        $this->assertNull($this->store->sessionId($this->sessionKey(), $this->phone));
    }

    // ---------------------------------------------------------------
    // 2. Bifurcación de mensajes salientes (fromMe)
    // ---------------------------------------------------------------

    public function test_fromme_message_registered_as_bot_is_discarded(): void
    {
        $orch = $this->bindOrchestrator();
        $orch->shouldNotReceive('respondToBuffered');
        $orch->shouldNotReceive('handleIncoming');

        $this->store->registerBotMessage('echo-1');
        $this->postWebhook($this->outgoingMessage('Respuesta generada por el bot', 'echo-1'))->assertOk();

        $this->assertFalse($this->store->isPaused($this->sessionKey(), $this->phone));
        $this->assertSame(0, WhatsappMessage::withoutGlobalScopes()
            ->where('instance_name', $this->sessionKey())
            ->where('user_phone', $this->phone)
            ->count());
        $this->assertNull($this->store->sessionId($this->sessionKey(), $this->phone));
    }

    public function test_fromme_operator_message_pauses_bot_and_saves_with_session(): void
    {
        $orch = $this->bindOrchestrator();
        $orch->shouldNotReceive('respondToBuffered');

        $this->postWebhook($this->outgoingMessage('Hola, te atiendo yo', 'op-1'))->assertOk();

        // Bandera de pausa activa (TTL = tiempo_pausa_humano)
        $this->assertTrue($this->store->isPaused($this->sessionKey(), $this->phone));

        // Mensaje del operador guardado y vinculado a la sesión
        $msg = WhatsappMessage::withoutGlobalScopes()
            ->where('instance_name', $this->sessionKey())
            ->where('user_phone', $this->phone)
            ->first();
        $this->assertNotNull($msg);
        $this->assertSame('assistant', $msg->role);
        $this->assertSame('operator', $msg->metadata['source'] ?? null);
        $this->assertNotEmpty($msg->session_id);
        $this->assertSame($msg->session_id, $this->store->sessionId($this->sessionKey(), $this->phone));
    }

    public function test_fromme_bot_command_reactivates_ai_immediately(): void
    {
        $orch = $this->bindOrchestrator();
        $orch->shouldNotReceive('respondToBuffered');

        $this->store->pause($this->sessionKey(), $this->phone, 15);
        $this->assertTrue($this->store->isPaused($this->sessionKey(), $this->phone));

        $this->postWebhook($this->outgoingMessage('#bot', 'op-2'))->assertOk();

        $this->assertFalse($this->store->isPaused($this->sessionKey(), $this->phone));
    }

    public function test_fromme_cerrar_command_closes_session_and_clears_pointer(): void
    {
        $orch = $this->bindOrchestrator();
        $orch->shouldReceive('respondToBuffered')->andReturn('¡Hasta luego!');

        // Mensaje entrante crea la sesión activa
        $this->postWebhook($this->incomingMessage('Hola', 'in-1'))->assertOk();
        $sessionId = $this->store->sessionId($this->sessionKey(), $this->phone);
        $this->assertNotEmpty($sessionId);

        $this->postWebhook($this->outgoingMessage('#cerrar', 'op-3'))->assertOk();

        $row = WhatsappSession::withoutGlobalScopes()
            ->where('instance_name', $this->sessionKey())
            ->where('session_id', $sessionId)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(WhatsappSession::STATUS_CERRADA, $row->status);
        $this->assertSame(WhatsappSession::REASON_COMANDO, $row->close_reason);
        $this->assertNull($this->store->sessionId($this->sessionKey(), $this->phone));
    }

    // ---------------------------------------------------------------
    // 3. Bifurcación de mensajes entrantes y ciclo de vida de sesión
    // ---------------------------------------------------------------

    public function test_incoming_while_paused_is_stored_for_audit_without_reply(): void
    {
        $orch = $this->bindOrchestrator();
        $orch->shouldNotReceive('respondToBuffered');
        $orch->shouldNotReceive('handleIncoming');

        $this->store->pause($this->sessionKey(), $this->phone, 15);

        $this->postWebhook($this->incomingMessage('Sigo esperando', 'in-9'))->assertOk();

        $msg = WhatsappMessage::withoutGlobalScopes()
            ->where('instance_name', $this->sessionKey())
            ->where('user_phone', $this->phone)
            ->first();
        $this->assertNotNull($msg);
        $this->assertSame('user', $msg->role);
        $this->assertSame('audit', $msg->metadata['source'] ?? null);
    }

    public function test_incoming_creates_session_and_renews_it_on_each_message(): void
    {
        $orch = $this->bindOrchestrator();
        $orch->shouldReceive('respondToBuffered')->andReturn('¡Hola!');

        $this->postWebhook($this->incomingMessage('Hola', 'in-2'))->assertOk();
        $first = $this->store->sessionId($this->sessionKey(), $this->phone);
        $this->assertNotEmpty($first);

        $this->postWebhook($this->incomingMessage('Quiero un precio', 'in-3'))->assertOk();
        $second = $this->store->sessionId($this->sessionKey(), $this->phone);

        $this->assertSame($first, $second);

        // Una sola conversación activa en BD, ambos mensajes vinculados
        $rows = WhatsappSession::withoutGlobalScopes()
            ->where('instance_name', $this->sessionKey())
            ->where('status', WhatsappSession::STATUS_ACTIVA)
            ->get();
        $this->assertCount(1, $rows);
        $this->assertSame($first, $rows->first()->session_id);

        $messages = WhatsappMessage::withoutGlobalScopes()
            ->where('instance_name', $this->sessionKey())
            ->where('user_phone', $this->phone)
            ->get();
        $this->assertCount(2, $messages);
        foreach ($messages as $msg) {
            $this->assertSame($first, $msg->session_id);
        }
    }

    public function test_expired_session_pointer_closes_old_session_and_opens_new_one(): void
    {
        $orch = $this->bindOrchestrator();
        $orch->shouldReceive('respondToBuffered')->andReturn('¡Hola!');

        $this->postWebhook($this->incomingMessage('Hola', 'in-4'))->assertOk();
        $first = $this->store->sessionId($this->sessionKey(), $this->phone);

        // Simula la expiración natural del TTL del puntero de sesión
        $this->store->clearSession($this->sessionKey(), $this->phone);

        $this->postWebhook($this->incomingMessage('Nueva consulta', 'in-5'))->assertOk();
        $second = $this->store->sessionId($this->sessionKey(), $this->phone);

        $this->assertNotEmpty($second);
        $this->assertNotSame($first, $second);

        $old = WhatsappSession::withoutGlobalScopes()
            ->where('instance_name', $this->sessionKey())
            ->where('session_id', $first)
            ->first();
        $this->assertSame(WhatsappSession::STATUS_CERRADA, $old->status);
        $this->assertSame(WhatsappSession::REASON_INACTIVIDAD, $old->close_reason);

        $new = WhatsappSession::withoutGlobalScopes()
            ->where('instance_name', $this->sessionKey())
            ->where('session_id', $second)
            ->first();
        $this->assertSame(WhatsappSession::STATUS_ACTIVA, $new->status);
    }

    // ---------------------------------------------------------------
    // 4. Contexto acotado a la sesión activa
    // ---------------------------------------------------------------

    public function test_llm_context_only_includes_active_session_messages(): void
    {
        $repo = app(ConversationRepository::class);

        $repo->append($this->sessionKey(), $this->phone, 'user', 'Mensaje de sesión vieja', sessionId: 'sesion-vieja');
        $repo->append($this->sessionKey(), $this->phone, 'assistant', 'Respuesta de sesión vieja', sessionId: 'sesion-vieja');
        $repo->append($this->sessionKey(), $this->phone, 'user', 'Mensaje de sesión activa', sessionId: 'sesion-activa');
        $repo->append($this->sessionKey(), $this->phone, 'assistant', 'Acordamos el jueves', metadata: ['source' => 'operator'], sessionId: 'sesion-activa');

        $messages = $repo->toLlmMessages(
            $this->sessionKey(),
            $this->phone,
            'reglas',
            'estado',
            null,
            'sesion-activa'
        );

        $contents = array_column($messages, 'content');
        $flat = implode("\n", $contents);

        $this->assertStringContainsString('Mensaje de sesión activa', $flat);
        $this->assertStringContainsString('Acordamos el jueves', $flat);
        $this->assertStringNotContainsString('Mensaje de sesión vieja', $flat);
        $this->assertStringNotContainsString('Respuesta de sesión vieja', $flat);
    }

    // ---------------------------------------------------------------
    // 5. Búfer anti-metralleta
    // ---------------------------------------------------------------

    public function test_buffer_consolidates_burst_into_single_ai_call(): void
    {
        Queue::fake();

        $this->postWebhook($this->incomingMessage('Hola', 'in-6'))->assertOk();
        $this->postWebhook($this->incomingMessage('Quiero cotizar 10 termos', 'in-7'))->assertOk();

        // Aún no se procesa nada: los mensajes esperan en el búfer
        $orch = $this->bindOrchestrator();
        $orch->shouldReceive('respondToBuffered')
            ->once()
            ->with($this->sessionKey(), $this->phone, "Hola\nQuiero cotizar 10 termos", Mockery::type('string'))
            ->andReturn('¡Claro!');

        $this->runQueuedProcessJobs();
        $this->runFlushJob();
    }

    public function test_buffer_defers_response_while_window_is_alive(): void
    {
        Queue::fake();
        config(['services.whatsapp.buffer_window_seconds' => 60]);

        $this->postWebhook($this->incomingMessage('Hola', 'in-8'))->assertOk();

        $silent = $this->bindOrchestrator();
        $silent->shouldNotReceive('respondToBuffered');

        $this->runQueuedProcessJobs();
        $this->runFlushJob();

        // El búfer conserva el mensaje a la espera de más
        $items = Cache::get($this->store->bufferKey($this->sessionKey(), $this->phone), []);
        $this->assertCount(1, $items);

        // Tras expirar la ventana, el flush consolidado sí se despacha
        $aged = array_map(fn ($i) => ['text' => $i['text'], 'at' => microtime(true) - 120], $items);
        Cache::put($this->store->bufferKey($this->sessionKey(), $this->phone), $aged, 3600);

        $orch = Mockery::mock(AppointmentOrchestrator::class);
        $orch->shouldReceive('respondToBuffered')
            ->once()
            ->with($this->sessionKey(), $this->phone, 'Hola', Mockery::type('string'))
            ->andReturn('¡Hola!');
        $this->app->instance(AppointmentOrchestrator::class, $orch);

        $this->runFlushJob();
    }

    // ---------------------------------------------------------------
    // 6. Etiqueta de cierre [CONVERSATION_CLOSED]
    // ---------------------------------------------------------------

    public function test_ai_close_tag_is_stripped_before_sending_and_closes_session(): void
    {
        $sent = [];
        $gateway = Mockery::mock(MessengerGatewayInterface::class);
        $gateway->shouldReceive('normalizePhone')
            ->andReturnUsing(fn ($p) => preg_replace('/\D+/', '', (string) $p) ?? (string) $p);
        $gateway->shouldReceive('sendTyping')->andReturnNull();
        $gateway->shouldReceive('sendText')->andReturnUsing(function ($instance, $phone, $text) use (&$sent) {
            $sent[] = $text;

            return ['key' => ['id' => 'bot-99']];
        });
        $this->app->instance(MessengerGatewayInterface::class, $gateway);

        $llm = Mockery::mock(DeepSeekClient::class);
        $llm->shouldReceive('chat')->andReturn([
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['content' => '¡Hasta luego! Que estés muy bien. [CONVERSATION_CLOSED]'],
            ]],
        ]);
        $this->app->instance(DeepSeekClient::class, $llm);

        $this->postWebhook($this->incomingMessage('Gracias, eso es todo', 'in-10'))->assertOk();

        // El texto técnico nunca llega al cliente
        $this->assertNotEmpty($sent);
        $flat = implode("\n", $sent);
        $this->assertStringNotContainsString('CONVERSATION_CLOSED', $flat);
        $this->assertStringContainsString('Hasta luego', $flat);

        // Rutina de cierre de sesión ejecutada
        $row = WhatsappSession::withoutGlobalScopes()
            ->where('instance_name', $this->sessionKey())
            ->first();
        $this->assertSame(WhatsappSession::STATUS_CERRADA, $row->status);
        $this->assertSame(WhatsappSession::REASON_IA, $row->close_reason);
        $this->assertNull($this->store->sessionId($this->sessionKey(), $this->phone));

        // El envío propio quedó registrado para evitar la autopausa
        $this->assertTrue($this->store->isBotMessage('bot-99'));
    }
}
