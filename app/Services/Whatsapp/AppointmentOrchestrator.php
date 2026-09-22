<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappAppointment;
use App\Models\WhatsappInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AppointmentOrchestrator
{
    public function __construct(
        protected ConversationRepository $conversations,
        protected BookingStateRepository $bookingStates,
        protected DeepSeekClient $deepSeek,
        protected GoogleCalendarService $calendar,
        protected EvolutionApiClient $evolution,
    ) {}

    public function handleIncoming(string $instanceName, string $userPhone, string $text): string
    {
        $instance = WhatsappInstance::query()
            ->where('instance_name', $instanceName)
            ->where('status', 'active')
            ->first();

        if (! $instance) {
            throw new RuntimeException("Instancia WhatsApp no registrada o inactiva: {$instanceName}");
        }

        $phone = $this->evolution->normalizePhone($userPhone);
        $booking = $this->bookingStates->touch($instanceName, $phone);

        $this->conversations->append($instanceName, $phone, 'user', $text);

        $timezone = $instance->timezone ?? config('services.google_calendar.timezone');
        $systemPrompt = $this->buildSystemPrompt($instance, $booking->fresh() ?? $booking, (string) $timezone);

        // Con estado en DB basta historial corto (últimos N turnos user/assistant).
        $messages = $this->conversations->toDeepSeekMessages($instanceName, $phone, $systemPrompt);

        try {
            $reply = $this->runDeepSeekLoop($messages, $instance, $instanceName, $phone);
        } catch (Throwable $e) {
            if ($this->isDeepSeekToolHistoryError($e)) {
                Log::warning('DeepSeek history invalid; retrying with fresh context', [
                    'instance' => $instanceName,
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);

                $messages = [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $text],
                ];
                $reply = $this->runDeepSeekLoop($messages, $instance, $instanceName, $phone);
            } else {
                throw $e;
            }
        }

        $this->conversations->append($instanceName, $phone, 'assistant', $reply);
        $this->evolution->sendText($instanceName, $phone, $reply);
        $this->bookingStates->touch($instanceName, $phone);

        return $reply;
    }

    /**
     * Respuesta estática sin LLM (media / bienvenida).
     */
    public function sendStaticReply(string $instanceName, string $userPhone, string $text): void
    {
        $phone = $this->evolution->normalizePhone($userPhone);
        $this->conversations->append($instanceName, $phone, 'assistant', $text);
        $this->evolution->sendText($instanceName, $phone, $text);
        $this->bookingStates->touch($instanceName, $phone);
    }

    public function maybeSendWelcome(string $instanceName, string $userPhone): bool
    {
        $phone = $this->evolution->normalizePhone($userPhone);
        $state = $this->bookingStates->findOrCreate($instanceName, $phone);
        $hours = (int) config('services.whatsapp.inactive_hours', 24);

        if (! $this->bookingStates->shouldSendWelcome($state, $hours)) {
            return false;
        }

        $welcome = (string) config('services.whatsapp.welcome_message');
        $this->sendStaticReply($instanceName, $phone, $welcome);
        $this->bookingStates->markWelcomeSent($state->fresh() ?? $state);

        return true;
    }

    protected function isDeepSeekToolHistoryError(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'tool_calls')
            || str_contains($msg, "role 'tool'")
            || str_contains($msg, 'tool messages');
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    protected function runDeepSeekLoop(
        array $messages,
        WhatsappInstance $instance,
        string $instanceName,
        string $phone,
    ): string {
        $maxRounds = 2;

        for ($round = 0; $round < $maxRounds; $round++) {
            $response = $this->deepSeek->chat($messages, withTools: true);
            $choice = $response['choices'][0]['message'] ?? null;

            if (! is_array($choice)) {
                throw new RuntimeException('Respuesta inválida de DeepSeek.');
            }

            $toolCalls = $choice['tool_calls'] ?? [];

            if ($toolCalls === []) {
                $content = trim((string) ($choice['content'] ?? ''));

                return $content !== ''
                    ? $content
                    : 'Listo. ¿En qué más te puedo ayudar con tu cita?';
            }

            $assistantContent = (string) ($choice['content'] ?? '');
            $this->conversations->append(
                $instanceName,
                $phone,
                'assistant',
                $assistantContent,
                metadata: ['tool_calls' => $toolCalls],
            );

            $messages[] = [
                'role' => 'assistant',
                'content' => $assistantContent,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $toolCall) {
                $toolResult = $this->executeToolCall($toolCall, $instance, $phone);
                $toolCallId = (string) ($toolCall['id'] ?? '');
                $toolName = (string) ($toolCall['function']['name'] ?? '');
                $encoded = json_encode($toolResult, JSON_UNESCAPED_UNICODE) ?: '{}';

                $this->conversations->append(
                    $instanceName,
                    $phone,
                    'tool',
                    $encoded,
                    toolCallId: $toolCallId,
                    toolName: $toolName,
                );

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => $encoded,
                ];
            }
        }

        $final = $this->deepSeek->chat($messages, withTools: false);
        $content = trim((string) ($final['choices'][0]['message']['content'] ?? ''));

        return $content !== ''
            ? $content
            : 'La cita se procesó. Si necesitas otro horario, escríbeme.';
    }

    /**
     * @param  array<string, mixed>  $toolCall
     * @return array<string, mixed>
     */
    protected function executeToolCall(array $toolCall, WhatsappInstance $instance, string $phone): array
    {
        $name = (string) ($toolCall['function']['name'] ?? '');
        $rawArgs = (string) ($toolCall['function']['arguments'] ?? '{}');
        $args = json_decode($rawArgs, true);

        if (! is_array($args)) {
            return ['ok' => false, 'error' => 'Argumentos de herramienta inválidos.'];
        }

        if ($name === 'update_booking_state') {
            $state = $this->bookingStates->updateState(
                (string) $instance->instance_name,
                $phone,
                $args,
            );

            return [
                'ok' => true,
                'state' => json_decode($this->bookingStates->toPromptJson($state), true),
                'message' => 'Estado de reserva actualizado.',
            ];
        }

        if ($name !== 'create_calendar_event') {
            return ['ok' => false, 'error' => "Herramienta desconocida: {$name}"];
        }

        $summary = trim((string) ($args['summary'] ?? 'Cita'));
        $startIso = (string) ($args['start_iso'] ?? '');
        $endIso = (string) ($args['end_iso'] ?? '');
        $description = isset($args['description']) ? (string) $args['description'] : null;
        $timezone = $instance->timezone ?: (string) config('services.google_calendar.timezone');
        $calendarId = $instance->google_calendar_id
            ?: (string) config('services.google_calendar.calendar_id');

        if ($startIso === '' || $endIso === '') {
            return [
                'ok' => false,
                'error' => 'Faltan start_iso o end_iso para agendar.',
            ];
        }

        try {
            $start = Carbon::parse($startIso, $timezone);
            $end = Carbon::parse($endIso, $timezone);

            if ($end->lessThanOrEqualTo($start)) {
                $end = $start->copy()->addMinutes(30);
            }

            $localConflicts = $this->calendar->findLocalConflicts(
                (string) $instance->instance_name,
                $start,
                $end,
            );

            if ($localConflicts !== []) {
                $labels = collect($localConflicts)->map(function ($a) {
                    $from = optional($a->starts_at)->format('Y-m-d H:i');
                    $to = optional($a->ends_at)->format('H:i');

                    return trim(($a->summary ?? 'Cita')." ($from–$to)");
                })->take(3)->implode('; ');

                return [
                    'ok' => false,
                    'error' => 'Horario ocupado en el sistema.',
                    'conflicts' => $labels,
                    'message' => "Ese horario ya está ocupado: {$labels}. Ofrece otro horario al usuario.",
                ];
            }

            if ($calendarId !== '' && ($instance->google_credentials_path || config('services.google_calendar.credentials_path'))) {
                try {
                    if ($this->calendar->hasBusyConflict(
                        $calendarId,
                        $start,
                        $end,
                        $instance->google_credentials_path ?: null,
                    )) {
                        return [
                            'ok' => false,
                            'error' => 'Horario ocupado en Google Calendar.',
                            'message' => 'Ese horario ya está ocupado en Google Calendar. Ofrece otro horario al usuario.',
                        ];
                    }
                } catch (Throwable $e) {
                    Log::warning('Conflict check Google failed', [
                        'error' => $e->getMessage(),
                        'instance' => $instance->instance_name,
                    ]);
                }
            }

            $appointment = WhatsappAppointment::query()->create([
                'instance_name' => (string) $instance->instance_name,
                'user_phone' => $phone,
                'summary' => $summary,
                'description' => $description,
                'starts_at' => $start,
                'ends_at' => $end,
                'timezone' => $timezone,
                'google_calendar_id' => $calendarId !== '' ? $calendarId : null,
                'sync_status' => 'local',
                'sync_error' => null,
                'source' => 'bot',
            ]);

            $google = null;

            if ($calendarId !== '') {
                try {
                    $google = $this->calendar->createEvent(
                        $calendarId,
                        $summary,
                        $start->toIso8601String(),
                        $end->toIso8601String(),
                        $timezone,
                        $description,
                        $instance->google_credentials_path ?: null,
                    );

                    $appointment->update([
                        'google_event_id' => $google['id'] ?? null,
                        'google_html_link' => $google['html_link'] ?? null,
                        'sync_status' => 'synced',
                        'sync_error' => null,
                    ]);
                } catch (Throwable $e) {
                    Log::error('Calendar Google sync failed (local saved)', [
                        'error' => $e->getMessage(),
                        'instance' => $instance->instance_name,
                        'appointment_id' => (string) $appointment->getKey(),
                    ]);

                    $appointment->update([
                        'sync_status' => 'google_failed',
                        'sync_error' => $e->getMessage(),
                    ]);
                }
            }

            $this->bookingStates->updateState((string) $instance->instance_name, $phone, [
                'step' => 'COMPLETED',
                'service' => $summary,
                'date' => $start->toDateString(),
                'time' => $start->format('H:i'),
            ]);
            $this->bookingStates->markCompleted((string) $instance->instance_name, $phone);

            return [
                'ok' => true,
                'appointment_id' => (string) $appointment->getKey(),
                'sync_status' => $appointment->sync_status,
                'event' => $google,
                'message' => $google
                    ? 'Cita guardada en el sistema y en Google Calendar.'
                    : 'Cita guardada en el sistema'.($calendarId === '' ? ' (sin Google Calendar ID).' : ' (Google pendiente/falló).'),
            ];
        } catch (Throwable $e) {
            Log::error('Calendar tool failed', [
                'error' => $e->getMessage(),
                'instance' => $instance->instance_name,
            ]);

            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function buildSystemPrompt(WhatsappInstance $instance, $booking, string $timezone): string
    {
        $custom = trim((string) ($instance->system_prompt ?? ''));
        $base = $custom !== '' ? $custom : $this->defaultSystemPrompt($timezone);
        $stateJson = $this->bookingStates->toPromptJson($booking);

        return $base."\n\nEstado actual de la reserva (instance={$instance->instance_name}): {$stateJson}\n"
            ."Usa update_booking_state para persistir datos parciales. Respuestas muy breves (1-3 oraciones).";
    }

    protected function defaultSystemPrompt(string $timezone): string
    {
        $today = Carbon::now($timezone)->toDayDateTimeString();

        return <<<PROMPT
Eres un asistente de agendamiento por WhatsApp. Español, breve y directo.
Zona horaria: {$timezone}. Ahora: {$today}.
Cuando tengas asunto + fecha/hora, llama create_calendar_event.
Si falta dato, pregunta solo lo que falta (usa el Estado actual de la reserva).
Duración por defecto 30 min si no indican.
Si el horario está ocupado, informa y propone otro. No inventes confirmaciones.
PROMPT;
    }
}
