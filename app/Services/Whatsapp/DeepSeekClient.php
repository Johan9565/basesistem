<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekClient
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    public function chat(array $messages, bool $withTools = true): array
    {
        $apiKey = (string) config('services.deepseek.key');
        $baseUrl = rtrim((string) config('services.deepseek.base_url'), '/');
        $model = (string) config('services.deepseek.model', 'deepseek-chat');

        if ($apiKey === '') {
            throw new RuntimeException('DEEPSEEK_API_KEY no está configurada.');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if ($withTools) {
            $payload['tools'] = [$this->calendarToolDefinition()];
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->acceptJson()
                ->timeout(60)
                ->post('/chat/completions', $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new RuntimeException('Error al llamar a DeepSeek: '.$e->getMessage(), 0, $e);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function calendarToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_calendar_event',
                'description' => 'Crea una cita en Google Calendar con asunto y horario de inicio/fin.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'summary' => [
                            'type' => 'string',
                            'description' => 'Asunto o título de la cita.',
                        ],
                        'start_iso' => [
                            'type' => 'string',
                            'description' => 'Inicio en ISO 8601 (ej. 2026-09-19T10:00:00).',
                        ],
                        'end_iso' => [
                            'type' => 'string',
                            'description' => 'Fin en ISO 8601 (ej. 2026-09-19T10:30:00). Si no se indica duración, usa 30 minutos.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Notas opcionales de la cita.',
                        ],
                    ],
                    'required' => ['summary', 'start_iso', 'end_iso'],
                ],
            ],
        ];
    }
}
