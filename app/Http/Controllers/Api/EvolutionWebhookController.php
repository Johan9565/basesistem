<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\AppointmentOrchestrator;
use App\Services\Whatsapp\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvolutionWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        AppointmentOrchestrator $orchestrator,
        EvolutionApiClient $evolution,
    ): JsonResponse {
        $payload = $request->all();
        $event = strtoupper((string) ($payload['event'] ?? $payload['type'] ?? ''));

        if ($event !== '' && $event !== 'MESSAGES_UPSERT' && $event !== 'MESSAGES.UPSERT') {
            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'event_filtered']);
        }

        $data = $payload['data'] ?? $payload;
        $messages = $this->normalizeMessages($data);

        foreach ($messages as $message) {
            if ($this->shouldSkip($message)) {
                continue;
            }

            $instance = (string) (
                $payload['instance']
                ?? $payload['instanceName']
                ?? data_get($message, 'instance')
                ?? ''
            );

            $phone = $this->extractPhone($message);
            if ($instance === '' || $phone === '') {
                continue;
            }

            $phone = $evolution->normalizePhone($phone);

            // Media sin texto → respuesta estática solo si está habilitado.
            if ($this->isNonTextMedia($message)) {
                if (config('services.whatsapp.auto_media_reply')) {
                    try {
                        $orchestrator->sendStaticReply(
                            $instance,
                            $phone,
                            (string) config('services.whatsapp.media_message'),
                        );
                    } catch (Throwable $e) {
                        Log::error('WhatsApp media static reply failed', [
                            'instance' => $instance,
                            'phone' => $phone,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                continue;
            }

            $text = $this->extractText($message);
            if ($text === '') {
                Log::debug('Evolution webhook skipped incomplete message', [
                    'instance' => $instance,
                    'phone' => $phone,
                    'has_text' => false,
                ]);

                continue;
            }

            try {
                $sentWelcome = false;
                if (config('services.whatsapp.auto_welcome')) {
                    $sentWelcome = $orchestrator->maybeSendWelcome($instance, $phone);
                }

                if ($sentWelcome && $this->isGreetingOnly($text)) {
                    continue;
                }

                $orchestrator->handleIncoming($instance, $phone, $text);
            } catch (Throwable $e) {
                Log::error('Evolution webhook processing failed', [
                    'instance' => $instance,
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    protected function isGreetingOnly(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized) ?? $normalized;
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);

        $greetings = [
            'hola', 'buenas', 'buen dia', 'buen día', 'buenas tardes', 'buenas noches',
            'hey', 'hi', 'hello', 'qué tal', 'que tal', 'saludos',
        ];

        return in_array($normalized, $greetings, true);
    }

    /**
     * @param  mixed  $data
     * @return list<array<string, mixed>>
     */
    protected function normalizeMessages(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (isset($data['key']) || isset($data['message'])) {
            return [$data];
        }

        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        if (isset($data['messages']) && is_array($data['messages'])) {
            return array_values(array_filter($data['messages'], 'is_array'));
        }

        return [$data];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function shouldSkip(array $message): bool
    {
        if ((bool) data_get($message, 'key.fromMe', false)) {
            return true;
        }

        $remoteJid = $this->extractRemoteJid($message);

        if (
            str_ends_with($remoteJid, '@g.us')
            || str_ends_with($remoteJid, '@broadcast')
            || str_ends_with($remoteJid, '@newsletter')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Audio / documento / sticker / video / imagen sin caption.
     *
     * @param  array<string, mixed>  $message
     */
    protected function isNonTextMedia(array $message): bool
    {
        $msg = data_get($message, 'message');
        if (! is_array($msg)) {
            return false;
        }

        $mediaKeys = [
            'audioMessage',
            'documentMessage',
            'stickerMessage',
            'videoMessage',
            'pttMessage',
        ];

        foreach ($mediaKeys as $key) {
            if (isset($msg[$key])) {
                return true;
            }
        }

        if (isset($msg['imageMessage'])) {
            $caption = trim((string) data_get($msg, 'imageMessage.caption', ''));

            return $caption === '';
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function extractRemoteJid(array $message): string
    {
        $remoteJid = (string) data_get($message, 'key.remoteJid', '');

        if ($remoteJid === '') {
            $remoteJid = (string) data_get($message, 'remoteJid', '');
        }

        return $remoteJid;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function extractPhone(array $message): string
    {
        $remoteJid = $this->extractRemoteJid($message);

        return preg_replace('/@.+$/', '', $remoteJid) ?? $remoteJid;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function extractText(array $message): string
    {
        $candidates = [
            data_get($message, 'message.conversation'),
            data_get($message, 'message.extendedTextMessage.text'),
            data_get($message, 'message.imageMessage.caption'),
            data_get($message, 'conversation'),
            data_get($message, 'text'),
            data_get($message, 'body'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }
}
