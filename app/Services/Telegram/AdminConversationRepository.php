<?php

namespace App\Services\Telegram;

use App\Models\TelegramAdminMessage;
use Illuminate\Support\Collection;

class AdminConversationRepository
{
    public function recent(string $instanceName, int $chatId, ?int $limit = null): Collection
    {
        $limit = $limit ?? (int) config('services.telegram.history_limit', 12);

        return TelegramAdminMessage::query()
            ->where('instance_name', $instanceName)
            ->where('chat_id', $chatId)
            ->orderBy('_id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function append(
        string $instanceName,
        int $chatId,
        string $role,
        string $content,
        ?string $toolCallId = null,
        ?string $toolName = null,
        ?array $metadata = null,
    ): TelegramAdminMessage {
        return TelegramAdminMessage::query()->create([
            'instance_name' => $instanceName,
            'chat_id' => $chatId,
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
    public function toLlmMessages(
        string $instanceName,
        int $chatId,
        string $staticSystemPrompt,
        string $dynamicSystemPrompt,
    ): array {
        $maxTurns = max(1, (int) config('services.telegram.history_turns', 4));
        $fetchLimit = max(
            $maxTurns * 4,
            (int) config('services.telegram.history_limit', 12),
        );

        $messages = [
            ['role' => 'system', 'content' => $staticSystemPrompt],
        ];

        if (trim($dynamicSystemPrompt) !== '') {
            $messages[] = ['role' => 'system', 'content' => $dynamicSystemPrompt];
        }

        $turns = [];
        foreach ($this->recent($instanceName, $chatId, $fetchLimit) as $row) {
            $role = (string) $row->role;
            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $content = trim((string) ($row->content ?? ''));
            if ($role === 'assistant' && $content === '' && ! empty($row->metadata['tool_calls'])) {
                continue;
            }
            if ($content === '') {
                continue;
            }

            $turns[] = ['role' => $role, 'content' => $content];
        }

        $userIndexes = [];
        foreach ($turns as $i => $t) {
            if ($t['role'] === 'user') {
                $userIndexes[] = $i;
            }
        }

        if (count($userIndexes) > $maxTurns) {
            $startIdx = $userIndexes[count($userIndexes) - $maxTurns];
            $turns = array_slice($turns, $startIdx);
        }

        return array_merge($messages, $turns);
    }
}
