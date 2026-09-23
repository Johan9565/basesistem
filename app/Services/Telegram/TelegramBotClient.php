<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramBotClient
{
    protected const API = 'https://api.telegram.org';

    /**
     * @return array<string, mixed>
     */
    public function getMe(string $token): array
    {
        return $this->request($token, 'getMe');
    }

    /**
     * @return array<string, mixed>
     */
    public function setWebhook(string $token, string $url, string $secretToken): array
    {
        return $this->request($token, 'setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteWebhook(string $token): array
    {
        return $this->request($token, 'deleteWebhook', [
            'drop_pending_updates' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendMessage(string $token, int|string $chatId, string $text): array
    {
        $text = $this->truncate($text);

        return $this->request($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    /**
     * @param  list<int|string>  $chatIds
     */
    public function broadcast(string $token, array $chatIds, string $text): void
    {
        foreach ($chatIds as $chatId) {
            try {
                $this->sendMessage($token, $chatId, $text);
            } catch (RuntimeException $e) {
                Log::warning('Telegram broadcast failed', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function webhookUrlForInstance(string $instanceName): string
    {
        $base = (string) config('services.telegram.webhook_base_url');

        return $base.'/api/telegram/'.rawurlencode($instanceName).'/webhook';
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function request(string $token, string $method, array $params = []): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException('Token de Telegram vacío.');
        }

        try {
            $response = Http::baseUrl(self::API)
                ->acceptJson()
                ->timeout(30)
                ->asJson()
                ->post("/bot{$token}/{$method}", $params)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new RuntimeException(
                "Telegram {$method} falló: ".$e->getMessage(),
                0,
                $e,
            );
        }

        if (! is_array($response) || empty($response['ok'])) {
            $desc = is_array($response)
                ? (string) ($response['description'] ?? 'respuesta inválida')
                : 'respuesta inválida';

            throw new RuntimeException("Telegram {$method}: {$desc}");
        }

        /** @var array<string, mixed> $result */
        $result = is_array($response['result'] ?? null) ? $response['result'] : $response;

        return $result;
    }

    protected function truncate(string $text, int $max = 4000): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text !== '' ? $text : '…';
        }

        return mb_substr($text, 0, $max - 1).'…';
    }
}
