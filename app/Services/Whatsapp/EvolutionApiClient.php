<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EvolutionApiClient
{
    public function sendText(string $instance, string $phone, string $text): array
    {
        $baseUrl = rtrim((string) config('services.evolution.base_url'), '/');
        $apiKey = (string) config('services.evolution.api_key');

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('EVOLUTION_BASE_URL o EVOLUTION_API_KEY no están configuradas.');
        }

        $number = $this->normalizePhone($phone);

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders(['apikey' => $apiKey])
                ->acceptJson()
                ->timeout(30)
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

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/@.+$/', '', $phone) ?? $phone;

        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }
}
