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
                'response' => $e->response?->json(),
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
}
