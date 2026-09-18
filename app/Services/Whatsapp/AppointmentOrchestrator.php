<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AppointmentOrchestrator
{
    public function __construct(
        protected ConversationRepository $conversations,
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
        $this->conversations->append($instanceName, $phone, 'user', $text);

        $systemPrompt = $instance->system_prompt
            ?: $this->defaultSystemPrompt($instance->timezone ?? config('services.google_calendar.timezone'));

        $messages = $this->conversations->toDeepSeekMessages($instanceName, $phone, $systemPrompt);
        $reply = $this->runDeepSeekLoop($messages, $instance, $instanceName, $phone);

        $this->conversations->append($instanceName, $phone, 'assistant', $reply);
        $this->evolution->sendText($instanceName, $phone, $reply);

        return $reply;
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
                $toolResult = $this->executeToolCall($toolCall, $instance);
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
    protected function executeToolCall(array $toolCall, WhatsappInstance $instance): array
    {
        $name = (string) ($toolCall['function']['name'] ?? '');
        $rawArgs = (string) ($toolCall['function']['arguments'] ?? '{}');
        $args = json_decode($rawArgs, true);

        if (! is_array($args)) {
            return ['ok' => false, 'error' => 'Argumentos de herramienta inválidos.'];
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

        if ($calendarId === '' || $startIso === '' || $endIso === '') {
            return [
                'ok' => false,
                'error' => 'Faltan calendar_id, start_iso o end_iso para agendar.',
            ];
        }

        try {
            $start = Carbon::parse($startIso, $timezone);
            $end = Carbon::parse($endIso, $timezone);

            if ($end->lessThanOrEqualTo($start)) {
                $end = $start->copy()->addMinutes(30);
            }

            $created = $this->calendar->createEvent(
                $calendarId,
                $summary,
                $start->toIso8601String(),
                $end->toIso8601String(),
                $timezone,
                $description,
            );

            return [
                'ok' => true,
                'event' => $created,
                'message' => 'Evento creado correctamente en Google Calendar.',
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

    protected function defaultSystemPrompt(string $timezone): string
    {
        $today = Carbon::now($timezone)->toDayDateTimeString();

        return <<<PROMPT
Eres un asistente de agendamiento por WhatsApp. Habla en español, de forma breve y amable.
Zona horaria: {$timezone}. Fecha/hora actual de referencia: {$today}.
Cuando el usuario pida una cita y tengas asunto + fecha/hora claros, llama a la herramienta create_calendar_event.
Si falta información (día, hora o motivo), pregunta antes de agendar.
Si no indiquen duración, usa 30 minutos (end_iso = start + 30 min).
Tras crear el evento, confirma al usuario con día, hora y asunto.
PROMPT;
    }
}
