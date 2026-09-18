<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappMessage;
use Illuminate\Support\Collection;

class ConversationRepository
{
    public function recent(string $instanceName, string $userPhone, ?int $limit = null): Collection
    {
        $limit = $limit ?? (int) config('services.whatsapp.history_limit', 15);

        return WhatsappMessage::query()
            ->where('instance_name', $instanceName)
            ->where('user_phone', $userPhone)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function append(
        string $instanceName,
        string $userPhone,
        string $role,
        string $content,
        ?string $toolCallId = null,
        ?string $toolName = null,
        ?array $metadata = null,
    ): WhatsappMessage {
        return WhatsappMessage::query()->create([
            'instance_name' => $instanceName,
            'user_phone' => $userPhone,
            'role' => $role,
            'content' => $content,
            'tool_call_id' => $toolCallId,
            'tool_name' => $toolName,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toDeepSeekMessages(string $instanceName, string $userPhone, string $systemPrompt): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        foreach ($this->recent($instanceName, $userPhone) as $row) {
            $message = [
                'role' => $row->role,
                'content' => $row->content ?? '',
            ];

            if ($row->role === 'tool' && $row->tool_call_id) {
                $message['tool_call_id'] = $row->tool_call_id;
            }

            if ($row->role === 'assistant' && ! empty($row->metadata['tool_calls'])) {
                $message['tool_calls'] = $row->metadata['tool_calls'];
            }

            $messages[] = $message;
        }

        return $messages;
    }
}
