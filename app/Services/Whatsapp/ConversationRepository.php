<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappMessage;
use Illuminate\Support\Collection;

class ConversationRepository
{
    public function recent(string $instanceName, string $userPhone, ?int $limit = null): Collection
    {
        $limit = $limit ?? (int) config('services.whatsapp.history_limit', 15);

        // _id de Mongo es monotónico: evita desorden cuando created_at coincide al segundo.
        return WhatsappMessage::query()
            ->where('instance_name', $instanceName)
            ->where('user_phone', $userPhone)
            ->orderBy('_id', 'desc')
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
     * Historial corto para DeepSeek: solo turnos user/assistant (sin tools).
     * El estado de reserva va en el system prompt, no en 15 mensajes viejos.
     *
     * @return list<array<string, mixed>>
     */
    public function toDeepSeekMessages(string $instanceName, string $userPhone, string $systemPrompt): array
    {
        $maxTurns = max(1, (int) config('services.whatsapp.history_turns', 3));
        $fetchLimit = max(
            $maxTurns * 4,
            (int) config('services.whatsapp.history_limit', 12),
        );

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        $turns = [];
        foreach ($this->recent($instanceName, $userPhone, $fetchLimit) as $row) {
            $role = (string) $row->role;
            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $content = trim((string) ($row->content ?? ''));
            // Omitir assistants vacíos que solo tenían tool_calls.
            if ($role === 'assistant' && $content === '' && ! empty($row->metadata['tool_calls'])) {
                continue;
            }
            if ($content === '') {
                continue;
            }

            $turns[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        // Conservar solo los últimos N mensajes de usuario (+ assistants entre medias).
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

    public function clearThread(string $instanceName, string $userPhone): int
    {
        $phone = preg_replace('/\D+/', '', $userPhone) ?? $userPhone;

        return (int) WhatsappMessage::query()
            ->where('instance_name', $instanceName)
            ->where('user_phone', $phone)
            ->delete();
    }

    public function clearInstance(string $instanceName): int
    {
        return (int) WhatsappMessage::query()
            ->where('instance_name', $instanceName)
            ->delete();
    }
}
