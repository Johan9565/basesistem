<?php

namespace App\Jobs;

use App\Events\AssistantMessageProcessed;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\AssistantOrchestratorService;
use App\Services\Whatsapp\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAssistantMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [2, 10, 30, 60];
    public int $timeout = 120;

    public function __construct(public string $messageId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(
        AssistantOrchestratorService $orchestrator,
        EvolutionApiService $evolution
    ): void {
        $assistantMessage = AssistantMessage::find($this->messageId);

        if (!$assistantMessage) {
            Log::warning("ProcessAssistantMessage: Message not found [{$this->messageId}]");
            return;
        }

        if ($assistantMessage->status === 'completed') {
            Log::info("ProcessAssistantMessage: Message already completed [{$this->messageId}]");
            return;
        }

        $assistantMessage->update([
            'status'      => 'processing',
            'retry_count' => $this->attempts(),
        ]);

        $user = User::find($assistantMessage->user_id);
        if (!$user) {
            $assistantMessage->update([
                'status'        => 'failed',
                'error_message' => 'Usuario no encontrado para procesar el mensaje.',
            ]);
            return;
        }

        $prompt = (string) $assistantMessage->prompt;
        $metadata = (array) ($assistantMessage->metadata ?? []);
        $history = (array) ($metadata['conversation_history'] ?? []);

        // Si hay un prompt del sistema personalizado (ej. WhatsApp instance), anteponerlo al historial
        if (!empty($metadata['system_prompt'])) {
            array_unshift($history, [
                'role'    => 'system',
                'content' => 'Instrucciones adicionales para este canal: ' . $metadata['system_prompt'],
            ]);
        }

        try {
            // Interactuar con el orquestador inteligente del asistente
            $result = $orchestrator->interact($user, $prompt, $history);

            $displayText = $result['display_text'] ?? $result['message'] ?? 'Mensaje procesado correctamente.';
            $speechText  = $result['speech_text'] ?? $displayText;
            $action      = $result['action'] ?? 'conversation';

            $assistantMessage->update([
                'status'       => 'completed',
                'reply'        => $displayText,
                'display_text' => $displayText,
                'speech_text'  => $speechText,
                'action'       => $action,
            ]);

            // Si proviene de WhatsApp, despachar respuesta por Evolution API
            if ($assistantMessage->channel === 'whatsapp') {
                $instanceName = $metadata['instance_name'] ?? null;
                $phone        = $metadata['phone'] ?? null;
                $messageKey   = $metadata['message_key'] ?? null;

                if ($instanceName && $phone && trim($replyText) !== '') {
                    $evolution->sendHumanizedMessage($instanceName, $phone, $replyText, $messageKey);
                }
            }

            // Emitir evento de broadcast para la app móvil y clientes WebSockets / Reverb
            try {
                broadcast(new AssistantMessageProcessed($assistantMessage))->toOthers();
            } catch (\Throwable $be) {
                Log::warning('ProcessAssistantMessage: Broadcast exception (ignorable): ' . $be->getMessage());
            }

        } catch (\Throwable $e) {
            Log::error("ProcessAssistantMessage error (Attempt {$this->attempts()}/{$this->tries}): " . $e->getMessage(), [
                'message_id' => $this->messageId,
                'user_id'    => $assistantMessage->user_id,
            ]);

            // Reintentar si aún quedan intentos disponibles
            if ($this->attempts() < $this->tries) {
                throw $e;
            }

            // Todos los reintentos fallaron
            $assistantMessage->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            try {
                broadcast(new AssistantMessageProcessed($assistantMessage))->toOthers();
            } catch (\Throwable $be) {
                // Silenciar fallo de broadcast en error
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?\Throwable $exception): void
    {
        $assistantMessage = AssistantMessage::find($this->messageId);
        if ($assistantMessage) {
            $assistantMessage->update([
                'status'        => 'failed',
                'error_message' => $exception?->getMessage() ?? 'Falló el procesamiento tras 5 intentos.',
            ]);

            try {
                broadcast(new AssistantMessageProcessed($assistantMessage))->toOthers();
            } catch (\Throwable $e) {
                // Silenciar fallo
            }
        }
    }
}
