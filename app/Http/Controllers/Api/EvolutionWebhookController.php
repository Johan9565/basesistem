<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Services\Assistant\AssistantOrchestratorService;
use App\Services\Whatsapp\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvolutionWebhookController extends Controller
{
    public function handle(
        Request $request,
        EvolutionApiService $evolution,
        AssistantOrchestratorService $orchestrator
    ): JsonResponse {
        $secret = (string) config('services.evolution.webhook_secret');
        if ($secret !== '') {
            $providedSecret = (string) $request->header('X-Evolution-Secret', $request->header('x-evolution-secret'));
            if ($providedSecret !== $secret) {
                Log::warning('Evolution webhook invalid secret attempt');
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $payload = $request->all();
        $event = (string) ($payload['event'] ?? '');

        // Solo procesamos mensajes entrantes
        if ($event !== '' && !in_array($event, ['messages.upsert', 'MESSAGES_UPSERT'], true)) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $instanceName = (string) ($payload['instance'] ?? $payload['instanceName'] ?? '');
        if ($instanceName === '') {
            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'no_instance']);
        }

        $instance = WhatsappInstance::where('instance_name', $instanceName)->first();
        if (!$instance || !$instance->enabled) {
            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'instance_inactive_or_disabled']);
        }

        $user = $instance->user()->first();
        if (!$user) {
            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'no_user']);
        }

        $data = $payload['data'] ?? $payload;
        $messages = $this->normalizeMessages($data);

        foreach ($messages as $message) {
            if ($this->shouldSkip($message)) {
                continue;
            }

            $phone = $this->extractPhone($message);
            $text = $this->extractText($message);

            if ($phone === '' || $text === '') {
                continue;
            }

            try {
                // Interactuar con el asistente del usuario
                $customPrompt = $instance->system_prompt ? "Instrucciones de WhatsApp para este usuario: " . $instance->system_prompt . "\n" : "";
                $history = [];
                if ($customPrompt !== '') {
                    $history[] = ['role' => 'system', 'content' => $customPrompt];
                }

                $replyData = $orchestrator->interact($user, $text, $history);
                $replyText = $replyData['message'] ?? 'Mensaje procesado por tu asistente.';

                if (trim($replyText) !== '') {
                    $evolution->sendTextMessage($instanceName, $phone, $replyText);
                }
            } catch (Throwable $e) {
                Log::error('Evolution webhook processing error', [
                    'instance' => $instanceName,
                    'phone'    => $phone,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    protected function normalizeMessages(mixed $data): array
    {
        if (!is_array($data)) {
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

    protected function shouldSkip(array $message): bool
    {
        if ((bool) data_get($message, 'key.fromMe', false)) {
            return true;
        }

        $remoteJid = (string) data_get($message, 'key.remoteJid', data_get($message, 'remoteJid', ''));
        if (
            str_ends_with($remoteJid, '@g.us')
            || str_ends_with($remoteJid, '@broadcast')
            || str_ends_with($remoteJid, '@newsletter')
        ) {
            return true;
        }

        return false;
    }

    protected function extractPhone(array $message): string
    {
        $remoteJid = (string) data_get($message, 'key.remoteJid', data_get($message, 'remoteJid', ''));
        $clean = preg_replace('/@.+$/', '', $remoteJid) ?? $remoteJid;
        return preg_replace('/\D+/', '', $clean) ?? $clean;
    }

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

        foreach ($candidates as $c) {
            if (is_string($c) && trim($c) !== '') {
                return trim($c);
            }
        }

        return '';
    }
}
