<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\AppointmentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvolutionWebhookController extends Controller
{
    public function __invoke(Request $request, AppointmentOrchestrator $orchestrator): JsonResponse
    {
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
            $text = $this->extractText($message);

            if ($instance === '' || $phone === '' || $text === '') {
                Log::debug('Evolution webhook skipped incomplete message', [
                    'instance' => $instance,
                    'phone' => $phone,
                    'has_text' => $text !== '',
                ]);

                continue;
            }

            try {
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
        return (bool) data_get($message, 'key.fromMe', false);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function extractPhone(array $message): string
    {
        $remoteJid = (string) data_get($message, 'key.remoteJid', '');

        if ($remoteJid === '') {
            $remoteJid = (string) data_get($message, 'remoteJid', '');
        }

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
