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
        ?array $metadata = null
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
     * Mensajes para el LLM con prefijo cacheable:
     * 1) system estático (reglas + catálogo)
     * 2) system dinámico (fecha/hora + estado + memoria telegráfica comprimida)
     * 3) historial user/assistant (acotado a turnos recientes para ahorrar tokens)
     *
     * @return list<array<string, mixed>>
     */
    public function toLlmMessages(
        string $instanceName,
        string $userPhone,
        string $staticSystemPrompt,
        string $dynamicSystemPrompt,
        ?string $contextSummary = null
    ): array {
        $maxTurns = max(1, (int) config('services.whatsapp.history_turns', 3));
        $fetchLimit = max(
            $maxTurns * 4,
            (int) config('services.whatsapp.history_limit', 12),
        );

        $messages = [
            [
                'role' => 'system',
                'content' => $staticSystemPrompt,
            ],
        ];

        $dynamicParts = [];
        if (trim($dynamicSystemPrompt) !== '') {
            $dynamicParts[] = trim($dynamicSystemPrompt);
        }
        if (! empty($contextSummary)) {
            $dynamicParts[] = 'MEMORIA TELEGRÁFICA PREVIA: '.trim($contextSummary);
        }

        if ($dynamicParts !== []) {
            $messages[] = [
                'role' => 'system',
                'content' => implode("\n\n", $dynamicParts),
            ];
        }

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

    /**
     * Comprime el historial conversacional previo en formato telegráfico "estilo cavernícola" (ahorro masivo de tokens).
     */
    public function compressHistory(
        string $instanceName,
        string $userPhone,
        $llmClient,
        int $minMessages = 6
    ): ?string {
        $recentMessages = $this->recent($instanceName, $userPhone, 15);
        if ($recentMessages->count() < $minMessages) {
            return null;
        }

        $chatLines = [];
        foreach ($recentMessages as $msg) {
            $r = $msg->role === 'assistant' ? 'Bot' : 'Cliente';
            $c = trim((string) $msg->content);
            if ($c !== '') {
                $chatLines[] = "{$r}: {$c}";
            }
        }

        if (count($chatLines) < $minMessages) {
            return null;
        }

        $rawTranscript = implode("\n", $chatLines);

        $prompt = [
            [
                'role' => 'system',
                'content' => "Eres un extractor de hechos telegráfico estilo cavernícola/ultra-compacto.\n"
                    ."Resume la conversación en HECHOS ATÓMICOS usando estrictamente este formato:\n"
                    ."Cliente: [Nombre] | Interés: [Servicio/Tratamiento] | Rechaza: [Horarios/Precios] | Quiere: [Fecha/Hora deseada] | Estado: [Etapa actual]\n"
                    ."REGLAS:\n"
                    ."- Máximo 30 palabras en total.\n"
                    ."- Sin saludos, sin explicaciones ni introducciones.\n"
                    .'- Omite campos si no existen datos.',
            ],
            [
                'role' => 'user',
                'content' => "Historial a resumir:\n".$rawTranscript,
            ],
        ];

        try {
            $res = $llmClient->chat($prompt, false);
            $summary = trim((string) ($res['choices'][0]['message']['content'] ?? ''));

            return $summary !== '' ? $summary : null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Conversation history compression failed (ignored)', [
                'instance' => $instanceName,
                'phone' => $userPhone,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @deprecated Usar toLlmMessages con system estático + dinámico.
     *
     * @return list<array<string, mixed>>
     */
    public function toDeepSeekMessages(string $instanceName, string $userPhone, string $systemPrompt): array
    {
        return $this->toLlmMessages($instanceName, $userPhone, $systemPrompt, '');
    }

    public function clearThread(string $instanceName, string $userPhone): int
    {
        $phone = preg_replace('/\D+/', '', $userPhone) ?? $userPhone;

        // Borra por teléfono normalizado y por posibles variantes guardadas.
        return (int) WhatsappMessage::query()
            ->where('instance_name', $instanceName)
            ->where(function ($q) use ($phone, $userPhone) {
                $q->where('user_phone', $phone)
                    ->orWhere('user_phone', $userPhone)
                    ->orWhere('user_phone', '+'.$phone);
            })
            ->delete();
    }

    /**
     * Borra mensajes de una sesión y, si se pasan alias, también de esos instance_name.
     *
     * @param  list<string>  $alsoInstanceNames
     */
    public function clearInstance(string $instanceName, array $alsoInstanceNames = []): int
    {
        $names = array_values(array_unique(array_filter(array_merge(
            [$instanceName],
            $alsoInstanceNames,
        ))));

        return (int) WhatsappMessage::query()
            ->whereIn('instance_name', $names)
            ->delete();
    }
}
