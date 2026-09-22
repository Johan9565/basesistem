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
            'max_tokens' => (int) config('services.deepseek.max_tokens', 150),
            'temperature' => (float) config('services.deepseek.temperature', 0.25),
        ];

        $stop = config('services.deepseek.stop', []);
        if (is_array($stop) && $stop !== []) {
            // Env con "\n\n" literal → convertir escapes.
            $payload['stop'] = array_map(static function ($s) {
                return str_replace(['\\n', '\n'], "\n", (string) $s);
            }, $stop);
        }

        if ($withTools) {
            $payload['tools'] = [
                $this->calendarToolDefinition(),
                $this->bookingStateToolDefinition(),
            ];
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
                'description' => 'Crea una cita si el horario está libre. Si está ocupado, la herramienta devolverá error y debes ofrecer otro horario.',
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

    /**
     * @return array<string, mixed>
     */
    public function bookingStateToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'update_booking_state',
                'description' => 'Guarda el progreso de la reserva en el backend (no en el chat). Llámalo cuando conozcas nombre, servicio, fecha u hora.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'step' => [
                            'type' => 'string',
                            'description' => 'NEW|GREETING|AWAITING_NAME|AWAITING_SERVICE|AWAITING_DATE|AWAITING_TIME|READY|COMPLETED',
                        ],
                        'name' => ['type' => 'string'],
                        'service' => ['type' => 'string'],
                        'date' => [
                            'type' => 'string',
                            'description' => 'Fecha YYYY-MM-DD',
                        ],
                        'time' => [
                            'type' => 'string',
                            'description' => 'Hora HH:MM (24h)',
                        ],
                        'notes' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }
}
