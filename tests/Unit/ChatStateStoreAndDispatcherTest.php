<?php

namespace Tests\Unit;

use App\Services\Whatsapp\ChatStateStore;
use App\Services\Whatsapp\Contracts\MessengerGatewayInterface;
use App\Services\Whatsapp\MessageDispatcher;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ChatStateStoreAndDispatcherTest extends TestCase
{
    protected ChatStateStore $store;
    protected string $instance = 'instancia-x';
    protected string $chat = '521999888777';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.split_messages' => false,
            'services.whatsapp.human_typing' => false,
        ]);

        $this->store = app(ChatStateStore::class);
    }

    public function test_keys_follow_instance_chat_schema(): void
    {
        $this->assertSame('pausa:instancia-x:521999888777', $this->store->pauseKey($this->instance, $this->chat));
        $this->assertSame('sesion:instancia-x:521999888777', $this->store->sessionKey($this->instance, $this->chat));
        $this->assertSame('buffer:instancia-x:521999888777', $this->store->bufferKey($this->instance, $this->chat));
        $this->assertSame('mensajes_bot:abc123', ChatStateStore::botMessageKey('abc123'));
    }

    public function test_pause_flag_can_be_set_and_cleared(): void
    {
        $this->assertFalse($this->store->isPaused($this->instance, $this->chat));

        $this->store->pause($this->instance, $this->chat, 15);
        $this->assertTrue($this->store->isPaused($this->instance, $this->chat));

        $this->store->clearPause($this->instance, $this->chat);
        $this->assertFalse($this->store->isPaused($this->instance, $this->chat));
    }

    public function test_session_pointer_can_be_stored_renewed_and_cleared(): void
    {
        $this->assertNull($this->store->sessionId($this->instance, $this->chat));

        $this->store->renewSession($this->instance, $this->chat, 'sesion-1', 24);
        $this->assertSame('sesion-1', $this->store->sessionId($this->instance, $this->chat));

        $this->store->renewSession($this->instance, $this->chat, 'sesion-1', 24);
        $this->assertSame('sesion-1', $this->store->sessionId($this->instance, $this->chat));

        $this->store->clearSession($this->instance, $this->chat);
        $this->assertNull($this->store->sessionId($this->instance, $this->chat));
    }

    public function test_bot_message_registry_detects_own_sends(): void
    {
        $this->assertFalse($this->store->isBotMessage('msg-1'));

        $this->store->registerBotMessage('msg-1');
        $this->assertTrue($this->store->isBotMessage('msg-1'));
        $this->assertFalse($this->store->isBotMessage('msg-2'));
    }

    public function test_buffer_accumulates_and_consolidates_when_idle(): void
    {
        $this->store->pushBuffer($this->instance, $this->chat, 'uno', microtime(true) - 10);
        $this->store->pushBuffer($this->instance, $this->chat, 'dos', microtime(true) - 10);

        // Ventana viva: no se consolida todavía
        $this->assertNull($this->store->pullBufferIfIdle($this->instance, $this->chat, 60));

        $texts = $this->store->pullBufferIfIdle($this->instance, $this->chat, 5);
        $this->assertSame(['uno', 'dos'], $texts);

        // El búfer se consume una sola vez
        $this->assertSame([], $this->store->pullBufferIfIdle($this->instance, $this->chat, 5));
    }

    public function test_dispatcher_registers_sent_message_ids_to_prevent_self_pause(): void
    {
        $gateway = Mockery::mock(MessengerGatewayInterface::class);
        $gateway->shouldReceive('sendText')
            ->andReturn(
                ['key' => ['id' => 'id-1']],
                ['data' => ['key' => ['id' => 'id-2']]],
                ['data' => ['id' => 'id-3']],
                ['id' => 'id-4'],
            );

        $dispatcher = new MessageDispatcher($gateway, $this->store);

        $dispatcher->dispatch($this->instance, $this->chat, 'Mensaje del bot');
        $dispatcher->dispatch($this->instance, $this->chat, 'Segundo mensaje');
        $dispatcher->dispatch($this->instance, $this->chat, 'Tercer mensaje');
        $dispatcher->dispatch($this->instance, $this->chat, 'Cuarto mensaje');

        foreach (['id-1', 'id-2', 'id-3', 'id-4'] as $id) {
            $this->assertTrue($this->store->isBotMessage($id), "El id {$id} debía quedar registrado");
        }
    }

    public function test_dispatcher_registers_nothing_when_gateway_returns_no_id(): void
    {
        $gateway = Mockery::mock(MessengerGatewayInterface::class);
        $gateway->shouldReceive('sendText')->andReturn([]);

        $dispatcher = new MessageDispatcher($gateway, $this->store);
        $dispatcher->dispatch($this->instance, $this->chat, 'Sin id');

        $this->assertFalse($this->store->isBotMessage(''));
    }
}
