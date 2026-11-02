<?php

namespace App\Jobs;

use App\Models\WhatsappInstance;
use App\Models\WhatsappSession;
use App\Services\Whatsapp\AppointmentOrchestrator;
use App\Services\Whatsapp\AudioTranscriptionService;
use App\Services\Whatsapp\BookingStateRepository;
use App\Services\Whatsapp\ChatSessionManager;
use App\Services\Whatsapp\ChatStateStore;
use App\Services\Whatsapp\ConversationRepository;
use App\Services\Whatsapp\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa un mensaje del webhook de Evolution API en cola (el webhook
 * responde HTTP 200 de inmediato para evitar reintentos).
 *
 * Bifurcación saliente (fromMe: true):
 * - Id registrado en mensajes_bot → envío propio del bot: se descarta (anti-bucle).
 * - "#bot"   → elimina la bandera de pausa y reactiva la IA de inmediato.
 * - "#cerrar" → marca la sesión como terminada y limpia su identificador.
 * - Cualquier otro texto → guarda/renueva la bandera de pausa (control humano).
 *
 * Bifurcación entrante (fromMe: false):
 * - Pausa activa → el mensaje solo se guarda para auditoría/historial.
 * - Sin pausa → ciclo de sesión + inserción en el búfer anti-metralleta.
 */
class ProcessWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /**
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public array $message,
        public string $instanceName,
        public string $phone,
    ) {
    }

    public function handle(
        ChatStateStore $store,
        ChatSessionManager $sessions,
        ConversationRepository $conversations,
        AppointmentOrchestrator $orchestrator,
        BookingStateRepository $bookingStates,
        EvolutionApiClient $evolution,
        AudioTranscriptionService $transcriber,
    ): void {
        $profile = WhatsappInstance::resolveActiveByEvolutionSession($this->instanceName);
        $sessionKey = $profile?->evolutionName() ?: $this->instanceName;
        $phone = $evolution->normalizePhone($this->phone);
        $fromMe = (bool) data_get($this->message, 'key.fromMe', false);
        $messageId = trim((string) data_get($this->message, 'key.id', ''));
        $text = $this->extractText($this->message);

        if ($fromMe) {
            $this->handleOutgoing($store, $sessions, $conversations, $bookingStates, $sessionKey, $phone, $messageId, $text, $profile);

            return;
        }

        $this->handleIncoming(
            $store,
            $sessions,
            $conversations,
            $orchestrator,
            $bookingStates,
            $evolution,
            $transcriber,
            $sessionKey,
            $phone,
            $text,
            $profile
        );
    }

    protected function handleOutgoing(
        ChatStateStore $store,
        ChatSessionManager $sessions,
        ConversationRepository $conversations,
        BookingStateRepository $bookingStates,
        string $sessionKey,
        string $phone,
        string $messageId,
        string $text,
        ?WhatsappInstance $profile,
    ): void {
        // Mensaje generado por el propio bot: descartado para evitar el bucle de autopausa.
        if ($messageId !== '' && $store->isBotMessage($messageId)) {
            return;
        }

        $command = mb_strtolower(trim($text));

        if ($command === '#bot') {
            $store->clearPause($sessionKey, $phone);
            $bookingStates->resumeBot($sessionKey, $phone);
            Log::info('WhatsApp bot reactivado por operador', [
                'instance' => $sessionKey,
                'phone' => $phone,
            ]);

            return;
        }

        if ($command === '#cerrar') {
            $sessions->close($sessionKey, $phone, WhatsappSession::REASON_COMANDO);
            Log::info('WhatsApp sesión cerrada por operador', [
                'instance' => $sessionKey,
                'phone' => $phone,
            ]);

            return;
        }

        // El operador escribió manualmente: se registra para que la IA entienda
        // qué se acordó y se guarda (o renueva) la bandera de pausa.
        $sessionId = $sessions->resolveOrCreate($sessionKey, $phone, $profile);

        if ($text !== '') {
            $conversations->append(
                $sessionKey,
                $phone,
                'assistant',
                $text,
                metadata: ['source' => 'operator'],
                sessionId: $sessionId
            );
        }

        $store->pause(
            $sessionKey,
            $phone,
            $profile?->pauseMinutes() ?? max(1, (int) config('services.whatsapp.tiempo_pausa_humano', 15))
        );
    }

    protected function handleIncoming(
        ChatStateStore $store,
        ChatSessionManager $sessions,
        ConversationRepository $conversations,
        AppointmentOrchestrator $orchestrator,
        BookingStateRepository $bookingStates,
        EvolutionApiClient $evolution,
        AudioTranscriptionService $transcriber,
        string $sessionKey,
        string $phone,
        string $text,
        ?WhatsappInstance $profile,
    ): void {
        $booking = $bookingStates->find($sessionKey, $phone);
        $paused = $store->isPaused($sessionKey, $phone)
            || ($booking !== null && $bookingStates->isBotPaused($booking));

        if ($paused) {
            // Un humano tiene el control: el mensaje solo queda para auditoría.
            if ($text !== '') {
                $conversations->append(
                    $sessionKey,
                    $phone,
                    'user',
                    $text,
                    metadata: ['source' => 'audit'],
                    sessionId: $store->sessionId($sessionKey, $phone)
                );
            }

            $sessions->renew($sessionKey, $phone, $profile);
            Log::info('WhatsApp bot paused; message stored without auto-reply', [
                'instance' => $sessionKey,
                'phone' => $phone,
            ]);

            return;
        }

        // 1. Notas de voz / audio con Whisper
        if ($text === '' && $this->isAudioMessage($this->message)) {
            if (config('services.whatsapp.transcribe_audio', true)) {
                try {
                    $evolution->sendPresence($sessionKey, $phone, 'composing', 2000);
                    $base64Audio = $evolution->downloadMediaBase64($sessionKey, $this->message);

                    if ($base64Audio) {
                        $transcribed = $transcriber->transcribe($base64Audio, 'ogg');
                        if (trim($transcribed) !== '') {
                            $text = trim($transcribed);
                            Log::info('Audio message transcribed successfully', [
                                'instance' => $sessionKey,
                                'phone' => $phone,
                                'text' => $text,
                            ]);
                        }
                    }
                } catch (Throwable $e) {
                    Log::warning('Audio transcription failed, falling back', [
                        'instance' => $sessionKey,
                        'phone' => $phone,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // 2. Media sin texto ni audio procesable → respuesta estática solo si está habilitado.
        if ($text === '' && $this->isNonTextMedia($this->message)) {
            if (config('services.whatsapp.auto_media_reply')) {
                try {
                    $orchestrator->sendStaticReply(
                        $sessionKey,
                        $phone,
                        (string) config('services.whatsapp.media_message')
                    );
                } catch (Throwable $e) {
                    Log::error('WhatsApp media static reply failed', [
                        'instance' => $sessionKey,
                        'phone' => $phone,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return;
        }

        if ($text === '') {
            Log::debug('Evolution webhook skipped incomplete message', [
                'instance' => $sessionKey,
                'phone' => $phone,
                'has_text' => false,
            ]);

            return;
        }

        // 3. Ciclo de vida de la sesión: crea o renueva el identificador y
        //    vincula el mensaje a la conversación activa.
        $sessionId = $sessions->resolveOrCreate($sessionKey, $phone, $profile);
        $conversations->append($sessionKey, $phone, 'user', $text, sessionId: $sessionId);

        if (config('services.whatsapp.auto_welcome')) {
            $sentWelcome = $orchestrator->maybeSendWelcome($sessionKey, $phone);

            if ($sentWelcome && $this->isGreetingOnly($text)) {
                return;
            }
        }

        // 4. Búfer anti-metralleta: se acumula el texto y se consolida
        //    al cerrarse la ventana de espera.
        $store->pushBuffer($sessionKey, $phone, $text);
        FlushChatBuffer::dispatch($sessionKey, $phone)
            ->delay(max(0, (int) config('services.whatsapp.buffer_window_seconds', 8)));
    }

    protected function isAudioMessage(array $message): bool
    {
        $msg = data_get($message, 'message');
        if (! is_array($msg)) {
            return false;
        }

        return isset($msg['audioMessage']) || isset($msg['pttMessage']);
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
