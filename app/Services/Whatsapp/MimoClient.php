<?php

namespace App\Services\Whatsapp;

use App\Services\Whatsapp\Concerns\HasWhatsappBookingTools;
use App\Services\Whatsapp\Contracts\LlmChatClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MimoClient implements LlmChatClient
{
    use HasWhatsappBookingTools;

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    public function chat(array $messages, bool $withTools = true, ?array $tools = null): array
    {
        $apiKey = (string) config('services.mimo.key');
        $baseUrl = rtrim((string) config('services.mimo.base_url'), '/');
        $model = (string) config('services.mimo.model', 'mimo-v2-flash');

        if ($apiKey === '') {
            throw new RuntimeException('MIMO_API_KEY no está configurada.');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => (int) config('services.mimo.max_tokens', 150),
            'temperature' => (float) config('services.mimo.temperature', 0.25),
            'stream' => false,
            // Flash puede activar thinking; lo desactivamos para respuestas cortas de WhatsApp.
            'thinking' => ['type' => 'disabled'],
        ];

        $stop = config('services.mimo.stop', []);
        if (is_array($stop) && $stop !== []) {
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
                ->timeout(120)
                ->post('/chat/completions', $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new RuntimeException('Error al llamar a MiMo: '.$e->getMessage(), 0, $e);
        }

        return $response;
    }
}
