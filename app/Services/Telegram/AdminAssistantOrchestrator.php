<?php

namespace App\Services\Telegram;

use App\Models\WhatsappAppointment;
use App\Models\WhatsappInstance;
use App\Services\Telegram\Concerns\HasAdminAssistantTools;
use App\Services\Whatsapp\Contracts\LlmChatClient;
use App\Services\Whatsapp\DeepSeekClient;
use App\Services\Whatsapp\GoogleCalendarService;
use App\Services\Whatsapp\MimoClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AdminAssistantOrchestrator
{
    use HasAdminAssistantTools;

    public function __construct(
        protected AdminConversationRepository $conversations,
        protected DeepSeekClient $deepSeek,
        protected MimoClient $mimo,
        protected GoogleCalendarService $calendar,
        protected TelegramBotClient $telegram,
    ) {}

    public function handleIncoming(WhatsappInstance $instance, int $chatId, int $userId, string $text): ?string
    {
        $instanceName = (string) $instance->instance_name;
        $token = trim((string) ($instance->telegram_bot_token ?? ''));

        if ($token === '') {
            throw new RuntimeException("Instancia {$instanceName} sin telegram_bot_token.");
        }

        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if ($this->isStartCommand($text)) {
            $reply = $this->handleStart($instance, $userId, $text);
            $this->telegram->sendMessage($token, $chatId, $reply);

            return $reply;
        }

        if (! $this->isAllowed($instance, $userId)) {
            $reply = "No autorizado. Tu Telegram user id es {$userId}. "
                .'Pide que lo agreguen en la instancia o envía /start con el código de vinculación.';
            $this->telegram->sendMessage($token, $chatId, $reply);

            return $reply;
        }

        if (preg_match('/^\/(help|ayuda)\b/iu', $text) === 1) {
            $reply = $this->helpText($instance);
            $this->telegram->sendMessage($token, $chatId, $reply);

            return $reply;
        }

        $this->conversations->append($instanceName, $chatId, 'user', $text);

        $timezone = (string) ($instance->timezone ?: config('services.google_calendar.timezone', 'America/Merida'));
        $staticPrompt = $this->buildStaticSystemPrompt($instance, $timezone);
        $dynamicPrompt = $this->buildDynamicSystemPrompt($timezone);

        $messages = $this->conversations->toLlmMessages(
            $instanceName,
            $chatId,
            $staticPrompt,
            $dynamicPrompt,
        );
        $provider = $this->resolveProvider($instance);

        try {
            $reply = $this->runLlmLoop($messages, $instance, $instanceName, $chatId, $provider);
        } catch (Throwable $e) {
            if ($this->isLlmToolHistoryError($e)) {
                Log::warning('Telegram admin LLM history invalid; retrying fresh', [
                    'instance' => $instanceName,
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
                $messages = [
                    ['role' => 'system', 'content' => $staticPrompt],
                    ['role' => 'system', 'content' => $dynamicPrompt],
                    ['role' => 'user', 'content' => $text],
                ];
                $reply = $this->runLlmLoop($messages, $instance, $instanceName, $chatId, $provider);
            } else {
                throw $e;
            }
        }

        $this->conversations->append($instanceName, $chatId, 'assistant', $reply);
        $this->telegram->sendMessage($token, $chatId, $reply);

        return $reply;
    }

    /**
     * Avisa a los admins vinculados por Telegram (escalación WhatsApp).
     */
    public function notifyEscalation(WhatsappInstance $instance, string $message): void
    {
        $token = trim((string) ($instance->telegram_bot_token ?? ''));
        $ids = $this->normalizedAllowedIds($instance);

        if ($token === '' || $ids === []) {
            return;
        }

        $this->telegram->broadcast($token, $ids, $message);
    }

    /**
     * Registra webhook + username; genera secret/link si faltan.
     *
     * @return array{username: ?string, webhook_url: string, link_code: string}
     */
    public function provisionBot(WhatsappInstance $instance): array
    {
        $token = trim((string) ($instance->telegram_bot_token ?? ''));
        if ($token === '') {
            throw new RuntimeException('Configura el token del bot de Telegram primero.');
        }

        if (trim((string) ($instance->telegram_webhook_secret ?? '')) === '') {
            $instance->telegram_webhook_secret = Str::random(32);
        }
        if (trim((string) ($instance->telegram_link_code ?? '')) === '') {
            $instance->telegram_link_code = Str::lower(Str::random(10));
        }

        $me = $this->telegram->getMe($token);
        $username = isset($me['username']) ? (string) $me['username'] : null;
        $instance->telegram_bot_username = $username;

        $webhookUrl = $this->telegram->webhookUrlForInstance((string) $instance->instance_name);
        $this->telegram->setWebhook(
            $token,
            $webhookUrl,
            (string) $instance->telegram_webhook_secret,
        );

        $instance->save();

        return [
            'username' => $username,
            'webhook_url' => $webhookUrl,
            'link_code' => (string) $instance->telegram_link_code,
        ];
    }

    protected function isStartCommand(string $text): bool
    {
        return preg_match('/^\/start(?:@\w+)?(?:\s|$)/iu', $text) === 1;
    }

    protected function handleStart(WhatsappInstance $instance, int $userId, string $text): string
    {
        $payload = '';
        if (preg_match('/^\/start(?:@\w+)?(?:\s+(.+))?$/iu', $text, $m) === 1) {
            $payload = trim((string) ($m[1] ?? ''));
        }

        $code = trim((string) ($instance->telegram_link_code ?? ''));
        $business = trim((string) ($instance->business_name ?? ''))
            ?: (string) $instance->instance_name;

        if ($payload !== '' && $code !== '' && hash_equals($code, $payload)) {
            $this->allowUser($instance, $userId);

            return "Listo. Quedaste vinculado a «{$business}».\n"
                ."Pregúntame en lenguaje natural, por ejemplo:\n"
                ."• ¿Qué citas tengo hoy?\n"
                ."• ¿Qué tan llena está mi agenda esta semana?\n"
                ."• ¿Qué paquetes me tocan mañana?\n"
                .'• El viernes no estaré disponible';
        }

        if ($this->isAllowed($instance, $userId)) {
            return "Ya estás vinculado a «{$business}». Escribe /ayuda o pregúntame por tu agenda.";
        }

        return "Hola. Este bot es solo para administradores de «{$business}».\n"
            ."Tu user id: {$userId}\n"
            .'Envía /start CÓDIGO (el código está en la instancia en el panel) o pide que agreguen tu id.';
    }

    protected function helpText(WhatsappInstance $instance): string
    {
        $business = trim((string) ($instance->business_name ?? ''))
            ?: (string) $instance->instance_name;

        return "Asistente admin · {$business}\n\n"
            ."Puedo consultar y gestionar la agenda con IA. Ejemplos:\n"
            ."• Citas de hoy / mañana / esta semana\n"
            ."• Ocupación de la agenda\n"
            ."• Paquetes o sesiones agendadas\n"
            ."• Bloquear un día u horario (no disponible)\n"
            ."• Cancelar una cita (con el id que te liste)\n\n"
            .'Comandos: /start · /ayuda';
    }

    protected function isAllowed(WhatsappInstance $instance, int $userId): bool
    {
        return in_array($userId, $this->normalizedAllowedIds($instance), true);
    }

    /**
     * @return list<int>
     */
    protected function normalizedAllowedIds(WhatsappInstance $instance): array
    {
        $raw = $instance->telegram_allowed_user_ids ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function allowUser(WhatsappInstance $instance, int $userId): void
    {
        $ids = $this->normalizedAllowedIds($instance);
        if (! in_array($userId, $ids, true)) {
            $ids[] = $userId;
            $instance->telegram_allowed_user_ids = $ids;
            $instance->save();
        }
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
     */
    protected function runLlmLoop(
        array $messages,
        WhatsappInstance $instance,
        string $instanceName,
        int $chatId,
        string $provider,
    ): string {
        $client = $this->llmClient($provider);
        $label = $provider === 'mimo' ? 'MiMo' : 'DeepSeek';
        $tools = $this->adminTools();
        $maxRounds = 4;

        for ($round = 0; $round < $maxRounds; $round++) {
            $response = $client->chat($messages, withTools: true, tools: $tools);
            $choice = $response['choices'][0]['message'] ?? null;

            if (! is_array($choice)) {
                throw new RuntimeException("Respuesta inválida de {$label}.");
            }

            $toolCalls = $choice['tool_calls'] ?? [];

            if ($toolCalls === []) {
                $content = trim((string) ($choice['content'] ?? ''));

                return $content !== '' ? $content : '¿En qué más te ayudo con la agenda?';
            }

            $assistantContent = (string) ($choice['content'] ?? '');
            $this->conversations->append(
                $instanceName,
                $chatId,
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
                $toolResult = $this->executeToolCall($toolCall, $instance);
                $toolCallId = (string) ($toolCall['id'] ?? '');
                $toolName = (string) ($toolCall['function']['name'] ?? '');
                $encoded = json_encode($toolResult, JSON_UNESCAPED_UNICODE) ?: '{}';

                $this->conversations->append(
                    $instanceName,
                    $chatId,
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

        return $content !== '' ? $content : 'Listo. Si necesitas otra consulta de agenda, aquí estoy.';
    }

    /**
     * @param  array<string, mixed>  $toolCall
     * @return array<string, mixed>
     */
    protected function executeToolCall(array $toolCall, WhatsappInstance $instance): array
    {
        $name = (string) ($toolCall['function']['name'] ?? '');
        $rawArgs = (string) ($toolCall['function']['arguments'] ?? '{}');
        $args = json_decode($rawArgs, true);
        if (! is_array($args)) {
            $args = [];
        }

        try {
            return match ($name) {
                'list_appointments' => $this->toolListAppointments($instance, $args),
                'agenda_occupancy' => $this->toolAgendaOccupancy($instance, $args),
                'list_packages' => $this->toolListPackages($instance, $args),
                'block_availability' => $this->toolBlockAvailability($instance, $args),
                'cancel_appointment' => $this->toolCancelAppointment($instance, $args),
                'get_business_info' => $this->toolGetBusinessInfo($instance),
                default => ['ok' => false, 'error' => "Herramienta desconocida: {$name}"],
            };
        } catch (Throwable $e) {
            Log::warning('Telegram admin tool failed', [
                'tool' => $name,
                'instance' => $instance->instance_name,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolListAppointments(WhatsappInstance $instance, array $args): array
    {
        [$from, $to] = $this->resolveRange($instance, $args);
        $rows = $this->appointmentsInRange($instance, $from, $to);

        $items = $rows->map(function (WhatsappAppointment $a) use ($instance) {
            $tz = (string) ($a->timezone ?: $instance->timezone ?: 'America/Merida');

            return [
                'id' => (string) $a->getKey(),
                'summary' => (string) ($a->summary ?? ''),
                'user_phone' => (string) ($a->user_phone ?? ''),
                'starts_at' => $a->starts_at?->copy()->timezone($tz)->format('Y-m-d H:i'),
                'ends_at' => $a->ends_at?->copy()->timezone($tz)->format('Y-m-d H:i'),
                'source' => (string) ($a->source ?? ''),
                'is_block' => ($a->source ?? '') === 'admin_block'
                    || str_contains(mb_strtolower((string) ($a->summary ?? '')), 'no disponible'),
            ];
        })->values()->all();

        return [
            'ok' => true,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'count' => count($items),
            'appointments' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolAgendaOccupancy(WhatsappInstance $instance, array $args): array
    {
        [$from, $to] = $this->resolveRange($instance, $args);
        $rows = $this->appointmentsInRange($instance, $from, $to);

        $bookedMinutes = 0;
        foreach ($rows as $a) {
            if (! $a->starts_at || ! $a->ends_at) {
                continue;
            }
            $bookedMinutes += max(0, $a->starts_at->diffInMinutes($a->ends_at));
        }

        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $hoursPerDay = 8;
        $capacityMinutes = $days * $hoursPerDay * 60;
        $pct = $capacityMinutes > 0
            ? round(($bookedMinutes / $capacityMinutes) * 100, 1)
            : 0.0;

        return [
            'ok' => true,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'appointment_count' => $rows->count(),
            'booked_minutes' => $bookedMinutes,
            'booked_hours' => round($bookedMinutes / 60, 2),
            'assumed_capacity_hours' => $days * $hoursPerDay,
            'occupancy_percent_approx' => $pct,
            'note' => 'Capacidad estimada a 8h/día. Usa business_hours del catálogo si el admin pregunta detalle.',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolListPackages(WhatsappInstance $instance, array $args): array
    {
        [$from, $to] = $this->resolveRange($instance, $args);
        $rows = $this->appointmentsInRange($instance, $from, $to);
        $tz = (string) ($instance->timezone ?: 'America/Merida');

        $byDay = [];
        foreach ($rows as $a) {
            if (($a->source ?? '') === 'admin_block') {
                continue;
            }
            $day = $a->starts_at?->copy()->timezone($tz)->toDateString() ?? 'sin-fecha';
            $label = trim((string) ($a->summary ?? '(Sin título)'));
            $byDay[$day][] = [
                'id' => (string) $a->getKey(),
                'package_or_session' => $label,
                'time' => $a->starts_at?->copy()->timezone($tz)->format('H:i'),
                'phone' => (string) ($a->user_phone ?? ''),
            ];
        }

        ksort($byDay);

        return [
            'ok' => true,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $byDay,
            'total' => $rows->filter(fn ($a) => ($a->source ?? '') !== 'admin_block')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolBlockAvailability(WhatsappInstance $instance, array $args): array
    {
        $tz = (string) ($instance->timezone ?: config('services.google_calendar.timezone', 'America/Merida'));
        $start = $this->parseLocalDateTime((string) ($args['starts_at'] ?? ''), $tz);
        $end = $this->parseLocalDateTime((string) ($args['ends_at'] ?? ''), $tz);
        $reason = trim((string) ($args['reason'] ?? 'No disponible'));

        if (! $start || ! $end || $end->lte($start)) {
            return ['ok' => false, 'error' => 'Rango inválido: revisa starts_at y ends_at.'];
        }

        $summary = str_starts_with(mb_strtolower($reason), 'no disponible')
            ? $reason
            : 'No disponible: '.$reason;

        $calendarId = (string) ($instance->google_calendar_id ?: config('services.google_calendar.calendar_id'));
        if ($calendarId === '') {
            return ['ok' => false, 'error' => 'La instancia no tiene Google Calendar ID.'];
        }

        $created = $this->calendar->createEvent(
            $calendarId,
            $summary,
            $start->toIso8601String(),
            $end->toIso8601String(),
            $tz,
            'Bloqueo creado desde bot admin Telegram.',
            $instance->google_credentials_path ?: null,
        );

        $apt = WhatsappAppointment::query()->create([
            'instance_name' => $instance->evolutionName(),
            'user_phone' => 'admin',
            'summary' => $summary,
            'description' => 'Bloqueo admin Telegram',
            'starts_at' => $start,
            'ends_at' => $end,
            'timezone' => $tz,
            'google_calendar_id' => $calendarId,
            'google_event_id' => $created['id'] ?? null,
            'google_html_link' => $created['html_link'] ?? null,
            'sync_status' => 'synced',
            'source' => 'admin_block',
        ]);

        return [
            'ok' => true,
            'appointment_id' => (string) $apt->getKey(),
            'summary' => $summary,
            'starts_at' => $start->format('Y-m-d H:i'),
            'ends_at' => $end->format('Y-m-d H:i'),
            'google_event_id' => $created['id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function toolCancelAppointment(WhatsappInstance $instance, array $args): array
    {
        $id = trim((string) ($args['appointment_id'] ?? ''));
        if ($id === '') {
            return ['ok' => false, 'error' => 'Falta appointment_id.'];
        }

        $apt = WhatsappAppointment::query()
            ->where('instance_name', $instance->evolutionName())
            ->find($id);

        if (! $apt) {
            return ['ok' => false, 'error' => 'Cita no encontrada.'];
        }

        $deleteGoogle = array_key_exists('delete_google', $args)
            ? (bool) $args['delete_google']
            : true;

        $googleNote = null;
        $eventId = (string) ($apt->google_event_id ?? '');
        $calendarId = (string) ($instance->google_calendar_id ?? '');

        if ($deleteGoogle && $eventId !== '' && $calendarId !== '') {
            try {
                $this->calendar->deleteEvent(
                    $calendarId,
                    $eventId,
                    $instance->google_credentials_path ?: null,
                );
            } catch (Throwable $e) {
                $googleNote = $e->getMessage();
            }
        }

        $summary = (string) ($apt->summary ?? '');
        $apt->delete();

        return [
            'ok' => true,
            'deleted_summary' => $summary,
            'google_error' => $googleNote,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function toolGetBusinessInfo(WhatsappInstance $instance): array
    {
        return [
            'ok' => true,
            'business_name' => (string) ($instance->business_name ?? ''),
            'services' => (string) ($instance->services ?? ''),
            'business_hours' => (string) ($instance->business_hours ?? ''),
            'prices' => (string) ($instance->prices ?? ''),
            'promotions' => (string) ($instance->promotions ?? ''),
            'locations' => (string) ($instance->locations ?? ''),
            'timezone' => (string) ($instance->timezone ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveRange(WhatsappInstance $instance, array $args): array
    {
        $tz = (string) ($instance->timezone ?: config('services.google_calendar.timezone', 'America/Merida'));
        $fromRaw = trim((string) ($args['date_from'] ?? ''));
        $toRaw = trim((string) ($args['date_to'] ?? ''));

        if ($fromRaw === '') {
            throw new RuntimeException('Falta date_from.');
        }

        $from = Carbon::parse($fromRaw, $tz)->startOfDay();
        $to = $toRaw !== ''
            ? Carbon::parse($toRaw, $tz)->endOfDay()
            : $from->copy()->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    protected function parseLocalDateTime(string $raw, string $tz): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw, $tz);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, WhatsappAppointment>
     */
    protected function appointmentsInRange(WhatsappInstance $instance, Carbon $from, Carbon $to)
    {
        return WhatsappAppointment::query()
            ->where('instance_name', $instance->evolutionName())
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->orderBy('starts_at')
            ->limit(200)
            ->get();
    }

    protected function buildStaticSystemPrompt(WhatsappInstance $instance, string $timezone): string
    {
        $business = trim((string) ($instance->business_name ?? ''))
            ?: (string) $instance->instance_name;

        return <<<PROMPT
Eres el asistente de administración de «{$business}» por Telegram.
Hablas con el dueño/staff autorizado. Responde en español, claro y conciso (mensajes cortos de chat).

Zona horaria de la instancia: {$timezone}.

Usa SIEMPRE las tools para datos reales de agenda. No inventes citas ni ocupación.
- list_appointments: listar citas
- agenda_occupancy: qué tan llena está la agenda
- list_packages: paquetes/sesiones por día
- block_availability: cuando diga que no estará disponible (día u horario)
- cancel_appointment: cancelar con el id que devolvió list_appointments
- get_business_info: catálogo/horarios configurados

Para "hoy", "mañana", "esta semana", "el viernes", convierte a YYYY-MM-DD usando la fecha actual del otro system prompt.
Si vas a cancelar o bloquear, confirma el rango/id en tu respuesta humana de forma natural.
No menciones que eres un modelo ni que usas tools.
PROMPT;
    }

    protected function buildDynamicSystemPrompt(string $timezone): string
    {
        $now = Carbon::now($timezone);

        return 'Ahora: '.$now->format('Y-m-d H:i')." ({$timezone}), "
            .$now->locale('es')->isoFormat('dddd D [de] MMMM YYYY').'.';
    }
}
