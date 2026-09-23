<?php

namespace App\Services\Whatsapp;

use App\Events\NotificacionToUser;
use App\Models\NotificationsModel;
use App\Models\User;
use App\Models\WhatsappAppointment;
use App\Models\WhatsappBookingState;
use App\Models\WhatsappInstance;
use App\Services\Telegram\AdminAssistantOrchestrator;
use App\Services\Whatsapp\Contracts\LlmChatClient;
use App\Support\NotificationLinkResolver;
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
        protected MimoClient $mimo,
        protected GoogleCalendarService $calendar,
        protected EvolutionApiClient $evolution,
        protected AdminAssistantOrchestrator $telegramAdmin,
    ) {}

    public function handleIncoming(string $instanceName, string $userPhone, string $text): ?string
    {
        $instance = WhatsappInstance::resolveActiveByEvolutionSession($instanceName);

        if (! $instance) {
            throw new RuntimeException("Instancia WhatsApp no registrada o inactiva: {$instanceName}");
        }

        // Clave de sesión = Evolution (compartida entre perfiles duplicados).
        $sessionKey = $instance->evolutionName();
        $phone = $this->evolution->normalizePhone($userPhone);
        $booking = $this->bookingStates->touch($sessionKey, $phone);

        $this->conversations->append($sessionKey, $phone, 'user', $text);

        if ($this->bookingStates->isBotPaused($booking)) {
            Log::info('WhatsApp bot paused; message stored without auto-reply', [
                'instance' => $sessionKey,
                'profile' => $instance->instance_name,
                'phone' => $phone,
            ]);

            return null;
        }

        $timezone = $instance->timezone ?? config('services.google_calendar.timezone');
        $this->bookingStates->backfillMissingFromTranscript($sessionKey, $phone);
        $booking = $this->bookingStates->find($sessionKey, $phone) ?? $booking;
        $staticPrompt = $this->buildStaticSystemPrompt($instance, (string) $timezone);
        $dynamicPrompt = $this->buildDynamicSystemPrompt(
            $instance,
            $booking->fresh() ?? $booking,
            (string) $timezone,
        );

        $messages = $this->conversations->toLlmMessages(
            $sessionKey,
            $phone,
            $staticPrompt,
            $dynamicPrompt,
        );
        $provider = $this->resolveProvider($instance);

        try {
            $loop = $this->runLlmLoop($messages, $instance, $sessionKey, $phone, $provider);
        } catch (Throwable $e) {
            if ($this->isLlmToolHistoryError($e)) {
                Log::warning('LLM history invalid; retrying with fresh context', [
                    'instance' => $sessionKey,
                    'profile' => $instance->instance_name,
                    'phone' => $phone,
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ]);

                $messages = [
                    ['role' => 'system', 'content' => $staticPrompt],
                    ['role' => 'system', 'content' => $dynamicPrompt],
                    ['role' => 'user', 'content' => $text],
                ];
                $loop = $this->runLlmLoop($messages, $instance, $sessionKey, $phone, $provider);
            } else {
                throw $e;
            }
        }

        $reply = (string) ($loop['reply'] ?? '');
        $bookingAfter = $this->bookingStates->find($sessionKey, $phone) ?? $booking;
        $reply = $this->ensureConfirmingReplyComplete($reply, $bookingAfter);
        $reply = $this->guardFalseBookingConfirmation(
            $reply,
            $bookingAfter,
            (bool) ($loop['calendar_ok'] ?? false),
            is_array($loop['calendar_failure'] ?? null) ? $loop['calendar_failure'] : null,
        );

        // Si durante el loop se pausó el bot (escalado), igual enviamos la cortesía del modelo.
        $this->conversations->append($sessionKey, $phone, 'assistant', $reply);
        $this->evolution->sendText($sessionKey, $phone, $reply);
        $this->bookingStates->touch($sessionKey, $phone);

        return $reply;
    }

    public function sendStaticReply(string $instanceName, string $userPhone, string $text): void
    {
        $instance = WhatsappInstance::resolveActiveByEvolutionSession($instanceName);
        $sessionKey = $instance?->evolutionName() ?: $instanceName;
        $phone = $this->evolution->normalizePhone($userPhone);
        $this->conversations->append($sessionKey, $phone, 'assistant', $text);
        $this->evolution->sendText($sessionKey, $phone, $text);
        $this->bookingStates->touch($sessionKey, $phone);
    }

    public function maybeSendWelcome(string $instanceName, string $userPhone): bool
    {
        $instance = WhatsappInstance::resolveActiveByEvolutionSession($instanceName);
        $sessionKey = $instance?->evolutionName() ?: $instanceName;
        $phone = $this->evolution->normalizePhone($userPhone);
        $state = $this->bookingStates->findOrCreate($sessionKey, $phone);

        if ($this->bookingStates->isBotPaused($state)) {
            return false;
        }

        $hours = (int) config('services.whatsapp.inactive_hours', 24);

        if (! $this->bookingStates->shouldSendWelcome($state, $hours)) {
            return false;
        }

        $welcome = (string) config('services.whatsapp.welcome_message');
        $this->sendStaticReply($sessionKey, $phone, $welcome);
        $this->bookingStates->markWelcomeSent($state->fresh() ?? $state);

        return true;
    }

    protected function resolveProvider(WhatsappInstance $instance): string
    {
        $provider = strtolower(trim((string) ($instance->ai_provider ?? 'deepseek')));

        return in_array($provider, ['deepseek', 'mimo'], true) ? $provider : 'deepseek';
    }

    protected function llmClient(string $provider): LlmChatClient
    {
        return $provider === 'mimo' ? $this->mimo : $this->deepSeek;
    }

    protected function isLlmToolHistoryError(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'tool_calls')
            || str_contains($msg, "role 'tool'")
            || str_contains($msg, 'tool messages');
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array{reply: string, calendar_ok: bool, calendar_failure: ?array<string, mixed>}
     */
    protected function runLlmLoop(
        array $messages,
        WhatsappInstance $instance,
        string $instanceName,
        string $phone,
        string $provider,
    ): array {
        $client = $this->llmClient($provider);
        $label = $provider === 'mimo' ? 'MiMo' : 'DeepSeek';
        $maxRounds = 3;
        $calendarOk = false;
        $calendarFailure = null;

        for ($round = 0; $round < $maxRounds; $round++) {
            $response = $client->chat($messages, withTools: true);
            $choice = $response['choices'][0]['message'] ?? null;

            if (! is_array($choice)) {
                throw new RuntimeException("Respuesta inválida de {$label}.");
            }

            $toolCalls = $choice['tool_calls'] ?? [];

            if ($toolCalls === []) {
                $content = trim((string) ($choice['content'] ?? ''));

                return [
                    'reply' => $content !== '' ? $content : '¿En qué más te puedo ayudar?',
                    'calendar_ok' => $calendarOk,
                    'calendar_failure' => $calendarFailure,
                ];
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

                if ($toolName === 'create_calendar_event') {
                    if (! empty($toolResult['ok'])) {
                        $calendarOk = true;
                        $calendarFailure = null;
                    } else {
                        $calendarFailure = $toolResult;
                    }
                }

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

        $final = $client->chat($messages, withTools: false);
        $content = trim((string) ($final['choices'][0]['message']['content'] ?? ''));

        return [
            'reply' => $content !== '' ? $content : 'Listo. Si necesitas algo más, aquí estoy.',
            'calendar_ok' => $calendarOk,
            'calendar_failure' => $calendarFailure,
        ];
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

        return match ($name) {
            'update_booking_state' => $this->toolUpdateBookingState($instance, $phone, $args),
            'check_availability' => $this->toolCheckAvailability($instance, $args),
            'reset_booking' => $this->toolResetBooking($instance, $phone, $args),
            'escalate_to_human' => $this->toolEscalateToHuman($instance, $phone, $args),
            'create_calendar_event' => $this->toolCreateCalendarEvent($instance, $phone, $args),
            'list_my_appointments' => $this->toolListMyAppointments($instance, $phone, $args),
            'cancel_appointment' => $this->toolCancelAppointment($instance, $phone, $args),
            default => ['ok' => false, 'error' => "Herramienta desconocida: {$name}"],
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolUpdateBookingState(WhatsappInstance $instance, string $phone, array $args): array
    {
        $state = $this->bookingStates->updateState(
            $instance->evolutionName(),
            $phone,
            $args,
        );

        $confirming = $this->bookingStates->hasRequiredBookingData($state)
            && $this->bookingStates->normalizeStage((string) $state->step) === WhatsappBookingState::STAGE_CONFIRMING;

        return [
            'ok' => true,
            'state' => json_decode($this->bookingStates->toPromptJson($state), true),
            'message' => $confirming
                ? 'Datos completos (CONFIRMING). En TU próxima respuesta al cliente DEBES pegar este resumen completo y pedir confirmación. No agendes aún:\n'.$this->formatBookingSummaryLines($state)
                : 'Estado actualizado.',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolCheckAvailability(WhatsappInstance $instance, array $args): array
    {
        $startIso = (string) ($args['start_iso'] ?? '');
        $endIso = (string) ($args['end_iso'] ?? '');
        $timezone = $instance->timezone ?: (string) config('services.google_calendar.timezone');
        $calendarId = $instance->google_calendar_id
            ?: (string) config('services.google_calendar.calendar_id');

        if ($startIso === '') {
            return ['ok' => false, 'error' => 'Falta start_iso.'];
        }

        try {
            $start = Carbon::parse($startIso, $timezone);
            $end = $endIso !== ''
                ? Carbon::parse($endIso, $timezone)
                : $start->copy()->addMinutes(30);

            if ($end->lessThanOrEqualTo($start)) {
                $end = $start->copy()->addMinutes(30);
            }

            $slotMinutes = max(15, (int) $start->diffInMinutes($end));
            $now = Carbon::now($timezone);
            if ($start->lt($now->copy()->startOfMinute())) {
                return [
                    'ok' => true,
                    'available' => false,
                    'reason' => 'past',
                    'message' => 'Ese horario ya pasó. No lo ofrezcas; pide una fecha/hora futuras.',
                    'requested' => $start->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY HH:mm'),
                ];
            }

            $localConflicts = $this->calendar->findLocalConflicts(
                $instance->evolutionName(),
                $start,
                $end,
            );

            $googleBusy = false;
            $googleError = null;
            if ($calendarId !== '' && ($instance->google_credentials_path || config('services.google_calendar.credentials_path'))) {
                try {
                    $googleBusy = $this->calendar->hasBusyConflict(
                        $calendarId,
                        $start,
                        $end,
                        $instance->google_credentials_path ?: null,
                    );
                } catch (Throwable $e) {
                    $googleError = $e->getMessage();
                    Log::warning('check_availability Google failed', [
                        'error' => $e->getMessage(),
                        'instance' => $instance->instance_name,
                    ]);
                }
            }

            $labels = collect($localConflicts)->map(function ($a) use ($timezone) {
                $from = optional($a->starts_at)->copy()->timezone($timezone)->format('H:i');
                $to = optional($a->ends_at)->copy()->timezone($timezone)->format('H:i');

                return trim(($a->summary ?? 'Cita')." ({$from}–{$to})");
            })->take(3)->implode('; ');

            if ($localConflicts !== [] || $googleBusy) {
                $nextSlots = $this->suggestNextOpenSlots(
                    $instance,
                    $start,
                    $slotMinutes,
                    $calendarId,
                    maxSuggestions: 3,
                );

                $nextLabels = collect($nextSlots)->pluck('label')->implode('; ');

                return [
                    'ok' => true,
                    'available' => false,
                    'reason' => $googleBusy && $localConflicts === [] ? 'google_busy' : 'slot_busy',
                    'conflicts' => $labels,
                    'requested' => $start->locale('es')->isoFormat('dddd D [de] MMMM HH:mm'),
                    'next_slots' => $nextSlots,
                    'message' => 'OCUPADO: ya hay una cita/agenda en ese horario'
                        .($labels !== '' ? " ({$labels})" : '')
                        .'. Di EXPLÍCITAMENTE al cliente que a esa hora ya hay una cita agendada (no digas "no pude verificar"). '
                        .'Ofrece el siguiente turno libre'
                        .($nextLabels !== '' ? ": {$nextLabels}" : ' (busca otra hora y vuelve a check_availability)')
                        .'. No reinicies nombre/servicio; solo actualiza date/time si elige otro horario.'
                        .$this->instanceSlotBusyPolicySuffix($instance),
                ];
            }

            // Google falló pero local libre: no inventes "no pude verificar" como si estuviera libre al 100%.
            if ($googleError !== null) {
                $nextSlots = $this->suggestNextOpenSlots(
                    $instance,
                    $start,
                    $slotMinutes,
                    $calendarId,
                    maxSuggestions: 2,
                );

                return [
                    'ok' => true,
                    'available' => false,
                    'reason' => 'verify_uncertain',
                    'google_error' => $googleError,
                    'requested' => $start->locale('es')->isoFormat('dddd D [de] MMMM HH:mm'),
                    'next_slots' => $nextSlots,
                    'message' => 'No se pudo consultar Google Calendar, así que NO affirmes que el horario está libre. '
                        .'Di que ese horario no se pudo confirmar en agenda y propone alternativas '
                        .(collect($nextSlots)->pluck('label')->implode('; ') ?: 'u otra hora')
                        .'.'
                        .$this->instanceSlotBusyPolicySuffix($instance),
                ];
            }

            return [
                'ok' => true,
                'available' => true,
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'message' => 'Horario libre según sistema'.($calendarId !== '' ? ' y Google' : '').'. Puedes ofrecerlo; aún NO digas que la cita está confirmada hasta create_calendar_event con ok=true.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'message' => 'Error técnico al verificar. NO digas que está libre. Propón otra hora.'
                    .$this->instanceSlotBusyPolicySuffix($instance),
            ];
        }
    }

    /**
     * Política extra solo si la instancia la define (ej. SAC: urgencia con costo extra).
     */
    protected function instanceSlotBusyPolicySuffix(WhatsappInstance $instance): string
    {
        $policy = trim((string) ($instance->slot_busy_policy ?? ''));
        if ($policy === '') {
            return '';
        }

        return ' Política de esta instancia: '.$policy;
    }

    /**
     * @return list<array{start: string, end: string, label: string}>
     */
    protected function suggestNextOpenSlots(
        WhatsappInstance $instance,
        Carbon $from,
        int $slotMinutes,
        string $calendarId,
        int $maxSuggestions = 3,
    ): array {
        $timezone = (string) ($instance->timezone ?: config('services.google_calendar.timezone'));
        $cursor = $from->copy()->addMinutes($slotMinutes);
        $limit = $from->copy()->addDays(2)->endOfDay();
        $found = [];
        $guard = 0;

        while (count($found) < $maxSuggestions && $cursor->lt($limit) && $guard < 96) {
            $guard++;
            $slotEnd = $cursor->copy()->addMinutes($slotMinutes);

            // Evitar madrugada absurda (antes de 7 o después de 21) salvo que el negocio sea 24/7;
            // igual permitimos proponer; el cliente/urgencia decide.
            $localConflicts = $this->calendar->findLocalConflicts(
                $instance->evolutionName(),
                $cursor,
                $slotEnd,
            );

            $busy = $localConflicts !== [];
            if (! $busy && $calendarId !== '') {
                try {
                    $busy = $this->calendar->hasBusyConflict(
                        $calendarId,
                        $cursor,
                        $slotEnd,
                        $instance->google_credentials_path ?: null,
                    );
                } catch (Throwable) {
                    // Si Google falla, aún podemos sugerir según agenda local.
                    $busy = false;
                }
            }

            if (! $busy && $cursor->gte(Carbon::now($timezone))) {
                $found[] = [
                    'start' => $cursor->toIso8601String(),
                    'end' => $slotEnd->toIso8601String(),
                    'label' => $cursor->locale('es')->isoFormat('dddd D [de] MMMM HH:mm')
                        .'–'.$slotEnd->format('H:i'),
                ];
            }

            $cursor->addMinutes($slotMinutes);
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolResetBooking(WhatsappInstance $instance, string $phone, array $args): array
    {
        $state = $this->bookingStates->resetToReception(
            $instance->evolutionName(),
            $phone,
            resumeBot: true,
        );

        return [
            'ok' => true,
            'reason' => (string) ($args['reason'] ?? 'cancel'),
            'state' => json_decode($this->bookingStates->toPromptJson($state), true),
            'message' => 'Datos limpiados. Volviste a RECEPTION. Atiende como en etapa 1.',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolEscalateToHuman(WhatsappInstance $instance, string $phone, array $args): array
    {
        $reason = (string) ($args['reason'] ?? 'complex_question');
        $detail = isset($args['detail']) ? trim((string) $args['detail']) : null;

        $this->bookingStates->pauseBot(
            $instance->evolutionName(),
            $phone,
            $reason,
            $detail,
        );

        $this->notifyStaffEscalation($instance, $phone, $reason, $detail);

        $courtesy = (string) config(
            'services.whatsapp.escalation_courtesy',
            'Déjame revisar ese detalle específico con el equipo para darte la información exacta. En un momento te confirmo por aquí mismo.',
        );

        return [
            'ok' => true,
            'paused' => true,
            'reason' => $reason,
            'instruction' => 'Responde al cliente SOLO con un mensaje de cortesía similar a: "'.$courtesy.'". Prohibido decir que transfieres, que eres un bot o que un humano tomará el chat.',
            'suggested_reply' => $courtesy,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolCreateCalendarEvent(WhatsappInstance $instance, string $phone, array $args): array
    {
        $booking = $this->bookingStates->findOrCreate($instance->evolutionName(), $phone);
        $stage = $this->bookingStates->normalizeStage((string) ($booking->step ?? ''));

        if ($stage !== WhatsappBookingState::STAGE_CONFIRMING) {
            return [
                'ok' => false,
                'error' => 'Aún no estás en CONFIRMING.',
                'message' => 'No agendes todavía. Completa datos y pide confirmación del cliente primero.',
                'stage' => $stage,
            ];
        }

        if (! $this->bookingStates->hasRequiredBookingData($booking)) {
            return [
                'ok' => false,
                'error' => 'Faltan datos obligatorios.',
                'message' => 'Vuelve a COLLECTING y pide los datos pendientes (nombre, servicio, fecha, hora).',
                'state' => json_decode($this->bookingStates->toPromptJson($booking), true),
            ];
        }

        $confirmed = filter_var($args['client_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $confirmed) {
            return [
                'ok' => false,
                'error' => 'Cliente no confirmó.',
                'message' => 'Muestra el resumen y espera un sí explícito antes de llamar esta herramienta con client_confirmed=true.',
            ];
        }

        $summary = trim((string) ($args['summary'] ?? $booking->service ?? 'Cita'));
        $startIso = (string) ($args['start_iso'] ?? '');
        $endIso = (string) ($args['end_iso'] ?? '');
        $description = isset($args['description'])
            ? (string) $args['description']
            : trim(implode("\n", array_filter([
                $booking->name ? 'Cliente: '.$booking->name : null,
                'Tel: '.$phone,
                $booking->location ? 'Lugar: '.$booking->location : null,
                $booking->notes ? 'Notas: '.$booking->notes : null,
            ])));
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

            $now = Carbon::now($timezone);
            if ($start->lt($now->copy()->startOfMinute())) {
                return [
                    'ok' => false,
                    'error' => 'Fecha/hora en el pasado.',
                    'today' => $now->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY HH:mm'),
                    'requested' => $start->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY HH:mm'),
                    'message' => 'No se puede agendar en el pasado. Pide una fecha y hora futuras.',
                ];
            }

            $localConflicts = $this->calendar->findLocalConflicts(
                $instance->evolutionName(),
                $start,
                $end,
            );

            $googleBusy = false;
            if ($calendarId !== '' && ($instance->google_credentials_path || config('services.google_calendar.credentials_path'))) {
                try {
                    $googleBusy = $this->calendar->hasBusyConflict(
                        $calendarId,
                        $start,
                        $end,
                        $instance->google_credentials_path ?: null,
                    );
                } catch (Throwable $e) {
                    Log::warning('Conflict check Google failed', [
                        'error' => $e->getMessage(),
                        'instance' => $instance->instance_name,
                    ]);
                }
            }

            if ($localConflicts !== [] || $googleBusy) {
                $friction = $this->bookingStates->incrementFriction(
                    $instance->evolutionName(),
                    $phone,
                );

                $labels = collect($localConflicts)->map(function ($a) {
                    $from = optional($a->starts_at)->format('Y-m-d H:i');
                    $to = optional($a->ends_at)->format('H:i');

                    return trim(($a->summary ?? 'Cita')." ($from–$to)");
                })->take(3)->implode('; ');

                $threshold = (int) config('services.whatsapp.friction_escalate_after', 3);
                if ($friction >= $threshold) {
                    $this->toolEscalateToHuman($instance, $phone, [
                        'reason' => 'loop',
                        'detail' => 'Horario no disponible repetido ('.$friction.' intentos). '.($labels ?: 'Google busy'),
                    ]);

                    return [
                        'ok' => false,
                        'error' => 'Horario ocupado; escalado por bucle.',
                        'friction_count' => $friction,
                        'escalated' => true,
                        'message' => 'El horario sigue sin estar disponible tras varios intentos. Usa la cortesía de revisión (bot pausado). No digas que transfieres.',
                    ];
                }

                return [
                    'ok' => false,
                    'error' => $googleBusy ? 'Horario ocupado en Google Calendar.' : 'Horario ocupado en el sistema.',
                    'conflicts' => $labels,
                    'friction_count' => $friction,
                    'message' => 'Ese horario NO está disponible'.($labels !== '' ? ": {$labels}" : '').'. PROHIBIDO decir que la cita quedó confirmada. Informa que el horario está ocupado, ofrece otra opción y usa check_availability antes de proponerla. Si insiste 3 veces, escalate_to_human.',
                ];
            }

            $appointment = WhatsappAppointment::query()->create([
                'instance_name' => $instance->evolutionName(),
                'user_phone' => $phone,
                'summary' => $summary,
                'description' => $description !== '' ? $description : null,
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
                        $description !== '' ? $description : null,
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

            $this->bookingStates->updateState($instance->evolutionName(), $phone, [
                'step' => WhatsappBookingState::STAGE_COMPLETED,
                'service' => $summary,
                'date' => $start->toDateString(),
                'time' => $start->format('H:i'),
            ]);
            $this->bookingStates->markCompleted($instance->evolutionName(), $phone);

            return [
                'ok' => true,
                'appointment_id' => (string) $appointment->getKey(),
                'sync_status' => $appointment->sync_status,
                'event' => $google,
                'message' => 'REGISTRO EXITOSO (ok=true). SOLO AHORA puedes decir al cliente que su cita quedó confirmada. Agradece y da indicaciones breves de llegada/pago si constan en el catálogo.'
                    .($google ? '' : ($calendarId === '' ? ' (sin Google Calendar ID).' : ' (Google pendiente/falló; igual quedó en el sistema).')),
            ];
        } catch (Throwable $e) {
            Log::error('Calendar tool failed', [
                'error' => $e->getMessage(),
                'instance' => $instance->instance_name,
            ]);

            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'message' => 'Falló el registro. PROHIBIDO decir que quedó confirmada. Pide disculpas y ofrece reintentar con otro horario.',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolListMyAppointments(WhatsappInstance $instance, string $phone, array $args): array
    {
        $tz = (string) ($instance->timezone ?: config('services.google_calendar.timezone', 'America/Merida'));
        $pastHours = max(0, min(48, (int) ($args['include_past_hours'] ?? 0)));
        $from = Carbon::now($tz)->subHours($pastHours);

        $rows = WhatsappAppointment::query()
            ->where('instance_name', $instance->evolutionName())
            ->where('user_phone', $phone)
            ->where('starts_at', '>=', $from)
            ->orderBy('starts_at')
            ->limit(20)
            ->get();

        $items = $rows->map(function (WhatsappAppointment $a) use ($tz) {
            return [
                'id' => (string) $a->getKey(),
                'summary' => (string) ($a->summary ?? ''),
                'starts_at' => $a->starts_at?->copy()->timezone($tz)->format('Y-m-d H:i'),
                'ends_at' => $a->ends_at?->copy()->timezone($tz)->format('Y-m-d H:i'),
                'day_label' => $a->starts_at
                    ? $a->starts_at->copy()->timezone($tz)->locale('es')->isoFormat('dddd D [de] MMMM HH:mm')
                    : null,
            ];
        })->values()->all();

        return [
            'ok' => true,
            'count' => count($items),
            'appointments' => $items,
            'message' => $items === []
                ? 'No hay citas futuras de este número. No inventes ninguna.'
                : 'Muestra estas citas al cliente (servicio + día/hora). Si quiere cancelar, pide cuál y luego confirmación explícita antes de cancel_appointment.',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolCancelAppointment(WhatsappInstance $instance, string $phone, array $args): array
    {
        $confirmed = filter_var($args['client_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $confirmed) {
            return [
                'ok' => false,
                'error' => 'Sin confirmación del cliente.',
                'message' => 'NO cancelaste nada. Primero resume la cita y pide un SÍ explícito; luego llama con client_confirmed=true.',
            ];
        }

        $id = trim((string) ($args['appointment_id'] ?? ''));
        if ($id === '') {
            return [
                'ok' => false,
                'error' => 'Falta appointment_id.',
                'message' => 'Usa list_my_appointments y el id exacto. No cancelaste nada.',
            ];
        }

        $sessionKey = $instance->evolutionName();
        $apt = WhatsappAppointment::query()
            ->where('instance_name', $sessionKey)
            ->find($id);

        if (! $apt) {
            return [
                'ok' => false,
                'error' => 'Cita no encontrada.',
                'message' => 'Ese id no existe en esta agenda. Vuelve a list_my_appointments. No cancelaste nada.',
            ];
        }

        // Solo el dueño del hilo WhatsApp puede cancelar su propia cita.
        $owner = preg_replace('/\D+/', '', (string) ($apt->user_phone ?? '')) ?? '';
        $caller = preg_replace('/\D+/', '', $phone) ?? $phone;
        if ($owner === '' || $owner !== $caller) {
            Log::warning('WhatsApp cancel blocked: phone mismatch', [
                'instance' => $sessionKey,
                'appointment_id' => $id,
                'owner' => $owner,
                'caller' => $caller,
            ]);

            return [
                'ok' => false,
                'error' => 'La cita no pertenece a este número.',
                'message' => 'PROHIBIDO cancelar. No digas que se canceló. Ofrece listar sus citas o escalate_to_human.',
            ];
        }

        $tz = (string) ($apt->timezone ?: $instance->timezone ?: config('services.google_calendar.timezone'));
        $summary = (string) ($apt->summary ?? 'Cita');
        $when = $apt->starts_at
            ? $apt->starts_at->copy()->timezone($tz)->locale('es')->isoFormat('dddd D [de] MMMM HH:mm')
            : '(sin fecha)';

        $googleNote = null;
        $eventId = (string) ($apt->google_event_id ?? '');
        $calendarId = (string) ($apt->google_calendar_id ?: $instance->google_calendar_id ?: '');

        if ($eventId !== '' && $calendarId !== '') {
            try {
                $this->calendar->deleteEvent(
                    $calendarId,
                    $eventId,
                    $instance->google_credentials_path ?: null,
                );
            } catch (Throwable $e) {
                Log::warning('WhatsApp cancel: Google delete failed', [
                    'appointment_id' => $id,
                    'error' => $e->getMessage(),
                ]);
                $googleNote = $e->getMessage();
            }
        }

        $apt->delete();

        // Limpia embudo si coincidía con la cita cancelada.
        $this->bookingStates->resetToReception($sessionKey, $phone, resumeBot: true);

        Log::info('WhatsApp appointment cancelled by bot', [
            'instance' => $sessionKey,
            'phone' => $phone,
            'appointment_id' => $id,
            'summary' => $summary,
            'when' => $when,
            'confirm_summary' => $args['confirm_summary'] ?? null,
            'google_error' => $googleNote,
        ]);

        return [
            'ok' => true,
            'cancelled' => [
                'id' => $id,
                'summary' => $summary,
                'when' => $when,
            ],
            'google_error' => $googleNote,
            'message' => $googleNote
                ? "Cita cancelada en el sistema ({$summary} — {$when}). Google pudo fallar: avisa que quedó cancelada aquí y el equipo revisa el calendario."
                : "Cita cancelada correctamente ({$summary} — {$when}). Ahora sí puedes confirmarlo al cliente.",
        ];
    }

    protected function notifyStaffEscalation(
        WhatsappInstance $instance,
        string $phone,
        string $reason,
        ?string $detail,
    ): void {
        $business = trim((string) ($instance->business_name ?? '')) ?: (string) $instance->instance_name;
        $reasonLabel = match ($reason) {
            'request_human' => 'Solicitud de persona',
            'loop' => 'Bucle / requerimiento no resuelto',
            'out_of_catalog' => 'Duda fuera de catálogo',
            default => 'Duda no resuelta / solicitud compleja',
        };

        $message = sprintf(
            'Intervención requerida para el cliente +%s en la instancia %s. Razón: %s%s',
            ltrim($phone, '+'),
            $business,
            $reasonLabel,
            $detail ? ' — '.$detail : '',
        );

        $linkDefs = [
            [
                'label' => 'Ver conversación',
                'route' => 'whatsapp.conversations.show',
                'params' => [
                    'instance' => (string) $instance->instance_name,
                    'phone' => $phone,
                ],
            ],
        ];

        foreach ($this->staffUserIdsForWhatsapp() as $userId) {
            try {
                NotificationsModel::create([
                    'user_id' => $userId,
                    'message' => $message,
                    'item_ids' => [(string) $instance->instance_name, $phone],
                    'is_read' => false,
                    'read_at' => null,
                    'links' => NotificationLinkResolver::resolve($linkDefs),
                ]);

                NotificacionToUser::dispatch(
                    message: $message,
                    userId: $userId,
                    itemIds: [(string) $instance->instance_name, $phone],
                    links: $linkDefs,
                    currentPaths: ['/whatsapp/conversations'],
                    meta: [
                        'navigate' => false,
                        'whatsapp_escalation' => true,
                    ],
                );
            } catch (Throwable $e) {
                Log::warning('WhatsApp escalation notification failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $alertPhone = trim((string) config('services.whatsapp.escalation_phone', ''));
        $alertInstance = trim((string) (
            config('services.whatsapp.escalation_instance')
            ?: $instance->evolutionName()
        ));

        if ($alertPhone !== '' && $alertInstance !== '') {
            try {
                $this->evolution->sendText($alertInstance, $alertPhone, $message);
            } catch (Throwable $e) {
                Log::warning('WhatsApp escalation SMS/WA to staff failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $this->telegramAdmin->notifyEscalation($instance, $message);
        } catch (Throwable $e) {
            Log::warning('Telegram escalation to staff failed', [
                'instance' => $instance->instance_name,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('WhatsApp conversation escalated', [
            'instance' => $instance->instance_name,
            'phone' => $phone,
            'reason' => $reason,
            'detail' => $detail,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function staffUserIdsForWhatsapp(): array
    {
        $modules = ['whatsapp', 'whatsapp.conversations', 'whatsapp.calendar'];

        return User::query()
            ->where('status', 1)
            ->get()
            ->filter(function (User $user) use ($modules) {
                foreach ($modules as $module) {
                    if ($user->hasPermission($module)) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn (User $u) => (string) $u->getKey())
            ->unique()
            ->values()
            ->all();
    }

    protected function buildStaticSystemPrompt(WhatsappInstance $instance, string $timezone): string
    {
        $businessName = trim((string) ($instance->business_name ?? '')) ?: (string) $instance->instance_name;
        $parts = [$this->transversalRules($businessName, $timezone)];

        $catalog = $this->formatBusinessCatalog($instance);
        if ($catalog !== '') {
            $parts[] = $catalog;
        }

        $custom = trim((string) ($instance->system_prompt ?? ''));
        if ($custom !== '') {
            $parts[] = "Políticas / instrucciones adicionales del negocio:\n{$custom}";
        }

        return implode("\n\n", $parts);
    }

    protected function buildDynamicSystemPrompt(WhatsappInstance $instance, $booking, string $timezone): string
    {
        $now = Carbon::now($timezone)->locale('es');
        $todayLabel = $now->isoFormat('dddd D [de] MMMM [de] YYYY');
        $timeLabel = $now->format('H:i');
        $businessName = trim((string) ($instance->business_name ?? '')) ?: (string) $instance->instance_name;
        $stage = $this->bookingStates->normalizeStage((string) ($booking->step ?? ''));
        $stateJson = $this->bookingStates->toPromptJson($booking);
        $stagePrompt = $this->stagePrompt($stage, $businessName, $todayLabel, $timezone, $booking);
        $retention = $this->bookingRetentionReminder($booking);

        return <<<PROMPT
Contexto temporal:
Hoy es {$todayLabel}. Hora actual: {$timeLabel}. Zona: {$timezone}. Año: {$now->year}.
Prohibido aceptar fechas pasadas. Si el cliente menciona un mes/día ya transcurrido, corrige con amabilidad.
Verifica que el día de la semana coincida con la fecha.

Estado persistido: {$stateJson}

{$retention}

{$stagePrompt}
PROMPT;
    }

    /**
     * Evita que el modelo “reinicie” el embudo tras check_availability u otros tools.
     */
    protected function bookingRetentionReminder($booking): string
    {
        if (! $booking) {
            return '';
        }

        $filled = [];
        $missing = [];
        $map = [
            'nombre' => trim((string) ($booking->name ?? '')),
            'servicio' => trim((string) ($booking->service ?? '')),
            'fecha' => trim((string) ($booking->date ?? '')),
            'hora' => trim((string) ($booking->time ?? '')),
            'ubicacion' => trim((string) ($booking->location ?? '')),
            'notas' => trim((string) ($booking->notes ?? '')),
        ];

        foreach ($map as $label => $value) {
            if ($value !== '') {
                $filled[] = "- {$label}: {$value}";
            } else {
                $missing[] = $label;
            }
        }

        $filledBlock = $filled !== []
            ? "YA GUARDADO (PROHIBIDO volver a pedirlo):\n".implode("\n", $filled)
            : 'YA GUARDADO: (nada aún).';
        $missingBlock = $missing !== []
            ? 'AÚN FALTA (pide SOLO esto, de a uno): '.implode(', ', $missing)
            : 'AÚN FALTA: nada — pasa a resumen CONFIRMING / create_calendar_event según etapa.';

        return <<<PROMPT
Retención de datos (anti-amnesia — CRÍTICO):
Tras check_availability u otras tools, NO reinicies la conversación.
{$filledBlock}
{$missingBlock}
Si el historial menciona nombre/servicio y el estado dice Pendiente, llama update_booking_state INMEDIATO con esos valores antes de preguntar otra vez.
PROMPT;
    }

    protected function transversalRules(string $businessName, string $timezone): string
    {
        return <<<PROMPT
Eres el asistente oficial de {$businessName} por WhatsApp.
Zona horaria: {$timezone}. Español, cordial, natural. En general 2–3 oraciones; en CONFIRMING puedes usar un poco más para listar el resumen completo.
No inventes precios, servicios, horarios ni políticas fuera del catálogo/políticas del negocio.
Nunca dejes un mensaje a medias (por ejemplo "te confirmo:" sin listar los datos). Si vas a mostrar un resumen, inclúyelo completo en el mismo mensaje.

Identidad del cliente:
Si el usuario proporciona un nuevo nombre o corrige su identidad durante la conversación, sobrescribe inmediatamente cualquier nombre anterior con update_booking_state (campo name) y usa solo el nombre nuevo en adelante.
En el MISMO turno en que el cliente da su nombre, DEBES llamar update_booking_state con name (no esperes al final).

Paquete / tipo de sesión (no repetir preguntas):
Si el tipo de sesión o paquete ya fue seleccionado o mencionado por el usuario en el historial inmediato (o servicio_producto en el estado NO es "Pendiente"), NO vuelvas a preguntar qué sesión/paquete quiere. Guárdalo de inmediato en service con update_booking_state y pide solo lo que falte.
Si TÚ ya propusiste un servicio (ej. "Voy a agendar Consulta General") y el cliente no lo corrigió, guarda service=ese valor de inmediato.

Anti-amnesia tras horarios:
Cuando check_availability falle o el cliente cambie la hora, ACTUALIZA solo date/time con update_booking_state. NUNCA borres name/service/notes ni uses clear_fields. NUNCA vuelvas a pedir nombre o servicio si ya estaban en el estado o en el historial reciente.

Ubicación:
Cuando el cliente indique lugar, conserva la localidad/ciudad especificada (Cancún, Puerto Morelos, Playa del Carmen u otra) junto con el tipo de sitio. Guárdalo en location, por ejemplo "Playa pública, Puerto Morelos". Nunca resumas solo como "Playa pública" si ya dijo la ciudad.

Disponibilidad y agendado (anti-alucinación — CRÍTICO):
1) No inventes ni asumas que un horario está libre. Antes de ofrecerlo como disponible, llama check_availability y espera el resultado.
2) Nunca digas que una cita quedó "confirmada", "agendada", "reservada" o "lista" a menos que create_calendar_event haya respondido ok=true en ESTE turno.
3) Si available=false / ocupado: di que YA HAY una cita agendada a esa hora (no digas "no pude verificar"). Propón el siguiente turno de next_slots (verifica con check_availability antes de afirmar que está libre). Si la instancia define una política extra en el resultado de la tool (campo/mensaje de política), síguela; si no, solo ofrece otro horario.
4) Pedir confirmación del resumen al cliente NO es lo mismo que haber registrado la cita. Primero tool ok=true, después mensaje de éxito.

Cancelación de citas (MUY PRECAVIDO — CRÍTICO):
1) reset_booking NO cancela citas del calendario; solo limpia el embudo del chat.
2) Si el cliente quiere cancelar una cita ya agendada: primero list_my_appointments. Muestra al cliente SOLO sus citas (servicio, fecha, hora, id corto).
3) Si hay varias, pregunta CUÁL. Si no hay ninguna futura, dilo y no inventes.
4) Antes de cancel_appointment: resume la cita elegida y pide confirmación explícita ("¿Confirmas que cancelo [servicio] el [fecha] a las [hora]? Responde SÍ").
5) Llama cancel_appointment SOLO con client_confirmed=true tras ese SÍ, con el appointment_id exacto. Nunca canceles por "tal vez", "creo que", ambigüedad, ni más de una cita en el mismo turno.
6) Nunca digas que quedó cancelada hasta recibir ok=true de cancel_appointment. Si ok=false, explica y no inventes el borrado.
7) Si duda o el caso es conflictivo (queja, amenaza, no identifica la cita): escalate_to_human en lugar de cancelar.

Herramientas:
- update_booking_state: guarda etapa y datos (name, service, date, time, location). name/service nuevos sobrescriben; location debe incluir ciudad si la dijo.
- check_availability: consulta ocupación real (sistema + Google) antes de ofrecer un horario.
- reset_booking: limpia embudo del chat → RECEPTION. NO borra citas del calendario.
- create_calendar_event: SOLO en CONFIRMING con client_confirmed=true. El éxito al cliente depende de ok=true.
- list_my_appointments: lista citas futuras de ESTE teléfono.
- cancel_appointment: elimina una cita (sistema+Google) SOLO con id correcto + client_confirmed=true tras confirmación explícita.
- escalate_to_human: dudas fuera de catálogo, quejas complejas, pide persona, o bucle 3 veces. NUNCA digas que transfieres a un humano ni que eres un bot limitado.

Reglas transversales:
1) Cambio de opinión en cualquier momento: si estaba agendando y pregunta algo general (ej. estacionamiento), responde la duda con el catálogo y retoma suavemente el agendamiento.
2) Si corrige un dato ("mejor a las 5", "cambiemos el servicio", "me llamo X no Y", "mejor en Cancún"), actualízalo de inmediato con update_booking_state / clear_fields.
3) Derivación silenciosa: ante escalado, usa escalate_to_human y responde solo con cortesía de "revisar con el equipo / te confirmo por aquí".
PROMPT;
    }

    protected function stagePrompt(
        string $stage,
        string $businessName,
        string $todayLabel,
        string $timezone,
        $booking = null,
    ): string {
        return match ($stage) {
            WhatsappBookingState::STAGE_COLLECTING => <<<PROMPT
ETAPA ACTUAL: 2 RECOLECTANDO (COLLECTING)
Hoy es {$todayLabel} en {$timezone}. Gestionas una solicitud para {$businessName}.
Revisa el Estado persistido y el bloque YA GUARDADO: pide SOLO lo marcado como Pendiente / AÚN FALTA.
PROHIBIDO volver a preguntar nombre, servicio, mascota u otros datos ya anotados, aunque acabes de usar check_availability.
Si servicio_producto ya tiene valor (o el cliente/tú ya mencionaron el servicio en el chat), NO preguntes de nuevo; guárdalo con update_booking_state si aún está Pendiente.
Si el cliente cambia un dato previo (incluido el nombre o la ciudad), acéptalo, sobrescribe con update_booking_state y continúa.
Ubicación: si da tipo de lugar + ciudad (ej. playa en Puerto Morelos), guarda location como "Playa pública, Puerto Morelos" (incluye siempre la localidad).
Si propone un horario concreto, llama check_availability antes de decir que está libre; luego actualiza date/time SIN tocar name/service/notes.
Cuando nombre, servicio, fecha y hora estén válidos, el sistema pasará a CONFIRMING: muestra el resumen completo (incluye ubicación si existe) y pide confirmación (aún no agendes).
PROMPT,
            WhatsappBookingState::STAGE_CONFIRMING => $this->confirmingStagePrompt($businessName, $booking),
            WhatsappBookingState::STAGE_COMPLETED => <<<PROMPT
ETAPA ACTUAL: COMPLETADO
La cita ya quedó registrada. Atiende dudas breves. Si quiere una nueva cita, usa reset_booking o step=COLLECTING e inicia recolección de nuevo.
PROMPT,
            default => <<<PROMPT
ETAPA ACTUAL: 1 RECEPCIÓN (RECEPTION)
Objetivo: recibir al cliente, resolver dudas generales del catálogo y detectar si desea comprar, agendar o cotizar.
Responde breve y cordial. Si hay intención ("quiero apartar", "me interesa una cita", "quiero comprarlo", o da fecha/producto/paquete), invita a iniciar el proceso: guarda de inmediato en update_booking_state lo que ya dijo (service, location con ciudad si aplica), pide solo el primer dato que falte y usa step=COLLECTING (e intent).
Si solo pregunta precios/ubicación/características: quédate en RECEPTION.
Si la duda excede el catálogo/políticas: escalate_to_human (out_of_catalog). No inventes.
PROMPT,
        };
    }

    protected function confirmingStagePrompt(string $businessName, $booking): string
    {
        $summaryBlock = $this->formatBookingSummaryLines($booking);

        return <<<PROMPT
ETAPA ACTUAL: 3 CONFIRMANDO (CONFIRMING) — {$businessName}
OBLIGATORIO: en tu respuesta al cliente incluye TODOS estos datos (no digas "aquí tienes el resumen" sin pegarlos):
{$summaryBlock}
Luego pregunta si confirma para asegurar su lugar. Eso NO agenda todavía.
En el resumen, si hay ubicación, debe verse completa (tipo + ciudad, ej. "Playa pública, Puerto Morelos"), nunca solo el tipo genérico.
Si el cliente confirma explícitamente (sí, correcto, adelante, confirma, agéndalo):
1) Opcional pero recomendado: check_availability con ese horario.
2) Llama create_calendar_event con client_confirmed=true.
3) SOLO si la tool responde ok=true, di que quedó confirmada. Si ok=false (ocupado u error), dilo con claridad y ofrece otro horario — nunca inventes el éxito.
Si pide el resumen otra vez: vuelve a pegarlo completo. No agendes.
Si quiere cambiar algo (incluido el nombre, el paquete o la ciudad): update_booking_state y ajusta.
PROMPT;
    }

    protected function formatBookingSummaryLines($booking): string
    {
        if (! $booking) {
            return "- Cliente: (pendiente)\n- Servicio: (pendiente)\n- Fecha: (pendiente)\n- Hora: (pendiente)\n- Lugar: (pendiente)";
        }

        $name = trim((string) ($booking->name ?? '')) ?: '(pendiente)';
        $service = trim((string) ($booking->service ?? '')) ?: '(pendiente)';
        $date = trim((string) ($booking->date ?? '')) ?: '(pendiente)';
        $time = trim((string) ($booking->time ?? '')) ?: '(pendiente)';
        $location = trim((string) ($booking->location ?? '')) ?: '(pendiente)';

        $dateLabel = $date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                $dateLabel = Carbon::parse($date)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
            } catch (Throwable) {
                $dateLabel = $date;
            }
        }

        return "- Cliente: {$name}\n- Servicio / paquete: {$service}\n- Fecha: {$dateLabel}\n- Hora: {$time}\n- Lugar: {$location}";
    }

    /**
     * Si el modelo corta el mensaje de confirmación, completa con el resumen persistido.
     */
    protected function ensureConfirmingReplyComplete(string $reply, $booking): string
    {
        if (! $booking) {
            return $reply;
        }

        $stage = $this->bookingStates->normalizeStage((string) ($booking->step ?? ''));
        if ($stage !== WhatsappBookingState::STAGE_CONFIRMING) {
            return $reply;
        }

        if (! $this->bookingStates->hasRequiredBookingData($booking)) {
            return $reply;
        }

        $trimmed = trim($reply);
        $looksIncomplete = $trimmed === ''
            || str_ends_with($trimmed, ':')
            || str_ends_with($trimmed, '...')
            || preg_match('/(resumen|confirmo|datos|sesión|cita)\s*:?\s*$/iu', $trimmed) === 1;

        $hasName = filled($booking->name) && str_contains(mb_strtolower($trimmed), mb_strtolower((string) $booking->name));
        $hasService = filled($booking->service) && str_contains(mb_strtolower($trimmed), mb_strtolower((string) $booking->service));
        $hasDate = filled($booking->date) && (
            str_contains($trimmed, (string) $booking->date)
            || str_contains($trimmed, (string) $booking->time)
        );

        if (! $looksIncomplete && $hasName && ($hasService || $hasDate)) {
            return $reply;
        }

        $block = $this->formatBookingSummaryLines($booking);
        $intro = $trimmed !== '' && ! $looksIncomplete
            ? rtrim($trimmed)
            : 'Te confirmo los datos de tu solicitud:';

        return $intro."\n".$block."\n¿Todo correcto para asegurar tu lugar?";
    }

    /**
     * Evita alucinaciones de "cita confirmada" cuando la tool falló o no se llamó.
     *
     * @param  array<string, mixed>|null  $calendarFailure
     */
    protected function guardFalseBookingConfirmation(
        string $reply,
        $booking,
        bool $calendarOk,
        ?array $calendarFailure,
    ): string {
        if ($calendarOk) {
            return $reply;
        }

        $stage = $booking
            ? $this->bookingStates->normalizeStage((string) ($booking->step ?? ''))
            : WhatsappBookingState::STAGE_RECEPTION;

        // Si ya estaba COMPLETED de un turno anterior, no reescribimos mensajes normales.
        if ($stage === WhatsappBookingState::STAGE_COMPLETED && $calendarFailure === null) {
            return $reply;
        }

        if (! $this->replyClaimsBookingSuccess($reply)) {
            return $reply;
        }

        if (is_array($calendarFailure) && empty($calendarFailure['ok'])) {
            $detail = trim((string) ($calendarFailure['message'] ?? $calendarFailure['error'] ?? ''));
            $conflicts = trim((string) ($calendarFailure['conflicts'] ?? ''));

            $parts = [
                'Ese horario no se pudo agendar'.($conflicts !== '' ? " ({$conflicts})" : '').'.',
            ];
            if ($detail !== '' && ! str_contains(mb_strtolower($detail), 'prohibido')) {
                // Prefer a short client-facing line.
            }
            $parts[] = '¿Te parece bien otro horario? Dime otra hora u otro día y lo reviso.';

            Log::info('Rewrote false booking confirmation after tool failure', [
                'error' => $calendarFailure['error'] ?? null,
            ]);

            return implode(' ', $parts);
        }

        // Claimed success without calling create_calendar_event successfully.
        Log::info('Rewrote false booking confirmation without successful create_calendar_event');

        if ($stage === WhatsappBookingState::STAGE_CONFIRMING && $booking) {
            $block = $this->formatBookingSummaryLines($booking);

            return "Aún no está registrada en la agenda. Estos son los datos pendientes de confirmar:\n{$block}\nSi estás de acuerdo, responde \"sí\" y la registro ahora.";
        }

        return 'Aún no he podido registrar la cita en la agenda. ¿Me confirmas el horario para intentarlo de nuevo, o prefieres otra hora?';
    }

    protected function replyClaimsBookingSuccess(string $reply): bool
    {
        $text = mb_strtolower($reply);

        $patterns = [
            '/queda\s+confirmad/u',
            '/qued[oó]\s+confirmad/u',
            '/cita\s+confirmad/u',
            '/sesi[oó]n\s+confirmad/u',
            '/ya\s+est[aá]\s+(confirmad|agendad|reservad)/u',
            '/queda\s+(agendad|reservad)/u',
            '/qued[oó]\s+(agendad|reservad)/u',
            '/te\s+agend[eé]/u',
            '/agend[eé]\s+tu\s+(cita|sesi)/u',
            '/reserv[eé]\s+tu\s+(cita|sesi|lugar)/u',
            '/listo[!,.]?\s+.*(confirmad|agendad|reservad)/u',
            '/tu\s+(cita|sesi[oó]n).*(confirmad|agendad|reservad)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function formatBusinessCatalog(WhatsappInstance $instance): string
    {
        $sections = [
            'Servicios / productos' => trim((string) ($instance->services ?? '')),
            'Horarios de atención' => trim((string) ($instance->business_hours ?? '')),
            'Precios' => trim((string) ($instance->prices ?? '')),
            'Promociones' => trim((string) ($instance->promotions ?? '')),
            'Ubicaciones' => trim((string) ($instance->locations ?? '')),
        ];

        $lines = [];
        foreach ($sections as $label => $value) {
            if ($value !== '') {
                $lines[] = "{$label}:\n{$value}";
            }
        }

        if ($lines === []) {
            return '';
        }

        return "Catálogo del negocio (usa solo esta información; no inventes):\n\n".implode("\n\n", $lines);
    }
}
