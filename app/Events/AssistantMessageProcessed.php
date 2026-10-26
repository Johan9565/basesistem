<?php

namespace App\Events;

use App\Models\AssistantMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssistantMessageProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AssistantMessage $message)
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $userId = (string) $this->message->user_id;

        return [
            new PrivateChannel('assistant.' . $userId),
            new Channel('public.assistant.' . $userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'assistant.message.processed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id'       => (string) ($this->message->id ?? $this->message->_id),
            'status'   => $this->message->status,
            'prompt'   => $this->message->prompt,
            'reply'    => $this->message->reply,
            'action'   => $this->message->action,
            'error'    => $this->message->error_message,
        ];
    }
}
