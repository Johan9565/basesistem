<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EvolutionApiClient
{
    public function sendText(string $instance, string $phone, string $text): array
    {
        $number = $this->normalizePhone($phone);

        try {
            $response = $this->http(30)
                ->post("/message/sendText/{$instance}", [
                    'number' => $number,
                    'text' => $text,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('Evolution sendText failed', [
                'instance' => $instance,
                'phone' => $number,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al enviar mensaje por Evolution API: '.$e->getMessage(), 0, $e);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function createInstance(string $name, ?string $webhookUrl = null): array
    {
        $secret = (string) config('services.evolution.webhook_secret');
        $webhookUrl = $webhookUrl ?: (string) config('services.evolution.webhook_url');

        $payload = [
            'instanceName' => $name,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ];

        if ($webhookUrl !== '') {
            $payload['webhook'] = [
                'url' => $webhookUrl,
                'byEvents' => false,
                'base64' => false,
                'events' => ['MESSAGES_UPSERT'],
                'headers' => [
                    'X-Evolution-Secret' => $secret,
                ],
            ];
        }

        try {
            $response = $this->http(60)
                ->post('/instance/create', $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('Evolution createInstance failed', [
                'instance' => $name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al crear instancia en Evolution API: '.$e->getMessage(), 0, $e);
        }

        try {
            $this->setGroupsIgnore($name, true);
        } catch (Throwable $e) {
            Log::warning('Evolution groupsIgnore no se pudo aplicar al crear', [
                'instance' => $name,
                'error' => $e->getMessage(),
            ]);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * Evita que Evolution procese mensajes de grupos.
     *
     * @return array<string, mixed>
     */
    public function setGroupsIgnore(string $name, bool $ignore = true): array
    {
        try {
            $response = $this->http(30)
                ->post("/settings/set/{$name}", [
                    'rejectCall' => false,
                    'msgCall' => '',
                    'groupsIgnore' => $ignore,
                    'alwaysOnline' => false,
                    'readMessages' => false,
                    'readStatus' => false,
                    'syncFullHistory' => false,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('Evolution setGroupsIgnore failed', [
                'instance' => $name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al configurar groupsIgnore en Evolution: '.$e->getMessage(), 0, $e);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function connect(string $name): array
    {
        try {
            $response = $this->http(60)
                ->get("/instance/connect/{$name}")
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('Evolution connect failed', [
                'instance' => $name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al conectar instancia en Evolution API: '.$e->getMessage(), 0, $e);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionState(string $name): array
    {
        try {
            $response = $this->http(30)
                ->get("/instance/connectionState/{$name}")
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('Evolution connectionState failed', [
                'instance' => $name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al consultar estado en Evolution API: '.$e->getMessage(), 0, $e);
        }

        return is_array($response) ? $response : [];
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/@.+$/', '', $phone) ?? $phone;

        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }

    protected function http(int $timeout): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.evolution.base_url'), '/');
        $apiKey = (string) config('services.evolution.api_key');

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('EVOLUTION_BASE_URL o EVOLUTION_API_KEY no están configuradas.');
        }

        return Http::baseUrl($baseUrl)
            ->withHeaders(['apikey' => $apiKey])
            ->acceptJson()
            ->timeout($timeout);
    }
}
