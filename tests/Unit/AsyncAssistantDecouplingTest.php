<?php

namespace Tests\Unit;

use App\Events\AssistantMessageProcessed;
use App\Jobs\ProcessAssistantMessage;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\AssistantOrchestratorService;
use App\Services\Whatsapp\EvolutionApiService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AsyncAssistantDecouplingTest extends TestCase
{
    public function test_process_assistant_message_job_configuration(): void
    {
        $job = new ProcessAssistantMessage('test-id-123');

        $this->assertEquals(5, $job->tries);
        $this->assertEquals([2, 10, 30, 60], $job->backoff);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_process_assistant_message_executes_and_updates_status(): void
    {
        Event::fake([AssistantMessageProcessed::class]);

        $user = new User();
        $user->name = 'Johan Test';
        $user->email = 'johan_' . uniqid() . '@test.com';
        $user->password = bcrypt('secret');
        $user->save();

        $message = AssistantMessage::create([
            'user_id'     => (string) $user->id,
            'channel'     => 'app',
            'direction'   => 'inbound',
            'prompt'      => 'hola estas ahi cami?',
            'status'      => 'received',
            'retry_count' => 0,
        ]);

        $orchestrator = app(AssistantOrchestratorService::class);
        $evolution    = app(EvolutionApiService::class);

        $job = new ProcessAssistantMessage((string) $message->id);
        $job->handle($orchestrator, $evolution);

        $refreshed = AssistantMessage::find($message->id);

        $this->assertNotNull($refreshed);
        $this->assertEquals('completed', $refreshed->status);
        $this->assertNotEmpty($refreshed->reply);
        $this->assertEquals('conversation', $refreshed->action);

        Event::assertDispatched(AssistantMessageProcessed::class);

        // Clean up
        $message->delete();
        $user->delete();
    }
}
