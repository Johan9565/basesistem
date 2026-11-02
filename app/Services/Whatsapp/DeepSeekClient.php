<?php

namespace App\Services\Whatsapp;

use App\Services\Whatsapp\Concerns\HasWhatsappBookingTools;
use App\Services\Whatsapp\Contracts\LlmChatClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekClient implements LlmChatClient
{
    use HasWhatsappBookingTools;

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    public function chat(array $messages, bool $withTools = true, ?array $tools = null, ?int $maxTokens = null): array
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
            'max_tokens' => $maxTokens ?? (int) config('services.deepseek.max_tokens', 1600),
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
            $payload['tools'] = $tools ?? $this->bookingTools();
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
}
