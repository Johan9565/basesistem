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

            $messageKey = data_get($message, 'key');

            // Si es un audio o nota de voz de WhatsApp, transcribirlo
            if ($text === '' && $this->isAudioMessage($message)) {
                $text = $this->transcribeAudioMessage($message, $instanceName, $evolution, $orchestrator);

                if ($text === '') {
                    $evolution->sendHumanizedMessage(
                        $instanceName,
                        $phone,
                        '🎙️ He recibido tu nota de voz, pero no fue posible transcribirla claramente. ¿Podrías enviarla de nuevo o escribirme tu consulta?',
                        $messageKey
                    );
                    continue;
                }
            }

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
                    $evolution->sendHumanizedMessage($instanceName, $phone, $replyText, $messageKey);
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

    /**
     * Determina si el mensaje recibido es una nota de voz o archivo de audio.
     */
    protected function isAudioMessage(array $message): bool
    {
        $type = (string) data_get($message, 'messageType', '');
        if (in_array($type, ['audioMessage', 'audio', 'ptt'], true)) {
            return true;
        }

        return data_get($message, 'message.audioMessage') !== null;
    }

    /**
     * Descarga el audio desde Evolution API y lo transcribe con Groq Whisper / Deepgram.
     */
    protected function transcribeAudioMessage(
        array $message,
        string $instanceName,
        EvolutionApiService $evolution,
        AssistantOrchestratorService $orchestrator
    ): string {
        $transcribeEnabled = filter_var(
            env('WHATSAPP_TRANSCRIBE_AUDIO', true),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$transcribeEnabled) {
            Log::info('WhatsApp audio transcription disabled via WHATSAPP_TRANSCRIBE_AUDIO');
            return '';
        }

        try {
            $base64   = data_get($message, 'base64');
            $mimetype = (string) (data_get($message, 'message.audioMessage.mimetype') ?: 'audio/ogg');

            if (empty($base64)) {
                $mediaData = $evolution->getBase64FromMediaMessage($instanceName, $message);
                $base64    = data_get($mediaData, 'base64');
                $mimetype  = (string) (data_get($mediaData, 'mimetype') ?: $mimetype);
            }

            if (empty($base64) || !is_string($base64)) {
                Log::warning('Evolution audio message without base64 content', [
                    'instance' => $instanceName,
                    'key'      => data_get($message, 'key'),
                ]);
                return '';
            }

            if (str_contains($base64, ',')) {
                $parts = explode(',', $base64);
                $base64 = $parts[1] ?? $base64;
            }

            $binary = base64_decode($base64);
            if ($binary === false || strlen($binary) === 0) {
                Log::warning('Evolution audio base64 decode failed');
                return '';
            }

            $ext = 'ogg';
            if (str_contains($mimetype, 'mp4') || str_contains($mimetype, 'm4a')) {
                $ext = 'm4a';
            } elseif (str_contains($mimetype, 'mp3') || str_contains($mimetype, 'mpeg')) {
                $ext = 'mp3';
            } elseif (str_contains($mimetype, 'wav')) {
                $ext = 'wav';
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'wa_voice_') . '.' . $ext;
            file_put_contents($tmpFile, $binary);

            try {
                $transcription = $orchestrator->transcribeAudio($tmpFile);
                Log::info('WhatsApp audio voice note transcribed', [
                    'instance'      => $instanceName,
                    'transcription' => $transcription,
                ]);
                return trim($transcription);
            } finally {
                if (file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
            }
        } catch (Throwable $e) {
            Log::error('WhatsApp audio transcription exception: ' . $e->getMessage(), [
                'instance' => $instanceName,
            ]);
            return '';
        }
    }
}

