<?php

namespace App\Services\Assistant;

use App\Models\CalendarEvent;
use App\Models\Reminder;
use App\Models\WorkspaceNote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssistantOrchestratorService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $model;
    protected string $timezone = 'America/Cancun';
    protected IntentRouterService $intentRouter;
    protected ModelRouterService $modelRouter;

    public function __construct(?IntentRouterService $intentRouter = null, ?ModelRouterService $modelRouter = null)
    {
        $this->intentRouter = $intentRouter ?? app(IntentRouterService::class);
        $this->modelRouter  = $modelRouter ?? app(ModelRouterService::class);

        $defaultConfig = $this->modelRouter->getActiveModelConfig('write_intent');
        $this->apiUrl = $defaultConfig['base_url'];
        $this->apiKey = $defaultConfig['api_key'];
        $this->model  = $defaultConfig['model'];
    }

    /**
     * Sanitiza el texto para síntesis de voz (TTS) y mensajería (WhatsApp),
     * eliminando asteriscos de markdown, almohadillas, viñetas y caracteres especiales
     * que arruinan la lectura por voz o generan lectura fonética indeseada.
     */
    public function cleanVoiceText(string $text): string
    {
        // 1. Eliminar encabezados markdown (# Encabezado -> Encabezado)
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);

        // 2. Eliminar negritas y cursivas (**texto**, *texto*, __texto__, _texto_)
        $text = preg_replace('/(\*\*|__)(.*?)\1/s', '$2', $text);
        $text = preg_replace('/(\*|_)(.*?)\1/s', '$2', $text);

        // 3. Eliminar viñetas de guion, asterisco o puntos de lista
        $text = preg_replace('/^\s*[-*•]\s+/m', '', $text);

        // 4. Eliminar enlaces markdown [texto](url) -> texto
        $text = preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $text);
        $text = str_replace(['[', ']'], '', $text);

        // 5. Reemplazar pipes o barras separadoras por puntos o espacios
        $text = str_replace(' | ', '. ', $text);
        $text = str_replace('|', ' ', $text);

        // 6. Limpiar espacios horizontales redundantes y saltos excesivos
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Parses the LLM content for dual output format (display_text vs speech_text)
     * Supports JSON responses, markdown blocks with JSON, or conversational fallback.
     */
    public function parseDualOutput(string $content, string $fallbackAction = 'conversation'): array
    {
        $trimmed = trim($content);

        // Check for markdown code blocks containing json
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $trimmed, $matches)) {
            $jsonStr = $matches[1];
        } elseif (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            $jsonStr = $trimmed;
        } else {
            $jsonStr = null;
        }

        if ($jsonStr) {
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded) && (isset($decoded['display_text']) || isset($decoded['speech_text']))) {
                $displayText = (string) ($decoded['display_text'] ?? $decoded['reply'] ?? $trimmed);
                $speechText = (string) ($decoded['speech_text'] ?? $this->cleanVoiceText($displayText));

                return [
                    'display_text'        => $displayText,
                    'speech_text'         => $this->cleanVoiceText($speechText),
                    'action'              => (string) ($decoded['action'] ?? $fallbackAction),
                    'categories_detected' => (array) ($decoded['categories_detected'] ?? []),
                    'atomic_tasks_count'  => (int) ($decoded['atomic_tasks_count'] ?? 0),
                    'prioritized_summary' => $decoded['prioritized_summary'] ?? null,
                ];
            }
        }

        return [
            'display_text'        => $trimmed,
            'speech_text'         => $this->cleanVoiceText($trimmed),
            'action'              => $fallbackAction,
            'categories_detected' => [],
            'atomic_tasks_count'  => 0,
            'prioritized_summary' => null,
        ];
    }

    /**
     * Retrieves recent conversation history turns from the database for the active user/session.
     * Returns an array of chronologically ordered messages:
     * [['role' => 'user', 'content' => ...], ['role' => 'assistant', 'content' => ...], ...]
     *
     * @param User $user
     * @param string|null $conversationId
     * @param string|null $channel
     * @param int $maxMessages Maximum individual messages to include (default 20: 10 user + 10 assistant)
     * @return array
     */
    public function loadConversationHistory(User $user, ?string $conversationId = null, ?string $channel = null, int $maxMessages = 20): array
    {
        $userId = (string) ($user->_id ?? $user->id);
        if (empty($userId)) {
            return [];
        }

        $query = \App\Models\AssistantMessage::where(function ($q) use ($userId, $user) {
            $q->where('user_id', $userId)
              ->orWhere('user_id', (string) $user->id);
        })
        ->where('status', 'completed')
        ->whereNotNull('prompt')
        ->where('prompt', '!=', '')
        ->where(function ($q) {
            $q->whereNotNull('reply')->where('reply', '!=', '')
              ->orWhereNotNull('display_text')->where('display_text', '!=', '');
        });

        if (!empty($conversationId)) {
            $query->where(function ($q) use ($conversationId) {
                $q->where('conversation_id', $conversationId)
                  ->orWhere('metadata.conversation_id', $conversationId);
            });
        } elseif (!empty($channel)) {
            if ($channel === 'mobile' || $channel === 'app') {
                $query->whereIn('channel', ['app', 'mobile']);
            } else {
                $query->where('channel', $channel);
            }
        }

        // Each AssistantMessage contains a prompt (user) and reply (assistant) = 2 messages.
        // Fetch up to ceil($maxMessages / 2) turns, ordered by most recent first.
        $turnLimit = max(1, (int) ceil($maxMessages / 2));
        $messages = $query->orderBy('created_at', 'desc')
            ->orderBy('_id', 'desc')
            ->limit($turnLimit)
            ->get();

        // Reverse to chronological order (oldest first)
        $chronological = $messages->reverse()->values();

        $history = [];
        foreach ($chronological as $msg) {
            $userText = trim((string) $msg->prompt);
            $assistantText = trim((string) ($msg->display_text ?? $msg->reply ?? ''));

            if ($userText !== '') {
                $history[] = [
                    'role'    => 'user',
                    'content' => $userText,
                ];
            }

            if ($assistantText !== '') {
                $history[] = [
                    'role'    => 'assistant',
                    'content' => $assistantText,
                ];
            }
        }

        // Keep strictly within window
        if (count($history) > $maxMessages) {
            $history = array_slice($history, -$maxMessages);
        }

        return $history;
    }

    /**
     * Builds standard multi-turn LLM messages payload:
     * [{ role: 'system', ... }, { role: 'user', ... }, { role: 'assistant', ... }, ..., { role: 'user', content: $prompt }]
     *
     * @param string $systemPrompt
     * @param string $prompt
     * @param array $conversationHistory
     * @param int $maxHistoryMessages
     * @return array
     */
    public function buildChatPayload(string $systemPrompt, string $prompt, array $conversationHistory = [], int $maxHistoryMessages = 20): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Sanitize and limit conversation history window
        $trimmedHistory = array_slice($conversationHistory, -$maxHistoryMessages);

        foreach ($trimmedHistory as $msg) {
            if (!isset($msg['role'], $msg['content'])) {
                continue;
            }

            $role = (string) $msg['role'];
            $content = (string) $msg['content'];

            if ($role === 'system') {
                // If there's an additional system instruction in history (e.g. channel prompt), append to primary system prompt
                $messages[0]['content'] .= "\n\n" . $content;
            } elseif (in_array($role, ['user', 'assistant'], true)) {
                $messages[] = [
                    'role'    => $role,
                    'content' => $content,
                ];
            }
        }

        // Append current user prompt as the final turn
        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $messages;
    }

    /**
     * Interacts with the appropriate pipeline using zero-latency Intent Routing and multi-turn context.
     *
     * @param User $user
     * @param string $prompt
     * @param array $conversationHistory
     * @param string|null $conversationId
     * @param string|null $channel
     * @return array Standardized response
     */
    public function interact(
        User $user,
        string $prompt,
        array $conversationHistory = [],
        ?string $conversationId = null,
        ?string $channel = null
    ): array {
        try {
            // Auto-load conversation history from database if not explicitly provided
            if (empty($conversationHistory)) {
                $conversationHistory = $this->loadConversationHistory($user, $conversationId, $channel, 20);
            }

            $intent = $this->intentRouter->classify($prompt);
            Log::info("Assistant routing decision", [
                'user_id' => (string) ($user->_id ?? $user->id),
                'route'   => $intent['route'],
                'intent'  => $intent['inferred_intent'] ?? null,
            ]);

            if ($intent['route'] === 'read_intent') {
                return $this->executeReadPipeline($user, $prompt, $intent, $conversationHistory);
            }

            if ($intent['route'] === 'write_intent') {
                return $this->executeWritePipeline($user, $prompt, $intent, $conversationHistory);
            }

            return $this->executeReasoningFallback($user, $prompt, $conversationHistory);
        } catch (\Throwable $e) {
            Log::error('Assistant interact error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success'      => false,
                'data'         => null,
                'message'      => 'Lo siento, ocurrió un error al procesar tu solicitud: ' . $e->getMessage(),
                'display_text' => 'Lo siento, ocurrió un error al procesar tu solicitud: ' . $e->getMessage(),
                'speech_text'  => 'Lo siento, ocurrió un error al procesar tu solicitud.',
                'action'       => 'error',
            ];
        }
    }

    /**
     * READ PIPELINE (Ultra-low latency, pre-indexed DB context, dual visual/TTS output).
     * Prohibido pasar datos crudos no indexados al modelo rápido.
     */
    public function executeReadPipeline(User $user, string $prompt, array $intent, array $conversationHistory = []): array
    {
        $userId = (string) ($user->_id ?? $user->id);
        $nowInTz = Carbon::now($this->timezone);

        // 1. Pre-query Database: Pending Reminders
        $reminders = Reminder::where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', '!=', 'completed');
            })
            ->orderBy('remind_at', 'asc')
            ->limit(20)
            ->get();

        // 2. Pre-query Database: Atemporal Notes & Tasks
        $notes = WorkspaceNote::where('user_id', $userId)
            ->where(function ($q) {
                $q->whereIn('tags', ['todo', 'pendiente'])
                  ->orWhere('is_pinned', true);
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // 3. Pre-query Database: Today Calendar Events
        $startOfDay = Carbon::now($this->timezone)->startOfDay()->toIso8601String();
        $endOfDay   = Carbon::now($this->timezone)->endOfDay()->toIso8601String();
        $events = CalendarEvent::where('user_id', $userId)
            ->where('start_time', '>=', $startOfDay)
            ->where('start_time', '<=', $endOfDay)
            ->orderBy('start_time', 'asc')
            ->get();

        // Construct clean structured context
        $remindersList = [];
        foreach ($reminders as $r) {
            $remindersList[] = [
                'id'        => (string) $r->_id,
                'title'     => $r->title,
                'remind_at' => $r->remind_at ? Carbon::parse($r->remind_at)->setTimezone($this->timezone)->format('d/m/Y H:i') : null,
            ];
        }

        $notesList = [];
        foreach ($notes as $n) {
            $notesList[] = [
                'id'       => (string) $n->_id,
                'title'    => $n->title,
                'content'  => $n->content,
                'tags'     => $n->tags ?? [],
                'category' => $this->inferCategoryFromTags((array) ($n->tags ?? [])),
            ];
        }

        $eventsList = [];
        foreach ($events as $ev) {
            $eventsList[] = [
                'id'         => (string) $ev->_id,
                'title'      => $ev->title,
                'start_time' => Carbon::parse($ev->start_time)->setTimezone($this->timezone)->format('H:i'),
            ];
        }

        $contextJson = json_encode([
            'usuario'               => $user->name,
            'hora_actual'           => $nowInTz->format('d/m/Y H:i:s'),
            'recordatorios_activos' => $remindersList,
            'pendientes_y_tareas'   => $notesList,
            'eventos_agenda_hoy'    => $eventsList,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // 4. Call Fast Model (read_intent)
        $modelConfig = $this->modelRouter->getActiveModelConfig('read_intent');
        $endpoint = rtrim($modelConfig['base_url'], '/') . '/chat/completions';

        $expectedAction = preg_match('/(pendientes|recordatorios|agenda|eventos|tareas|notas|hoy)/iu', $prompt) ? 'get_pending_reminders' : 'conversation';

        $systemPrompt = "Eres Cami, la asistente ejecutiva personal de {$user->name}. " .
            "Se te proporciona la información estructurada que YA FUE CONSULTADA de la base de datos del usuario. " .
            "Tu función es responder con ultra-baja latencia y máxima claridad para síntesis de voz (TTS) y pantalla móvil.\n\n" .
            "DIRECTIVAS Y REGLAS:\n" .
            "1. NO inventes datos. Usa solo los datos provistos en la consulta estructurada.\n" .
            "2. LISTADOS BAJO DEMANDA: Solo desglosa o resume pendientes cuando el usuario lo solicite EXPLÍCITAMENTE (ej. '¿qué tengo pendiente?', 'revisa mis tareas de hoy'). Si el usuario solo saluda (ej. 'hola', 'buenos días'), responde con un saludo breve y natural sin volcar pendientes.\n" .
            "3. ELIMINACIÓN DE DUPLICADOS: Cada elemento debe listarse exactamente UNA SOLA VEZ. NO dupliques ítems entre 'Prioridades' y sus categorías temáticas.\n" .
            "4. TONO NATURAL: Sé directa, natural y conversacional. Elimina saludos robóticos o introducciones innecesarias.\n" .
            "5. 'display_text': Markdown estructurado, limpio y elegante con viñetas y títulos en negrita.\n" .
            "6. 'speech_text': Prosa natural, fluida, conversacional y cálida para voz TTS, SIN asteriscos, SIN viñetas, SIN caracteres especiales ni enlaces.\n" .
            "7. Devuelve un JSON estricto con las claves: display_text, speech_text, action ('{$expectedAction}').\n\n" .
            "DATOS CONSULTADOS:\n{$contextJson}";

        $messages = $this->buildChatPayload($systemPrompt, $prompt, $conversationHistory, 20);

        try {
            $response = Http::withToken($modelConfig['api_key'])
                ->withHeaders((array) ($modelConfig['headers'] ?? []))
                ->timeout(max(5, intval($modelConfig['timeout_ms'] / 1000)))
                ->post($endpoint, [
                    'model'           => $modelConfig['model'],
                    'messages'        => $messages,
                    'temperature'     => $modelConfig['temperature'] ?? 0.5,
                    'max_tokens'      => $modelConfig['max_tokens'] ?? 1000,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content', '');
                $defaultAction = preg_match('/(pendientes|recordatorios|agenda|eventos|tareas|notas|hoy)/iu', $prompt) ? 'get_pending_reminders' : 'conversation';
                $parsed = $this->parseDualOutput($content, $defaultAction);

                return [
                    'success'             => true,
                    'data'                => [
                        'reminders' => $remindersList,
                        'notes'     => $notesList,
                        'events'    => $eventsList,
                    ],
                    'message'             => $parsed['display_text'],
                    'display_text'        => $parsed['display_text'],
                    'speech_text'         => $parsed['speech_text'],
                    'action'              => ($expectedAction === 'conversation' ? 'conversation' : ($parsed['action'] ?? $expectedAction)),
                    'categories_detected' => $parsed['categories_detected'],
                    'atomic_tasks_count'  => count($remindersList) + count($notesList),
                    'prioritized_summary' => $parsed['prioritized_summary'],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Read pipeline fast model failed, using deterministic local formatting', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->formatLocalReadFallback($user, $remindersList, $notesList, $eventsList, $prompt);
    }

    /**
     * WRITE PIPELINE (Reasoning Model, forced strict JSON object schema, direct deterministic DB persistence).
     */
    public function executeWritePipeline(User $user, string $prompt, array $intent, array $conversationHistory = []): array
    {
        $userId = (string) ($user->_id ?? $user->id);
        $nowInTz = Carbon::now($this->timezone);
        $modelConfig = $this->modelRouter->getActiveModelConfig('write_intent');
        $endpoint = rtrim($modelConfig['base_url'], '/') . '/chat/completions';

        $systemPrompt = "Eres el motor de razonamiento e ingesta estructurada de Cami, asistente ejecutiva de {$user->name}. " .
            "Tu tarea es descomponer atómicamente la solicitud del usuario en entidades independientes, deduplicar elementos repetidos y categorizarlos.\n" .
            "Fecha actual de referencia: {$nowInTz->format('Y-m-d H:i:s')} ({$this->timezone}).\n\n" .
            "DIRECTIVAS CRÍTICAS DE RESPUESTA:\n" .
            "1. RESPUESTAS CONVERSACIONALES Y CONCISAS: NO imprimas un reporte completo, inventario o volcado de base de datos cada vez que el usuario registra o modifica una tarea.\n" .
            "2. Limítate a confirmar la acción de forma breve, natural y directa (ejemplo: 'Anotado: comprar tomates' o 'Recordatorio programado: comprar pan mañana a las 9:00 am').\n" .
            "3. LISTADOS BAJO DEMANDA: Solo desglosa o resume pendientes cuando el usuario lo solicite explícitamente (ej. '¿qué tengo pendiente?'). Al registrar tareas, SOLO confirma lo recién registrado.\n" .
            "4. ELIMINACIÓN DE DUPLICADOS: Si un elemento viene repetido, regístralo solo una vez.\n" .
            "5. TONO NATURAL: Elimina saludos robóticos o introducciones innecesarias.\n\n" .
            "Debes responder ÚNICAMENTE un JSON con esta estructura exacta:\n" .
            "{\n" .
            '  "entities": [\n' .
            '    {\n' .
            '      "type": "reminder" | "task" | "note" | "calendar_event",\n' .
            '      "title": "string",\n' .
            '      "content": "string",\n' .
            '      "due_date": "YYYY-MM-DD" | null,\n' .
            '      "time": "HH:MM" | null,\n' .
            '      "category": "Desarrollo/Tech" | "Personal/Compras" | "Casa/Admin" | "General",\n' .
            '      "tags": ["string"]\n' .
            '    }\n' .
            '  ],\n' .
            '  "display_text": "Confirmación breve, natural y directa (ej. Anotado: comprar tomates)",\n' .
            '  "speech_text": "Confirmación breve para TTS sin markdown (ej. Anotado: comprar tomates)"\n' .
            "}";

        $messages = $this->buildChatPayload($systemPrompt, $prompt, $conversationHistory, 20);

        try {
            $response = Http::withToken($modelConfig['api_key'])
                ->withHeaders((array) ($modelConfig['headers'] ?? []))
                ->timeout(max(10, intval($modelConfig['timeout_ms'] / 1000)))
                ->post($endpoint, [
                    'model'           => $modelConfig['model'],
                    'messages'        => $messages,
                    'temperature'     => $modelConfig['temperature'] ?? 0.2,
                    'max_tokens'      => $modelConfig['max_tokens'] ?? 2000,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content', '{}');
                $decoded = json_decode($content, true);

                if (is_array($decoded) && !empty($decoded['entities'])) {
                    // Direct Backend Persistence
                    $persistedEntities = [];
                    foreach ($decoded['entities'] as $entity) {
                        $type = strtolower($entity['type'] ?? 'task');
                        $title = trim($entity['title'] ?? 'Pendiente');
                        $body = trim($entity['content'] ?? $title);
                        $category = $entity['category'] ?? 'General';
                        $tags = (array) ($entity['tags'] ?? []);

                        if ($type === 'reminder') {
                            $remindAt = null;
                            if (!empty($entity['due_date'])) {
                                $time = $entity['time'] ?? '09:00';
                                $remindAt = Carbon::parse("{$entity['due_date']} {$time}", $this->timezone);
                            } else {
                                $remindAt = Carbon::now($this->timezone)->addHour();
                            }

                            $created = Reminder::create([
                                'user_id'   => $userId,
                                'title'     => $title,
                                'remind_at' => $remindAt,
                                'status'    => 'pending',
                            ]);
                            $persistedEntities[] = ['type' => 'reminder', 'id' => (string) $created->_id, 'title' => $title];
                        } elseif ($type === 'calendar_event') {
                            $startTime = null;
                            if (!empty($entity['due_date'])) {
                                $time = $entity['time'] ?? '09:00';
                                $startTime = Carbon::parse("{$entity['due_date']} {$time}", $this->timezone)->toIso8601String();
                            } else {
                                $startTime = Carbon::now($this->timezone)->addHours(2)->toIso8601String();
                            }

                            $created = CalendarEvent::create([
                                'user_id'    => $userId,
                                'title'      => $title,
                                'start_time' => $startTime,
                            ]);
                            $persistedEntities[] = ['type' => 'calendar_event', 'id' => (string) $created->_id, 'title' => $title];
                        } else {
                            if (!in_array('todo', $tags) && $type === 'task') {
                                $tags[] = 'todo';
                                $tags[] = 'pendiente';
                            }
                            if (!empty($category)) {
                                $catTag = strtolower(explode('/', $category)[0] ?? $category);
                                $tags[] = $catTag;
                            }
                            $tags = array_values(array_unique($tags));

                            $created = WorkspaceNote::create([
                                'user_id'   => $userId,
                                'title'     => $title,
                                'content'   => $body,
                                'tags'      => $tags,
                                'is_pinned' => false,
                            ]);
                            $persistedEntities[] = ['type' => 'note', 'id' => (string) $created->_id, 'title' => $title];
                        }
                    }

                    $defaultConfirm = count($persistedEntities) === 1
                        ? "Anotado: {$persistedEntities[0]['title']}."
                        : ("Anotados en pendientes: " . implode(' ', array_map(fn($idx, $e) => ($idx + 1) . ". {$e['title']}.", array_keys($persistedEntities), $persistedEntities)));

                    $displayText = $decoded['display_text'] ?? $defaultConfirm;
                    $speechText  = $decoded['speech_text'] ?? $this->cleanVoiceText($displayText);

                    return [
                        'success'             => true,
                        'data'                => $persistedEntities,
                        'message'             => $displayText,
                        'display_text'        => $displayText,
                        'speech_text'         => $this->cleanVoiceText($speechText),
                        'action'              => 'create_multiple',
                        'categories_detected' => array_values(array_unique(array_filter(array_column($decoded['entities'], 'category')))),
                        'atomic_tasks_count'  => count($persistedEntities),
                        'prioritized_summary' => null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::error('Write pipeline failed, falling back to reasoning loop: ' . $e->getMessage());
        }

        return $this->executeReasoningFallback($user, $prompt, $conversationHistory);
    }

    /**
     * Deterministic Local Fallback for read requests if external LLM has issues.
     */
    protected function formatLocalReadFallback(User $user, array $reminders, array $notes, array $events, string $prompt = ''): array
    {
        $isExplicitRequest = (bool) preg_match('/(pendientes|recordatorios|agenda|eventos|tareas|notas|hoy|mañana|semana|resumen|status|estado)/iu', $prompt);

        if (!$isExplicitRequest) {
            $displayText = "¡Hola! ¿En qué puedo ayudarte hoy?";
            return [
                'success'             => true,
                'data'                => [
                    'reminders' => $reminders,
                    'notes'     => $notes,
                    'events'    => $events,
                ],
                'message'             => $displayText,
                'display_text'        => $displayText,
                'speech_text'         => $displayText,
                'action'              => 'conversation',
                'categories_detected' => [],
                'atomic_tasks_count'  => count($reminders) + count($notes),
                'prioritized_summary' => null,
            ];
        }

        $lines = ["Tus pendientes:"];
        $speechLines = ["Tus pendientes:"];
        $seenTitles = [];

        if (!empty($notes)) {
            $lines[] = "\n**Tareas y Pendientes**";
            foreach ($notes as $n) {
                $norm = mb_strtolower(trim($n['title'] ?? ''));
                if (!isset($seenTitles[$norm])) {
                    $seenTitles[$norm] = true;
                    $lines[] = "- {$n['title']}";
                    $speechLines[] = $n['title'];
                }
            }
        }

        if (!empty($reminders)) {
            $lines[] = "\n**Recordatorios Programados**";
            foreach ($reminders as $r) {
                $norm = mb_strtolower(trim($r['title'] ?? ''));
                if (!isset($seenTitles[$norm])) {
                    $seenTitles[$norm] = true;
                    $timeStr = !empty($r['remind_at']) ? " ({$r['remind_at']})" : "";
                    $lines[] = "- {$r['title']}{$timeStr}";
                    $speechLines[] = $r['title'];
                }
            }
        }

        if (!empty($events)) {
            $lines[] = "\n**Agenda de Hoy**";
            foreach ($events as $ev) {
                $norm = mb_strtolower(trim($ev['title'] ?? ''));
                if (!isset($seenTitles[$norm])) {
                    $seenTitles[$norm] = true;
                    $lines[] = "- {$ev['title']} a las {$ev['start_time']}";
                    $speechLines[] = "{$ev['title']} a las {$ev['start_time']}";
                }
            }
        }

        if (empty($seenTitles)) {
            $displayText = "No tienes pendientes ni recordatorios agendados por el momento.";
            $speechText  = $displayText;
        } else {
            $displayText = trim(implode("\n", $lines));
            $speechText  = implode(". ", $speechLines) . ".";
        }

        return [
            'success'             => true,
            'data'                => [
                'reminders' => $reminders,
                'notes'     => $notes,
                'events'    => $events,
            ],
            'message'             => $displayText,
            'display_text'        => $displayText,
            'speech_text'         => $this->cleanVoiceText($speechText),
            'action'              => 'get_pending_reminders',
            'categories_detected' => [],
            'atomic_tasks_count'  => count($reminders) + count($notes),
            'prioritized_summary' => null,
        ];
    }

    protected function inferCategoryFromTags(array $tags): string
    {
        $tagsLower = array_map('strtolower', $tags);
        if (array_intersect($tagsLower, ['tech', 'desarrollo', 'programacion', 'dev', 'codigo', 'shaders', 'webgpu'])) {
            return 'Desarrollo/Tech';
        }
        if (array_intersect($tagsLower, ['compras', 'personal', 'salud', 'comida', 'casa', 'hogar'])) {
            return 'Personal/Compras';
        }
        if (array_intersect($tagsLower, ['admin', 'pago', 'finanzas', 'factura', 'banco'])) {
            return 'Casa/Admin';
        }
        return 'General';
    }

    /**
     * REASONING FALLBACK (Full multi-turn tool calling loop with model router resolution).
     */
    public function executeReasoningFallback(User $user, string $prompt, array $conversationHistory = []): array
    {
        try {
            $nowInTz = Carbon::now($this->timezone);
            $nowIso  = $nowInTz->toIso8601String();

            $systemPrompt = $this->buildSystemPrompt($nowIso, $user);

            $messages = $this->buildChatPayload($systemPrompt, $prompt, $conversationHistory, 20);

            $modelConfig = $this->modelRouter->getActiveModelConfig('reasoning_fallback');
            $endpoint = rtrim($modelConfig['base_url'], '/');
            if (!str_ends_with($endpoint, '/chat/completions')) {
                $endpoint .= '/chat/completions';
            }

            $payload = [
                'model'       => $modelConfig['model'],
                'messages'    => $messages,
                'tools'       => $this->getToolsSchema(),
                'tool_choice' => 'auto',
                'temperature' => 0.2,
            ];

            $response = Http::withToken($modelConfig['api_key'])
                ->withHeaders((array) ($modelConfig['headers'] ?? []))
                ->timeout(max(10, intval($modelConfig['timeout_ms'] / 1000)))
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                Log::error('Assistant LLM API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return [
                    'success' => false,
                    'data'    => null,
                    'message' => 'Error al comunicarse con el motor de IA (' . $response->status() . ').',
                    'action'  => 'error',
                ];
            }

            $choice = $response->json('choices.0');
            $message = $choice['message'] ?? [];
            $toolCalls = $message['tool_calls'] ?? [];

            // If LLM returned tool calls, execute them
            if (!empty($toolCalls)) {
                return $this->handleToolCalls($user, $toolCalls, $messages, $endpoint, $choice, $nowInTz);
            }

            // Ordinary conversation text
            $content = $message['content'] ?? 'No recibí respuesta del asistente.';
            $parsed = $this->parseDualOutput($content, 'conversation');

            return [
                'success'             => true,
                'data'                => null,
                'message'             => $parsed['display_text'],
                'display_text'        => $parsed['display_text'],
                'speech_text'         => $parsed['speech_text'],
                'action'              => $parsed['action'],
                'categories_detected' => $parsed['categories_detected'],
                'atomic_tasks_count'  => $parsed['atomic_tasks_count'],
                'prioritized_summary' => $parsed['prioritized_summary'],
            ];
        } catch (\Throwable $e) {
            Log::error('Assistant interact exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'data'    => null,
                'message' => 'Ocurrió un error inesperado al procesar la solicitud: ' . $e->getMessage(),
                'action'  => 'error',
            ];
        }
    }

    /**
     * Build strict temporal system prompt with identity, memory & retrieval instructions.
     */
    protected function buildSystemPrompt(string $nowIso, ?User $user = null): string
    {
        $userName = trim((string) ($user->name ?? 'Usuario'));
        $firstName = !empty($userName) ? explode(' ', $userName)[0] : 'Usuario';

        return <<<SYS
IDENTIDAD Y ROLES (ESTRICTO):
- TU IDENTIDAD: Te llamas Cami. Eres la asistente personal ejecutiva inteligente, eficiente, técnica y proactiva de {$firstName}.
- IDENTIDAD DEL USUARIO: El usuario al que asistes se llama {$userName} (puedes llamarlo {$firstName}).
- REGLA DE TRATO ABSOLUTA: NUNCA llames al usuario "Cami". Tú eres Cami; el usuario es {$firstName}. Si el usuario dice "Cami", "hola Cami", "¿estás ahí Cami?", te está llamando a TI por tu nombre.
- TONO NATURAL: Sé directa, natural y conversacional. Elimina saludos robóticos, rodeos o introducciones innecesarias (ej. no uses "Como tu asistente...", "A continuación presento el reporte...", ve directo al grano).
- CONVERSACIÓN Y SALUDOS: Ante saludos, cortesías o preguntas de presencia (ej. "hola", "hola Cami", "¿estás ahí?", "buenos días", "¿cómo estás?"), responde de manera directa, cordial, cálida y concisa. NO ejecutes herramientas ni búsquedas de base de datos para responder a un saludo cotidiano.

Zona horaria de referencia: America/Cancun.
FECHA Y HORA ACTUAL DEL SISTEMA (ANCLA TEMPORAL ESTRICTA): {$nowIso}.

REGLAS TEMPORALES Y OPERATIVAS ESTRICTAS:
1. Todas las fechas y horas relativas (ej. "hoy", "mañana", "el próximo viernes", "en 2 horas") deben calcularse tomando como ancla estricta la FECHA Y HORA ACTUAL indicada arriba.
2. CITAS Y EVENTOS SIN HORA ESPECÍFICA: Si el usuario indica un día pero omite la hora, la hora de inicio por defecto debe ser exactamente las 09:00:00 (ejemplo: YYYY-MM-DDT09:00:00).
3. DURACIÓN POR DEFECTO: Si el usuario no indica hora de fin o duración para una cita, debe ser exactamente de 1 hora después de la hora de inicio.
4. FORMATO DE FECHA/HORA: Todos los parámetros temporales en tool calls deben expresarse en formato ISO 8601 estricto SIN sufijo de offset (ejemplo: YYYY-MM-DDTHH:mm:ss).

FASE 1: REGLAS DE NEGOCIO Y MODELO MENTAL (MÓDULO: CLASIFICACIÓN Y DIVISIÓN DE TAREAS / RECORDATORIOS):
1. MATRIZ DE DECISIÓN (TIPO DE REGISTRO) Y DESCOMPOSICIÓN ATÓMICA DE TAREAS:
   - Tipo "TODO" (Lista de pendientes/notas):
     * Criterio: Solicitudes de compra, tareas pendientes, encargos o recordatorios SIN hora/fecha específica ("recuérdame comprar...", "tengo que hacer...", "anota que debo...").
     * Destino: Invoca `create_workspace_note` para guardarlo en pendientes con tag 'todo'.
   - Tipo "RECORDATORIO_TEMPORAL" (Alarma / Calendario):
     * Criterio: Solicitudes que incluyan una referencia temporal explícita o relativa medible ("a las 5:00 pm", "mañana a las 9 am", "el viernes", "en 2 horas").
     * Destino: Invoca `create_reminder` (o `create_calendar_event` si es reunión/cita formal) con su fecha/hora programada.
   - REGLA DE ATOMICIDAD (DESCOMPOSICIÓN ESTRICTA):
     * Jamás crees un solo registro para múltiples objetos o acciones.
     * Si el mensaje contiene conjunciones ("y", "e"), comas o listas implícitas de cosas por adquirir o realizar:
       - Divide cada elemento en una entidad única, auto-contenida y concisa.
       - Realiza una llamada a función (tool call) independiente para CADA elemento atomizado por separado.
       - Ejemplo: Si el usuario pide comprar un paraguas y un impermeable, invoca `create_workspace_note` para "Comprar paraguas" y otro para "Comprar impermeable".
       - Ejemplo confirmación concisa: "Anotados en pendientes: 1. Comprar paraguas. 2. Comprar impermeable."

2. RESPUESTAS CONVERSACIONALES Y CONCISAS AL CREAR O MODIFICAR:
   - NO imprimas un reporte completo, inventario o volcado de base de datos cada vez que el usuario registra o modifica una tarea.
   - Si el usuario agrega un recordatorio o nota, limítate a confirmar la acción de forma breve, natural y directa (ej. "Anotado: comprar tomates" o "Recordatorio programado: comprar pan mañana a las 9 am").
   - Si el usuario agrega múltiples pendientes atomizados, confírmalos de forma compacta (ej. "Anotados en pendientes: 1. Comprar paraguas. 2. Comprar impermeable.").

3. DEDUPLICACIÓN SEMÁNTICA Y ELIMINACIÓN DE DUPLICADOS:
   - Identifica elementos redundantes o repetidos (ejemplo: "agregar función de mensajes" vs "poder enviar mensajes") y unifícalos automáticamente en una sola tarea limpia.
   - Al listar o categorizar, un elemento solo debe mostrarse una sola vez. PROHIBIDO duplicar ítems entre "Prioridades" y sus categorías temáticas (como "Personal/Compras").

4. CATEGORIZACIÓN CONTEXTUAL IMPLÍCITA:
   - Clasifica automáticamente cada entrada por contexto o dominio sin solicitar confirmación al usuario:
     * Desarrollo/Tech: Código, bugs, deploys, APIs, refactor, arquitectura, bases de datos.
     * Personal/Compras: Supermercado, farmacia, compras, salud, ejercicio.
     * Casa/Admin: Finanzas, servicios, trámites, recibos, mantenimiento.
     * General/Agenda: Citas, reuniones y acuerdos.

FASE 2: PROACTIVIDAD Y SÍNTESIS:
1. LISTADOS BAJO DEMANDA:
   - SOLO desglosa o resume pendientes cuando el usuario lo solicite EXPLÍCITAMENTE (ej. "¿qué tengo pendiente?", "revisa mis tareas de hoy"). NUNCA muestres un volcado de tareas si el usuario no lo ha pedido.
2. ELIMINACIÓN ESTRICTA DE DUPLICADOS:
   - Al presentar la información, agrupa los pendientes por su área de impacto o categoría.
   - Cada tarea o recordatorio debe aparecer UNA SOLA VEZ. NO dupliques ítems entre una lista de "Prioridades" y las categorías temáticas.
3. CONTEXTUALIZACIÓN PROACTIVA:
   - Si una tarea o recordatorio entrante se relaciona con especificaciones o notas técnicas guardadas previamente, asócialas en el contexto del registro sin generar ruido innecesario.

FASE 3: SALIDA DUAL CONVERSACIONAL (VISUAL VS VOZ):
El asistente debe devolver siempre una estructura con dos representaciones separadas del mismo mensaje:

1. Formato Visual (display_text):
   - Estructurado en Markdown limpio para pantallas (Dashboard Web y chat visual móvil).
   - Saltos de línea obligatorios entre elementos.
   - Uso de negritas para encabezados de categoría (ej. **Desarrollo/Tech**, **Personal/Compras**) y viñetas cortas (- ) para cada tarea.
   - Sin números corridos en un solo párrafo.

2. Formato Oral (speech_text para TTS):
   - El formato oral TTS será solo para la aplicación móvil, que es donde el usuario habla en tiempo real con el sintetizador de voz (TTS).
   - REGLAS DE FORMATO Y ESTILO CONVERSACIONAL (VOZ Y TEXTO):
     * PROHIBIDO ABSOLUTAMENTE el uso de Markdown en speech_text (sin asteriscos, sin negritas, sin almohadillas).
     * Prohibido el uso de viñetas, guiones, asteriscos, corchetes, números de lista ("1.", "2.") o URLs.
   - Redacción en prosa fluida y natural utilizando comas y puntos estratégicos para pausas de respiración del sintetizador de voz (TTS).
   - Agrupación por frases con conectores naturales (ej. "En desarrollo tienes pendiente...", "y de compras te falta...").
   - Integración natural de acrónimos o términos técnicos en inglés, eliminando explicaciones entre paréntesis que rompan la cadencia hablada.

MEMORIA, CONSULTA (RETRIEVAL) Y EJECUCIÓN DE HERRAMIENTAS:
- BÚSQUEDA DE CITAS: Cuando el usuario consulte qué eventos o citas tiene -> invoca `search_calendar_events`.
- BÚSQUEDA DE NOTAS: Cuando el usuario pregunte sobre ideas o notas previas -> invoca `search_notes`.
- BÚSQUEDA DE PENDIENTES Y TAREAS: Cuando el usuario consulte qué pendientes o tareas tiene -> invoca `get_pending_reminders`.
  * La herramienta recupera TANTO los recordatorios programados (con fecha/hora) como los To-Dos y compras atemporales.
  * Informa siempre de ambas categorías.
- CREACIÓN DE CITAS: Cuando exprese intención de agendar -> invoca `create_calendar_event`.
- CREACIÓN DE RECORDATORIOS: Cuando pida recordar algo con fecha/hora -> invoca `create_reminder`.
- CREACIÓN DE NOTAS / PENDIENTES: Cuando pida guardar nota o pendientes sin hora -> invoca `create_workspace_note`.
- CONSULTA DE AGENDA: Cuando pida la agenda de un rango -> invoca `get_agenda`.

Cuando emitas tu respuesta conversacional final (o turno de resumen), puedes entregarla como objeto estructurado:
{
  "display_text": "...",
  "speech_text": "...",
  "action": "...",
  "categories_detected": ["..."],
  "atomic_tasks_count": 0
}
SYS;
    }

    /**
     * JSON Schema specification for all tools (Creation + Retrieval).
     */
    public function getToolsSchema(): array
    {
        return [
            // 1. Creation tools
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'create_calendar_event',
                    'description' => 'Crea y agenda un evento o cita en el calendario del usuario.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'title' => [
                                'type'        => 'string',
                                'description' => 'Título descriptivo de la cita o evento.',
                            ],
                            'start_time' => [
                                'type'        => 'string',
                                'description' => 'Fecha y hora de inicio en formato ISO 8601 sin offset (YYYY-MM-DDTHH:mm:ss).',
                            ],
                            'end_time' => [
                                'type'        => 'string',
                                'description' => 'Fecha y hora de finalización en formato ISO 8601 sin offset (YYYY-MM-DDTHH:mm:ss). Si se omite, se asigna 1 hora después del inicio.',
                            ],
                            'description' => [
                                'type'        => 'string',
                                'description' => 'Descripción o detalles adicionales del evento.',
                            ],
                            'location' => [
                                'type'        => 'string',
                                'description' => 'Ubicación física o enlace virtual del evento.',
                            ],
                        ],
                        'required'   => ['title', 'start_time'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'create_reminder',
                    'description' => 'Crea un recordatorio para el usuario con fecha y hora programada.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'title' => [
                                'type'        => 'string',
                                'description' => 'Texto o descripción del recordatorio.',
                            ],
                            'scheduled_at' => [
                                'type'        => 'string',
                                'description' => 'Fecha y hora programada para el recordatorio en formato ISO 8601 sin offset (YYYY-MM-DDTHH:mm:ss).',
                            ],
                            'priority' => [
                                'type'        => 'string',
                                'enum'        => ['low', 'medium', 'high'],
                                'description' => 'Nivel de prioridad del recordatorio (low, medium, high). Por defecto medium.',
                            ],
                        ],
                        'required'   => ['title', 'scheduled_at'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'create_workspace_note',
                    'description' => 'Crea una nota o ítem de To-Do/pendiente en el workspace con etiquetas temáticas.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'title' => [
                                'type'        => 'string',
                                'description' => 'Título conciso del To-Do o nota (ej. "Comprar paraguas").',
                            ],
                            'content' => [
                                'type'        => 'string',
                                'description' => 'Cuerpo detallado de la nota (opcional). Si se omite se usará el título.',
                            ],
                            'tags' => [
                                'type'        => 'array',
                                'items'       => ['type' => 'string'],
                                'description' => 'Etiquetas temáticas (ej. ["todo", "compras"]).',
                            ],
                        ],
                        'required'   => ['title'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'get_agenda',
                    'description' => 'Obtiene la agenda completa (citas y recordatorios) del usuario en un rango de fechas.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'start_date' => [
                                'type'        => 'string',
                                'description' => 'Fecha inicial del rango de consulta (YYYY-MM-DD o ISO 8601).',
                            ],
                            'end_date' => [
                                'type'        => 'string',
                                'description' => 'Fecha final del rango de consulta (YYYY-MM-DD o ISO 8601).',
                            ],
                        ],
                        'required'   => ['start_date', 'end_date'],
                    ],
                ],
            ],

            // 2. Retrieval tools (RAG / Memoria de contexto)
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'search_calendar_events',
                    'description' => 'Busca eventos o citas en el calendario del usuario por término de búsqueda (query) o por rango de fechas (start_date, end_date).',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => [
                                'type'        => 'string',
                                'description' => 'Texto o palabra clave a buscar en el título o descripción del evento.',
                            ],
                            'start_date' => [
                                'type'        => 'string',
                                'description' => 'Fecha inicial del rango de búsqueda en formato ISO 8601 o YYYY-MM-DD (ej. "2026-10-23").',
                            ],
                            'end_date' => [
                                'type'        => 'string',
                                'description' => 'Fecha final del rango de búsqueda en formato ISO 8601 o YYYY-MM-DD (ej. "2026-10-30").',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'search_notes',
                    'description' => 'Busca notas guardadas en el workspace del usuario mediante texto de búsqueda o filtrando por etiqueta temática.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => [
                                'type'        => 'string',
                                'description' => 'Texto o palabra clave a buscar dentro del título o contenido de las notas.',
                            ],
                            'tag' => [
                                'type'        => 'string',
                                'description' => 'Etiqueta o tag específico por el cual filtrar las notas (ej. "desarrollo", "finanzas").',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'get_pending_reminders',
                    'description' => 'Recupera todos los pendientes del usuario: tanto los recordatorios programados con fecha/hora como la lista de To-Dos y compras atemporales del workspace.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'include_completed' => [
                                'type'        => 'boolean',
                                'description' => 'Si es true, incluye tanto pendientes como completados. Por defecto false (solo pendientes activos).',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Executes tool calls and persists in MongoDB or performs retrieval second-turn.
     */
    protected function handleToolCalls(User $user, array $toolCalls, array $messages, string $endpoint, array $choice, Carbon $nowInTz): array
    {
        $retrievalTools = ['search_calendar_events', 'search_notes', 'get_pending_reminders', 'get_agenda'];

        $hasRetrieval = false;
        $toolMessages = [];
        $retrievalResults = [];
        $creationResults = [];
        $primaryAction = 'multiple_actions';

        foreach ($toolCalls as $index => $call) {
            $fnName  = $call['function']['name'] ?? '';
            $callId  = $call['id'] ?? ('call_' . ($index + 1));
            $rawArgs = $call['function']['arguments'] ?? '{}';
            $args    = json_decode($rawArgs, true) ?: [];

            if (in_array($fnName, $retrievalTools, true)) {
                $hasRetrieval = true;
                if ($primaryAction === 'multiple_actions') {
                    $primaryAction = $fnName;
                }

                $resData = [];
                switch ($fnName) {
                    case 'search_calendar_events':
                        $resData = $this->executeSearchCalendarEvents($user, $args);
                        break;
                    case 'search_notes':
                        $resData = $this->executeSearchNotes($user, $args);
                        break;
                    case 'get_pending_reminders':
                        $resData = $this->executeGetPendingReminders($user, $args);
                        break;
                    case 'get_agenda':
                        $agendaRes = $this->executeGetAgenda($user, $args);
                        $resData = $agendaRes['data'] ?? [];
                        break;
                }

                $retrievalResults[$fnName] = $resData;

                $toolMessages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $callId,
                    'content'      => json_encode([
                        'query_tool'    => $fnName,
                        'results_count' => is_array($resData) ? count($resData) : 1,
                        'records'       => $resData,
                    ], JSON_UNESCAPED_UNICODE),
                ];
            } else {
                // Creation tools
                $actionResult = null;
                switch ($fnName) {
                    case 'create_calendar_event':
                        $actionResult = $this->executeCreateCalendarEvent($user, $args);
                        break;
                    case 'create_reminder':
                        $actionResult = $this->executeCreateReminder($user, $args);
                        break;
                    case 'create_workspace_note':
                        $actionResult = $this->executeCreateWorkspaceNote($user, $args);
                        break;
                    default:
                        $actionResult = [
                            'success' => false,
                            'data'    => null,
                            'message' => "Acción desconocida solicitada: {$fnName}.",
                            'action'  => 'unknown_action',
                        ];
                }

                $creationResults[] = $actionResult;

                $toolMessages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $callId,
                    'content'      => json_encode([
                        'action'  => $fnName,
                        'success' => $actionResult['success'] ?? false,
                        'message' => $actionResult['message'] ?? '',
                    ], JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        // If any retrieval was called, LLM must synthesize the conversational response
        if ($hasRetrieval) {
            $dataToReturn = count($retrievalResults) === 1 ? reset($retrievalResults) : $retrievalResults;
            return $this->executeSecondTurnRetrieval($user, $messages, $endpoint, $choice, $toolMessages, $dataToReturn, $primaryAction);
        }

        // Single creation tool call
        if (count($creationResults) === 1) {
            return $creationResults[0];
        }

        // Multiple creation tool calls (e.g. atomized To-Dos or Reminders)
        $actionsByType = [
            'notes'     => [],
            'reminders' => [],
            'events'    => [],
        ];

        foreach ($toolCalls as $idx => $call) {
            $fnName = $call['function']['name'] ?? '';
            $rawArgs = $call['function']['arguments'] ?? '{}';
            $args    = json_decode($rawArgs, true) ?: [];
            $res     = $creationResults[$idx] ?? null;

            if ($res && ($res['success'] ?? false)) {
                if ($fnName === 'create_workspace_note') {
                    $actionsByType['notes'][] = $res['data']->title ?? ($args['title'] ?? 'Pendiente');
                } elseif ($fnName === 'create_reminder') {
                    $actionsByType['reminders'][] = $res['data']->title ?? ($args['title'] ?? 'Recordatorio');
                } elseif ($fnName === 'create_calendar_event') {
                    $actionsByType['events'][] = $res['data']->title ?? ($args['title'] ?? 'Evento');
                }
            }
        }

        $llmContent = trim((string) ($choice['message']['content'] ?? ''));
        if (!empty($llmContent)) {
            $parsed = $this->parseDualOutput($llmContent, 'multiple_actions');
            $displayText = $parsed['display_text'];
            $speechText  = $parsed['speech_text'];
            $categories  = $parsed['categories_detected'];
        } else {
            $summaryParts = [];
            $displayParts = [];
            $categories   = [];

            if (!empty($actionsByType['notes'])) {
                $categories[] = 'Personal/Compras';
                $items = array_map(fn($idx, $item) => ($idx + 1) . '. ' . rtrim($item, '.') . '.', array_keys($actionsByType['notes']), $actionsByType['notes']);
                $summaryParts[] = 'Anotados en pendientes: ' . implode(' ', $items);
                $noteLines = array_map(fn($item) => '- ' . rtrim($item, '.'), $actionsByType['notes']);
                $displayParts[] = "**Personal/Compras:**
" . implode("
", $noteLines);
            }
            if (!empty($actionsByType['reminders'])) {
                $categories[] = 'Casa/Admin';
                $items = array_map(fn($idx, $item) => ($idx + 1) . '. ' . rtrim($item, '.') . '.', array_keys($actionsByType['reminders']), $actionsByType['reminders']);
                $summaryParts[] = 'Recordatorios programados: ' . implode(' ', $items);
                $remLines = array_map(fn($item) => '- ' . rtrim($item, '.'), $actionsByType['reminders']);
                $displayParts[] = "**Casa/Admin:**
" . implode("
", $remLines);
            }
            if (!empty($actionsByType['events'])) {
                $categories[] = 'General/Agenda';
                $items = array_map(fn($idx, $item) => ($idx + 1) . '. ' . rtrim($item, '.') . '.', array_keys($actionsByType['events']), $actionsByType['events']);
                $summaryParts[] = 'Citas agendadas: ' . implode(' ', $items);
                $evLines = array_map(fn($item) => '- ' . rtrim($item, '.'), $actionsByType['events']);
                $displayParts[] = "**Citas agendadas:**
" . implode("
", $evLines);
            }

            $speechText = !empty($summaryParts)
                ? implode(' ', $summaryParts)
                : 'Se procesaron ' . count($creationResults) . ' acciones correctamente.';
            $displayText = !empty($displayParts)
                ? implode("

", $displayParts)
                : $speechText;
        }

        return [
            'success'             => true,
            'data'                => array_map(fn($e) => $e['data'] ?? null, $creationResults),
            'message'             => $this->cleanVoiceText($speechText), // Backwards compatibility for exact unit test match
            'display_text'        => $displayText,
            'speech_text'         => $this->cleanVoiceText($speechText),
            'action'              => 'multiple_actions',
            'categories_detected' => $categories,
            'atomic_tasks_count'  => count($creationResults),
        ];
    }

    /**
     * Injects retrieved MongoDB data back into a second conversational turn with the LLM.
     * Passes all tool responses to avoid OpenAI/DeepSeek 400 Bad Request error.
     */
    protected function executeSecondTurnRetrieval(User $user, array $messages, string $endpoint, array $choice, array $toolMessages, array $dataToReturn, string $primaryAction): array
    {
        try {
            $assistantMessage = $choice['message'] ?? [];
            $messages[] = $assistantMessage;

            foreach ($toolMessages as $tm) {
                $messages[] = $tm;
            }

            $modelConfig = $this->modelRouter->getActiveModelConfig('reasoning_fallback');

            $secondResponse = Http::withToken($modelConfig['api_key'] ?? $this->apiKey)
                ->withHeaders((array) ($modelConfig['headers'] ?? []))
                ->timeout(45)
                ->post($endpoint, [
                    'model'       => $modelConfig['model'] ?? $this->model,
                    'messages'    => $messages,
                    'temperature' => 0.3,
                ]);

            if ($secondResponse->successful()) {
                $naturalContent = (string) $secondResponse->json('choices.0.message.content');
                if (!empty(trim($naturalContent))) {
                    $parsed = $this->parseDualOutput(trim($naturalContent), $primaryAction);
                    return [
                        'success'             => true,
                        'data'                => $dataToReturn,
                        'message'             => $parsed['display_text'],
                        'display_text'        => $parsed['display_text'],
                        'speech_text'         => $parsed['speech_text'],
                        'action'              => $primaryAction,
                        'categories_detected' => $parsed['categories_detected'],
                        'atomic_tasks_count'  => $parsed['atomic_tasks_count'],
                        'prioritized_summary' => $parsed['prioritized_summary'],
                    ];
                }
            } else {
                Log::error('Assistant second turn LLM error', [
                    'status' => $secondResponse->status(),
                    'body'   => $secondResponse->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Second turn retrieval exception: " . $e->getMessage());
        }

        // Friendly fallback message in plain conversational text for TTS & WhatsApp
        $fallbackMessage = $this->buildFriendlyRetrievalFallback($dataToReturn, $primaryAction);
        $cleanSpeech = $this->cleanVoiceText($fallbackMessage);

        return [
            'success'             => true,
            'data'                => $dataToReturn,
            'message'             => $cleanSpeech,
            'display_text'        => $fallbackMessage,
            'speech_text'         => $cleanSpeech,
            'action'              => $primaryAction,
            'categories_detected' => [],
            'atomic_tasks_count'  => 0,
            'prioritized_summary' => null,
        ];
    }

    /**
     * Generates a warm, user-friendly conversational fallback when second turn LLM is unavailable.
     * Formats strictly in clean plain text suitable for TTS speech and WhatsApp messages.
     */
    protected function buildFriendlyRetrievalFallback(array $results, string $primaryAction): string
    {
        if ($primaryAction === 'get_pending_reminders') {
            $scheduled = $results['recordatorios_programados'] ?? [];
            $todos     = $results['tareas_y_pendientes_atemporales'] ?? [];

            if (empty($scheduled) && empty($todos)) {
                return 'No tienes pendientes ni recordatorios activos en este momento.';
            }

            $parts = ['Aquí tienes tus pendientes.'];
            if (!empty($scheduled)) {
                $parts[] = 'Recordatorios programados:';
                $i = 1;
                foreach ($scheduled as $r) {
                    $when = !empty($r['scheduled_at']) ? ' para el ' . $r['scheduled_at'] : '';
                    $parts[] = "{$i}. {$r['title']}{$when}.";
                    $i++;
                }
            }
            if (!empty($todos)) {
                $parts[] = 'To-Dos y tareas pendientes:';
                $j = 1;
                foreach ($todos as $t) {
                    $parts[] = "{$j}. {$t['title']}.";
                    $j++;
                }
            }
            return implode(' ', $parts);
        }

        if ($primaryAction === 'search_calendar_events') {
            $count = count($results);
            if ($count === 0) {
                return 'No tienes eventos ni citas agendadas en el periodo solicitado.';
            }
            $parts = ["Encontré {$count} evento(s) en tu calendario:"];
            $i = 1;
            foreach ($results as $e) {
                $start = $e['start_time'] ?? '';
                $when = $start ? " el {$start}" : '';
                $parts[] = "{$i}. {$e['title']}{$when}.";
                $i++;
            }
            return implode(' ', $parts);
        }

        if ($primaryAction === 'search_notes') {
            $count = count($results);
            if ($count === 0) {
                return 'No encontré notas que coincidan con tu búsqueda.';
            }
            $parts = ["Encontré {$count} nota(s) en tu espacio de trabajo:"];
            $i = 1;
            foreach ($results as $n) {
                $parts[] = "{$i}. {$n['title']}.";
                $i++;
            }
            return implode(' ', $parts);
        }

        return 'He revisado tu información en el sistema.';
    }

    /**
     * Execute search_calendar_events retrieval query.
     */
    protected function executeSearchCalendarEvents(User $user, array $args): array
    {
        $userId = (string) $user->id;
        $builder = CalendarEvent::where('user_id', $userId);

        if (!empty($args['start_date'])) {
            $builder->where('start_time', '>=', Carbon::parse($args['start_date'], $this->timezone)->startOfDay());
        }
        if (!empty($args['end_date'])) {
            $builder->where('start_time', '<=', Carbon::parse($args['end_date'], $this->timezone)->endOfDay());
        }
        if (!empty($args['query'])) {
            $q = trim((string) $args['query']);
            $builder->where(function ($sub) use ($q) {
                $sub->where('title', 'regex', new \MongoDB\BSON\Regex($q, 'i'))
                    ->orWhere('description', 'regex', new \MongoDB\BSON\Regex($q, 'i'));
            });
        }

        $events = $builder->orderBy('start_time', 'asc')->limit(40)->get();

        return $events->map(function ($e) {
            return [
                'id'          => (string) ($e->id ?? $e->_id),
                'title'       => $e->title,
                'start_time'  => $e->start_time ? $e->start_time->format('Y-m-d H:i') : null,
                'end_time'    => $e->end_time ? $e->end_time->format('Y-m-d H:i') : null,
                'location'    => $e->location,
                'description' => $e->description,
                'status'      => $e->status,
            ];
        })->all();
    }

    /**
     * Execute search_notes retrieval query.
     * Matches tags across both BSON arrays and JSON strings.
     */
    protected function executeSearchNotes(User $user, array $args): array
    {
        $userId = (string) $user->id;
        $builder = WorkspaceNote::where('user_id', $userId);

        if (!empty($args['tag'])) {
            $tag = trim((string) $args['tag']);
            $builder->where(function ($sub) use ($tag) {
                $sub->where('tags', 'all', [$tag])
                    ->orWhere('tags', 'regex', new \MongoDB\BSON\Regex($tag, 'i'));
            });
        }

        if (!empty($args['query'])) {
            $q = trim((string) $args['query']);
            $builder->where(function ($sub) use ($q) {
                $sub->where('title', 'regex', new \MongoDB\BSON\Regex($q, 'i'))
                    ->orWhere('content', 'regex', new \MongoDB\BSON\Regex($q, 'i'));
            });
        }

        $notes = $builder->orderBy('is_pinned', 'desc')->orderBy('created_at', 'desc')->limit(40)->get();

        return $notes->map(function ($n) {
            $rawTags = $n->tags;
            $tags = is_array($rawTags) ? $rawTags : (json_decode((string) $rawTags, true) ?: []);

            return [
                'id'         => (string) ($n->id ?? $n->_id),
                'title'      => $n->title,
                'content'    => $n->content,
                'tags'       => $tags,
                'is_pinned'  => (bool) $n->is_pinned,
                'created_at' => $n->created_at ? $n->created_at->format('Y-m-d H:i') : null,
            ];
        })->all();
    }

    /**
     * Execute get_pending_reminders retrieval query.
     * Retrieves BOTH scheduled reminders (con fecha/hora) AND atemporal workspace To-Dos / tasks.
     */
    protected function executeGetPendingReminders(User $user, array $args): array
    {
        $userId = (string) $user->id;

        // 1. Scheduled Reminders
        $builder = Reminder::where('user_id', $userId);

        if (empty($args['include_completed'])) {
            $builder->where('status', 'pending');
        }

        $reminders = $builder->orderBy('scheduled_at', 'asc')->limit(40)->get();

        $scheduledFormatted = $reminders->map(function ($r) {
            return [
                'id'           => (string) ($r->id ?? $r->_id),
                'title'        => $r->title,
                'scheduled_at' => $r->scheduled_at ? $r->scheduled_at->format('Y-m-d H:i') : null,
                'status'       => $r->status,
                'priority'     => $r->priority,
                'channel'      => $r->channel,
                'type'         => 'recordatorio_programado',
            ];
        })->all();

        // 2. Atemporal To-Dos and tasks from WorkspaceNote
        $notes = WorkspaceNote::where('user_id', $userId)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $todosFormatted = [];
        foreach ($notes as $n) {
            $rawTags = $n->tags;
            if (is_string($rawTags)) {
                $tags = json_decode($rawTags, true) ?: [$rawTags];
            } elseif (is_array($rawTags)) {
                $tags = $rawTags;
            } else {
                $tags = [];
            }

            $todosFormatted[] = [
                'id'         => (string) ($n->id ?? $n->_id),
                'title'      => $n->title,
                'content'    => $n->content,
                'tags'       => $tags,
                'is_pinned'  => (bool) $n->is_pinned,
                'created_at' => $n->created_at ? $n->created_at->format('Y-m-d H:i') : null,
                'type'       => 'tarea_atemporal_todo',
            ];
        }

        return [
            'recordatorios_programados'       => $scheduledFormatted,
            'tareas_y_pendientes_atemporales' => $todosFormatted,
            'total_pendientes'                => count($scheduledFormatted) + count($todosFormatted),
        ];
    }

    /**
     * Execute create_calendar_event tool.
     */
    protected function executeCreateCalendarEvent(User $user, array $args): array
    {
        $title     = trim((string) ($args['title'] ?? 'Nueva cita'));
        $startTimeRaw = (string) ($args['start_time'] ?? '');
        $endTimeRaw   = (string) ($args['end_time'] ?? '');

        // If time part missing, default to 09:00:00
        if (!empty($startTimeRaw) && !str_contains($startTimeRaw, 'T')) {
            $startTimeRaw .= 'T09:00:00';
        }

        $startTime = Carbon::parse($startTimeRaw, $this->timezone);

        if (!empty($endTimeRaw)) {
            if (!str_contains($endTimeRaw, 'T')) {
                $endTimeRaw .= 'T10:00:00';
            }
            $endTime = Carbon::parse($endTimeRaw, $this->timezone);
        } else {
            // Default 1 hour duration
            $endTime = (clone $startTime)->addHour();
        }

        $event = CalendarEvent::create([
            'user_id'     => (string) $user->id,
            'title'       => $title,
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'description' => $args['description'] ?? null,
            'location'    => $args['location'] ?? null,
            'status'      => 'scheduled',
        ]);

        try {
            app(\App\Services\GoogleCalendar\GoogleCalendarService::class)->pushEventToGoogle($user, $event);
        } catch (\Throwable $e) {
            Log::warning('Google Calendar auto-push: ' . $e->getMessage());
        }

        $formattedDate = $startTime->locale('es')->isoFormat('D [de] MMMM [a las] HH:mm');
        $msg = "Evento {$title} agendado para el {$formattedDate}.";

        return [
            'success'      => true,
            'data'         => $event,
            'message'      => $msg,
            'display_text' => $msg,
            'speech_text'  => $msg,
            'action'       => 'create_calendar_event',
        ];
    }

    /**
     * Execute create_reminder tool.
     */
    protected function executeCreateReminder(User $user, array $args): array
    {
        $title = trim((string) ($args['title'] ?? 'Nuevo recordatorio'));
        $scheduledAtRaw = (string) ($args['scheduled_at'] ?? '');

        if (!empty($scheduledAtRaw) && !str_contains($scheduledAtRaw, 'T')) {
            $scheduledAtRaw .= 'T09:00:00';
        }

        $scheduledAt = Carbon::parse($scheduledAtRaw, $this->timezone);
        $priority    = in_array($args['priority'] ?? '', ['low', 'medium', 'high'])
            ? $args['priority']
            : 'medium';

        $reminder = Reminder::create([
            'user_id'      => (string) $user->id,
            'title'        => $title,
            'scheduled_at' => $scheduledAt,
            'status'       => 'pending',
            'priority'     => $priority,
            'channel'      => 'whatsapp',
        ]);

        $formattedDate = $scheduledAt->locale('es')->isoFormat('D [de] MMMM [a las] HH:mm');
        $msg = "Recordatorio {$title} programado para el {$formattedDate}.";

        return [
            'success'      => true,
            'data'         => $reminder,
            'message'      => $msg,
            'display_text' => $msg,
            'speech_text'  => $msg,
            'action'       => 'create_reminder',
        ];
    }

    /**
     * Execute create_workspace_note tool.
     */
    protected function executeCreateWorkspaceNote(User $user, array $args): array
    {
        $title   = trim((string) ($args['title'] ?? 'Nota'));
        $content = trim((string) ($args['content'] ?? ''));
        if ($content === '') {
            $content = $title;
        }

        $tags = is_array($args['tags'] ?? null) ? $args['tags'] : [];
        if (empty($tags)) {
            $tags = ['todo', 'pendiente'];
        }

        $note = WorkspaceNote::create([
            'user_id'   => (string) $user->id,
            'title'     => $title,
            'content'   => $content,
            'tags'      => array_values($tags),
            'is_pinned' => false,
        ]);

        $msg = "Anotado: {$title}.";

        return [
            'success'      => true,
            'data'         => $note,
            'message'      => $msg,
            'display_text' => $msg,
            'speech_text'  => $msg,
            'action'       => 'create_workspace_note',
        ];
    }

    /**
     * Execute get_agenda tool.
     */
    protected function executeGetAgenda(User $user, array $args): array
    {
        $startDate = $args['start_date'] ?? Carbon::now($this->timezone)->toDateString();
        $endDate   = $args['end_date'] ?? Carbon::now($this->timezone)->toDateString();

        $agenda = $this->getAgenda($user, $startDate, $endDate);

        $eventsCount    = count($agenda['events'] ?? []);
        $remindersCount = count($agenda['reminders'] ?? []);

        return [
            'success' => true,
            'data'    => $agenda,
            'message' => "Agenda obtenida: {$eventsCount} eventos y {$remindersCount} recordatorios encontrados.",
            'action'  => 'get_agenda',
        ];
    }

    /**
     * Get feed of upcoming events, pending reminders and notes.
     */
    public function getFeed(User $user): array
    {
        $userId = (string) $user->id;
        $now    = Carbon::now($this->timezone);

        $events = CalendarEvent::where('user_id', $userId)
            ->where('start_time', '>=', $now->copy()->startOfDay())
            ->orderBy('start_time', 'asc')
            ->limit(30)
            ->get();

        $reminders = Reminder::where('user_id', $userId)
            ->where('status', 'pending')
            ->orderBy('scheduled_at', 'asc')
            ->limit(50)
            ->get();

        $notes = WorkspaceNote::where('user_id', $userId)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return [
            'events'    => $events,
            'reminders' => $reminders,
            'notes'     => $notes,
        ];
    }

    /**
     * Get agenda for a user within a date range.
     */
    public function getAgenda(User $user, string $startDate, string $endDate): array
    {
        $userId = (string) $user->id;

        $start = Carbon::parse($startDate, $this->timezone)->startOfDay();
        $end   = Carbon::parse($endDate, $this->timezone)->endOfDay();

        $events = CalendarEvent::where('user_id', $userId)
            ->where('start_time', '>=', $start)
            ->where('start_time', '<=', $end)
            ->orderBy('start_time', 'asc')
            ->get();

        $reminders = Reminder::where('user_id', $userId)
            ->where('scheduled_at', '>=', $start)
            ->where('scheduled_at', '<=', $end)
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return [
            'start_date' => $start->toIso8601String(),
            'end_date'   => $end->toIso8601String(),
            'events'     => $events,
            'reminders'  => $reminders,
        ];
    }

    /**
     * Transcribe audio file using Groq Whisper, Deepgram, or OpenAI Whisper.
     *
     * @param UploadedFile|string $file
     * @return string
     */
    public function transcribeAudio($file): string
    {
        $cleanupFiles = [];

        try {
            $realPath = null;
            $fileName = 'audio.m4a';
            $rawMime  = 'audio/m4a';

            if ($file instanceof UploadedFile) {
                $realPath = $file->getRealPath();
                $fileName = $file->getClientOriginalName() ?: 'audio.m4a';
                $rawMime  = $file->getClientMimeType() ?: ($file->getMimeType() ?: 'audio/m4a');
            } elseif (is_string($file)) {
                $isPath = file_exists($file)
                    || str_starts_with($file, '/')
                    || str_starts_with($file, './')
                    || str_starts_with($file, '../')
                    || preg_match('/\.(m4a|mp3|ogg|opus|wav|webm|aac|flac|3gp|amr|pcm|raw)$/i', $file);

                if ($isPath) {
                    if (!file_exists($file)) {
                        throw new \InvalidArgumentException("El archivo de audio no existe en: {$file}");
                    }
                    $realPath = $file;
                    $fileName = basename($file);
                    $rawMime  = function_exists('mime_content_type') ? (mime_content_type($file) ?: 'audio/m4a') : 'audio/m4a';
                } else {
                    // Posible Base64 o string binario directo
                    $tmp = tempnam(sys_get_temp_dir(), 'voice_raw_');
                    $cleanupFiles[] = $tmp;
                    file_put_contents($tmp, $file);
                    $realPath = $tmp;
                    $fileName = 'audio.m4a';
                    $rawMime  = 'audio/m4a';
                }
            } else {
                throw new \InvalidArgumentException("Tipo de archivo de audio no soportado.");
            }

            if (!file_exists($realPath) || filesize($realPath) === 0) {
                throw new \InvalidArgumentException("El archivo de audio no existe o está vacío (0 bytes) en: {$realPath}");
            }

            // Inspeccionar contenido: ¿Es Base64?
            $fileContents = file_get_contents($realPath);
            if (empty($fileContents)) {
                throw new \InvalidArgumentException("El contenido del audio está vacío tras la lectura.");
            }

            $decodedBinary = null;
            if (preg_match('/^data:([a-zA-Z0-9_\-\/]+);base64,(.+)$/s', trim($fileContents), $matches)) {
                $decoded = base64_decode($matches[2], true);
                if ($decoded !== false && strlen($decoded) > 0) {
                    $decodedBinary = $decoded;
                    $rawMime = $matches[1];
                }
            } elseif (strlen($fileContents) > 32 && !str_starts_with($fileContents, 'RIFF') && !str_starts_with($fileContents, 'ID3') && !str_starts_with($fileContents, 'OggS') && !str_contains(substr($fileContents, 0, 16), 'ftyp')) {
                $cleaned = preg_replace('/\s+/', '', $fileContents);
                if (preg_match('/^[A-Za-z0-9+\/]+=*$/', substr($cleaned, 0, 2048))) {
                    $decoded = base64_decode($cleaned, true);
                    if ($decoded !== false && strlen($decoded) > 0) {
                        $decodedBinary = $decoded;
                    }
                }
            }

            if ($decodedBinary !== null) {
                $tmpDecoded = tempnam(sys_get_temp_dir(), 'voice_b64_');
                file_put_contents($tmpDecoded, $decodedBinary);
                $cleanupFiles[] = $tmpDecoded;
                $realPath = $tmpDecoded;
                $fileContents = $decodedBinary;
            }

            // Si ffmpeg está disponible, convertirlo a MP3 estandarizado (16kHz mono) para máxima compatibilidad
            // con todos los motores de transcripción (especialmente para audios de Android/WhatsApp).
            if ($this->isFfmpegAvailable()) {
                $convertedPath = $this->convertAudioToMp3($realPath, $fileName);
                if ($convertedPath !== null) {
                    $cleanupFiles[] = $convertedPath;
                    $realPath = $convertedPath;
                    $fileName = 'audio.mp3';
                    $rawMime  = 'audio/mpeg';
                    $fileContents = file_get_contents($realPath);
                }
            }

            $cleanMime = $this->normalizeMimeType($fileName, $rawMime);
            $driver = (string) config('services.transcription.driver', 'groq');

            // Determinar orden de proveedores según driver preferido
            $providers = $driver === 'deepgram'
                ? ['deepgram', 'groq', 'whisper']
                : ['groq', 'deepgram', 'whisper'];

            foreach ($providers as $provider) {
                $transcript = null;
                switch ($provider) {
                    case 'groq':
                        $transcript = $this->transcribeWithGroq($fileContents, $fileName, $cleanMime);
                        break;
                    case 'deepgram':
                        $transcript = $this->transcribeWithDeepgram($fileContents, $cleanMime);
                        break;
                    case 'whisper':
                        $transcript = $this->transcribeWithWhisperFallback($fileContents, $fileName);
                        break;
                }

                if (!empty($transcript)) {
                    return $transcript;
                }
            }

            throw new \RuntimeException('No fue posible transcribir el archivo de audio. Verifique que el audio contenga datos válidos o revise las claves de Groq y Deepgram.');
        } finally {
            foreach ($cleanupFiles as $f) {
                if (file_exists($f)) {
                    @unlink($f);
                }
            }
        }
    }

    /**
     * Verifica si la herramienta ffmpeg está instalada y ejecutable en el sistema.
     */
    protected function isFfmpegAvailable(): bool
    {
        static $available = null;
        if ($available === null) {
            $path = trim((string) @shell_exec('which ffmpeg 2>/dev/null'));
            $available = !empty($path) && is_executable($path);
        }
        return $available;
    }

    /**
     * Convierte cualquier audio (WhatsApp Ogg Opus, WebM, 3GP, AMR, PCM crudo de Android, etc.) a MP3 mono a 16kHz.
     */
    protected function convertAudioToMp3(string $sourcePath, string $originalFileName = ''): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'transcribe_conv_');
        $out = $tmp . '.mp3';
        @unlink($tmp);

        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $inputPath = $sourcePath;
        $tempInputWithExt = null;

        if (!empty($ext) && !str_ends_with(strtolower($sourcePath), '.' . $ext)) {
            $tempInputWithExt = tempnam(sys_get_temp_dir(), 'transcribe_in_') . '.' . $ext;
            if (@copy($sourcePath, $tempInputWithExt)) {
                $inputPath = $tempInputWithExt;
            }
        }

        try {
            // 1. Intento estándar de conversión de contenedor
            $cmd = sprintf(
                'ffmpeg -y -v error -i %s -vn -ar 16000 -ac 1 -c:a libmp3lame -b:a 64k %s 2>&1',
                escapeshellarg($inputPath),
                escapeshellarg($out)
            );
            $output = [];
            @exec($cmd, $output, $exitCode);

            if ($exitCode === 0 && file_exists($out) && filesize($out) > 0) {
                return $out;
            }
            if ($exitCode === 0 && file_exists($out) && filesize($out) > 0) {
                return $out;
            }

            // 2. Si falló, intentar como Raw PCM 16-bit LE a 16kHz mono (estándar de Android AudioRecord)
            $cmdRaw16 = sprintf(
                'ffmpeg -y -v error -f s16le -ar 16000 -ac 1 -i %s -vn -ar 16000 -ac 1 -c:a libmp3lame -b:a 64k %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($out)
            );
            $outputRaw16 = [];
            @exec($cmdRaw16, $outputRaw16, $exitCodeRaw16);

            if ($exitCodeRaw16 === 0 && file_exists($out) && filesize($out) > 0) {
                Log::info('Audio converted to MP3 using raw PCM (16kHz mono) demuxer.');
                return $out;
            }

            // 3. Si falló, intentar como Raw PCM 16-bit LE a 44.1kHz mono (tasa de muestreo estándar de hardware Android)
            $cmdRaw44 = sprintf(
                'ffmpeg -y -v error -f s16le -ar 44100 -ac 1 -i %s -vn -ar 16000 -ac 1 -c:a libmp3lame -b:a 64k %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($out)
            );
            $outputRaw44 = [];
            @exec($cmdRaw44, $outputRaw44, $exitCodeRaw44);

            if ($exitCodeRaw44 === 0 && file_exists($out) && filesize($out) > 0) {
                Log::info('Audio converted to MP3 using raw PCM (44.1kHz mono) demuxer.');
                return $out;
            }

            // 4. Si falló, intentar como Raw PCM 16-bit LE a 8kHz mono (telefonía / AMR)
            $cmdRaw8 = sprintf(
                'ffmpeg -y -v error -f s16le -ar 8000 -ac 1 -i %s -vn -ar 16000 -ac 1 -c:a libmp3lame -b:a 64k %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($out)
            );
            $outputRaw8 = [];
            @exec($cmdRaw8, $outputRaw8, $exitCodeRaw8);

            if ($exitCodeRaw8 === 0 && file_exists($out) && filesize($out) > 0) {
                Log::info('Audio converted to MP3 using raw PCM (8kHz mono) demuxer.');
                return $out;
            }

            // 2. Si falló, intentar como Raw PCM 16-bit LE a 16kHz mono (estándar de Android AudioRecord)
            $cmdRaw16 = sprintf(
                'ffmpeg -y -v error -f s16le -ar 16000 -ac 1 -i %s -vn -ar 16000 -ac 1 -c:a libmp3lame -b:a 64k %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($out)
            );
            $outputRaw16 = [];
            @exec($cmdRaw16, $outputRaw16, $exitCodeRaw16);

            if ($exitCodeRaw16 === 0 && file_exists($out) && filesize($out) > 0) {
                Log::info('Audio converted to MP3 using raw PCM (16kHz mono) demuxer.');
                return $out;
            }

            // 3. Si falló, intentar como Raw PCM 16-bit LE a 44.1kHz mono (tasa de muestreo estándar de hardware Android)
            $cmdRaw44 = sprintf(
                'ffmpeg -y -v error -f s16le -ar 44100 -ac 1 -i %s -vn -ar 16000 -ac 1 -c:a libmp3lame -b:a 64k %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($out)
            );
            $outputRaw44 = [];
            @exec($cmdRaw44, $outputRaw44, $exitCodeRaw44);

            if ($exitCodeRaw44 === 0 && file_exists($out) && filesize($out) > 0) {
                Log::info('Audio converted to MP3 using raw PCM (44.1kHz mono) demuxer.');
                return $out;
            }

            Log::warning('ffmpeg conversion to MP3 failed for both standard and raw PCM modes: ' . implode("\n", $output));
            return null;
        } finally {
            if ($tempInputWithExt !== null && file_exists($tempInputWithExt)) {
                @unlink($tempInputWithExt);
            }
        }
    }

    /**
     * Normaliza y valida el Content-Type para los servicios de IA de transcripción.
     */
    protected function normalizeMimeType(string $fileName, string $detectedMime): string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $extensionMap = [
            'm4a'  => 'audio/m4a',
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'ogg'  => 'audio/ogg',
            'opus' => 'audio/ogg',
            'webm' => 'audio/webm',
            'flac' => 'audio/flac',
            'aac'  => 'audio/aac',
            '3gp'  => 'video/3gpp',
            'amr'  => 'audio/amr',
        ];

        if (isset($extensionMap[$ext])) {
            return $extensionMap[$ext];
        }

        if (str_starts_with($detectedMime, 'audio/') || in_array($detectedMime, ['video/mp4', 'video/3gpp', 'video/webm'])) {
            return $detectedMime;
        }

        return 'audio/m4a';
    }

    /**
     * Transcribe usando Groq Whisper (Whisper-large-v3-turbo).
     */
    protected function transcribeWithGroq(string $fileContents, string $fileName, string $mimeType): ?string
    {
        $apiKey = (string) config('services.groq.api_key', env('GROQ_API_KEY', ''));
        $url    = (string) config('services.groq.whisper_url', 'https://api.groq.com/openai/v1/audio/transcriptions');
        $model  = (string) config('services.groq.whisper_model', 'whisper-large-v3-turbo');

        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->attach('file', $fileContents, $fileName, ['Content-Type' => $mimeType])
                ->post($url, [
                    'model'       => $model,
                    'language'    => 'es',
                    'temperature' => '0',
                ]);

            if ($response->successful()) {
                $text = (string) $response->json('text');
                if (!empty(trim($text))) {
                    return trim($text);
                }
            } else {
                Log::warning('Groq Whisper transcription failed, status: ' . $response->status(), [
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Groq Whisper exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Transcribe usando Deepgram Nova-3 API.
     */
    protected function transcribeWithDeepgram(string $fileContents, string $mimeType): ?string
    {
        $apiKey = (string) config('services.deepgram.key', env('DEEPGRAM_API_KEY', ''));
        if (empty($apiKey)) {
            return null;
        }

        $model    = config('services.deepgram.model', 'nova-3');
        $language = config('services.deepgram.language', 'es');

        $url = "https://api.deepgram.com/v1/listen?model={$model}&language={$language}&smart_format=true&punctuate=true";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Token {$apiKey}",
                'Content-Type'  => $mimeType,
            ])
                ->timeout(60)
                ->withBody($fileContents, $mimeType)
                ->post($url);

            if ($response->successful()) {
                $transcript = $response->json('results.channels.0.alternatives.0.transcript');
                if (!empty(trim((string) $transcript))) {
                    return trim($transcript);
                }
            } else {
                Log::warning('Deepgram transcription failed, status: ' . $response->status(), [
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Deepgram exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Transcribe con OpenAI Whisper como respaldo si hay una clave de OpenAI configurada.
     */
    protected function transcribeWithWhisperFallback(string $fileContents, string $fileName): ?string
    {
        $whisperUrl = config('services.assistant.whisper_url', 'https://api.openai.com/v1/audio/transcriptions');
        $whisperKey = (string) config('services.assistant.whisper_key', env('OPENAI_API_KEY', ''));

        if (empty($whisperKey)) {
            return null;
        }

        try {
            $response = Http::withToken($whisperKey)
                ->timeout(60)
                ->attach('file', $fileContents, $fileName)
                ->post($whisperUrl, [
                    'model'    => config('services.assistant.whisper_model', 'whisper-1'),
                    'language' => 'es',
                ]);

            if ($response->successful()) {
                $text = (string) $response->json('text');
                if (!empty(trim($text))) {
                    return trim($text);
                }
            } else {
                Log::warning('Whisper fallback failed, status: ' . $response->status(), [
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Whisper fallback exception: ' . $e->getMessage());
        }

        return null;
    }
}
