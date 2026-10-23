<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EvolutionApiService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.evolution.base_url', 'http://evolution-api:8080'), '/');
        $this->apiKey  = (string) config('services.evolution.api_key', '');
    }

    /**
     * Instancia HTTP base con autenticación Evolution.
     */
    protected function http(int $timeout = 30): PendingRequest
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('EVOLUTION_BASE_URL o EVOLUTION_API_KEY no están configuradas.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['apikey' => $this->apiKey])
            ->acceptJson()
            ->timeout($timeout);
    }

    /**
     * Crea una instancia en Evolution API.
     *
     * @return array<string, mixed>
     */
    public function createInstance(string $name, ?string $webhookUrl = null): array
    {
        $secret = (string) config('services.evolution.webhook_secret');
        $webhookUrl = $webhookUrl ?: (string) config('services.evolution.webhook_url');

        $payload = [
            'instanceName' => $name,
            'qrcode'       => true,
            'integration'  => 'WHATSAPP-BAILEYS',
        ];

        if ($webhookUrl !== '') {
            $payload['webhook'] = [
                'url'      => $webhookUrl,
                'byEvents' => false,
                'base64'   => false,
                'events'   => ['MESSAGES_UPSERT'],
                'headers'  => [
                    'X-Evolution-Secret' => $secret,
                ],
            ];
        }

        try {
            $response = $this->http(60)
                ->post('/instance/create', $payload)
                ->throw()
                ->json();

            return is_array($response) ? $response : [];
        } catch (RequestException $e) {
            Log::error('Evolution createInstance failed', [
                'instance' => $name,
                'error'    => $e->getMessage(),
                'response' => $e->response ? $e->response->json() : null,
            ]);

            throw new RuntimeException('Error al crear instancia en Evolution API: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Inicia conexión y obtiene QR Code o Pairing Code.
     *
     * @return array<string, mixed>
     */
    public function connect(string $name): array
    {
        try {
            $response = $this->http(60)
                ->get("/instance/connect/{$name}")
                ->throw()
                ->json();

            return is_array($response) ? $response : [];
        } catch (RequestException $e) {
            Log::error('Evolution connect failed', [
                'instance' => $name,
                'error'    => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al conectar instancia en Evolution API: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Consulta el estado de conexión de la instancia.
     *
     * @return array<string, mixed>
     */
    public function connectionState(string $name): array
    {
        try {
            $response = $this->http(20)
                ->get("/instance/connectionState/{$name}")
                ->throw()
                ->json();

            return is_array($response) ? $response : [];
        } catch (RequestException $e) {
            Log::error('Evolution connectionState failed', [
                'instance' => $name,
                'error'    => $e->getMessage(),
            ]);

            return ['instance' => ['state' => 'close']];
        }
    }

    /**
     * Obtiene los datos detallados de la instancia desde Evolution.
     */
    public function fetchInstance(string $name): ?array
    {
        try {
            $response = $this->http(15)
                ->get('/instance/fetchInstances', ['instanceName' => $name])
                ->json();

            if (is_array($response)) {
                foreach ($response as $item) {
                    if (is_array($item) && ($item['name'] ?? '') === $name) {
                        return $item;
                    }
                }
            }

            return null;
        } catch (Throwable $e) {
            Log::debug('Evolution fetchInstance failed', [
                'instance' => $name,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cierra la sesión (logout) de la instancia.
     */
    public function logout(string $name): array
    {
        try {
            $response = $this->http(30)
                ->delete("/instance/logout/{$name}")
                ->json();

            return is_array($response) ? $response : [];
        } catch (Throwable $e) {
            Log::warning('Evolution logout failed', [
                'instance' => $name,
                'error'    => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Elimina completamente la instancia de Evolution API.
     */
    public function deleteInstance(string $name): array
    {
        try {
            $response = $this->http(30)
                ->delete("/instance/delete/{$name}")
                ->json();

            return is_array($response) ? $response : [];
        } catch (Throwable $e) {
            Log::warning('Evolution deleteInstance failed', [
                'instance' => $name,
                'error'    => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Envía un mensaje de texto simple por WhatsApp.
     */
    public function sendTextMessage(string $name, string $phone, string $text): array
    {
        $number = $this->normalizePhone($phone);

        try {
            $response = $this->http(30)
                ->post("/message/sendText/{$name}", [
                    'number' => $number,
                    'text'   => $text,
                ])
                ->throw()
                ->json();

            return is_array($response) ? $response : [];
        } catch (RequestException $e) {
            Log::error('Evolution sendTextMessage failed', [
                'instance' => $name,
                'phone'    => $number,
                'error'    => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al enviar mensaje por Evolution API: ' . $e->getMessage(), 0, $e);
        }
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/@.+$/', '', $phone) ?? $phone;
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }

    /**
     * Descarga y obtiene el contenido multimedia en base64 desde Evolution API.
     *
     * @param string $name Nombre de la instancia
     * @param array $message Mensaje completo recibido por webhook
     * @return array<string, mixed>|null
     */
    public function getBase64FromMediaMessage(string $name, array $message): ?array
    {
        try {
            $payload = [
                'message' => [
                    'key'     => data_get($message, 'key', []),
                    'message' => data_get($message, 'message', []),
                ],
                'convertToMp4' => false,
            ];

            $response = $this->http(60)
                ->post("/chat/getBase64FromMediaMessage/{$name}", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return is_array($data) ? $data : null;
            }

            Log::warning('Evolution getBase64FromMediaMessage error response', [
                'instance' => $name,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            return null;
        } catch (Throwable $e) {
            Log::error('Evolution getBase64FromMediaMessage exception: ' . $e->getMessage(), [
                'instance' => $name,
            ]);

            return null;
        }
    }

    /**
     * Emite el estado de presencia ("composing", "recording", "available", "paused") en WhatsApp.
     *
     * @param string $name Nombre de la instancia
     * @param string $phone Número telefónico
     * @param string $presence "composing"|"recording"|"available"|"paused"
     * @param int $delay Duración en milisegundos
     * @return array<string, mixed>
     */
    public function sendPresence(string $name, string $phone, string $presence = 'composing', int $delay = 1200): array
    {
        $number = $this->normalizePhone($phone);

        try {
            $response = $this->http(15)
                ->post("/chat/sendPresence/{$name}", [
                    'number'   => $number,
                    'presence' => $presence,
                    'delay'    => $delay,
                ])
                ->json();

            return is_array($response) ? $response : [];
        } catch (Throwable $e) {
            Log::debug('Evolution sendPresence failed', [
                'instance' => $name,
                'phone'    => $number,
                'presence' => $presence,
                'error'    => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Marca un mensaje entrante como leído en WhatsApp (Read Receipt / doble check azul).
     *
     * @param string $name Nombre de la instancia
     * @param array $messageKey Clave del mensaje {id, remoteJid, fromMe}
     * @return array<string, mixed>
     */
    public function markMessageAsRead(string $name, array $messageKey): array
    {
        $id = data_get($messageKey, 'id');
        $remoteJid = data_get($messageKey, 'remoteJid');

        if (!$id || !$remoteJid) {
            return [];
        }

        try {
            $response = $this->http(15)
                ->post("/chat/markMessageAsRead/{$name}", [
                    'readMessages' => [
                        [
                            'id'        => $id,
                            'fromMe'    => false,
                            'remoteJid' => $remoteJid,
                        ],
                    ],
                ])
                ->json();

            return is_array($response) ? $response : [];
        } catch (Throwable $e) {
            Log::debug('Evolution markMessageAsRead failed', [
                'instance' => $name,
                'id'       => $id,
                'error'    => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Despacha un mensaje aplicando el pipeline humanizado anti-baneo.
     *
     * @param string $name Nombre de la instancia
     * @param string $phone Número de teléfono
     * @param string $text Texto saliente
     * @param array|null $messageKey Clave del mensaje original para marcar lectura
     * @return array<int, array<string, mixed>>
     */
    public function sendHumanizedMessage(string $name, string $phone, string $text, ?array $messageKey = null): array
    {
        /** @var WhatsAppAntiBanPipeline $pipeline */
        $pipeline = app(WhatsAppAntiBanPipeline::class);

        return $pipeline->sendHumanizedResponse($name, $phone, $text, $messageKey);
    }
}
