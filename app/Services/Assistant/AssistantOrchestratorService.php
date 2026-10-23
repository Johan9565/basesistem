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

    public function __construct()
    {
        $this->apiUrl = (string) config('services.assistant.url', 'https://api.deepseek.com');
        $this->apiKey = (string) config('services.assistant.key', '');
        $this->model  = (string) config('services.assistant.model', 'deepseek-chat');
    }

    /**
     * Interacts with LLM using tools and processes user prompt.
     *
     * @param User $user
     * @param string $prompt
     * @param array $conversationHistory
     * @return array Standardized response: ['success' => bool, 'data' => mixed, 'message' => string, 'action' => string]
     */
    public function interact(User $user, string $prompt, array $conversationHistory = []): array
    {
        try {
            $nowInTz = Carbon::now($this->timezone);
            $nowIso  = $nowInTz->toIso8601String();

            $systemPrompt = $this->buildSystemPrompt($nowIso);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
            ];

            foreach ($conversationHistory as $msg) {
                if (isset($msg['role'], $msg['content'])) {
                    $messages[] = [
                        'role'    => $msg['role'],
                        'content' => (string) $msg['content'],
                    ];
                }
            }

            $messages[] = ['role' => 'user', 'content' => $prompt];

            $endpoint = rtrim($this->apiUrl, '/');
            if (!str_ends_with($endpoint, '/chat/completions')) {
                $endpoint .= '/chat/completions';
            }

            $payload = [
                'model'       => $this->model,
                'messages'    => $messages,
                'tools'       => $this->getToolsSchema(),
                'tool_choice' => 'auto',
                'temperature' => 0.2,
            ];

            $response = Http::withToken($this->apiKey)
                ->timeout(45)
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

            return [
                'success' => true,
                'data'    => null,
                'message' => $content,
                'action'  => 'conversation',
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
     * Build strict temporal system prompt with memory & retrieval instructions.
     */
    protected function buildSystemPrompt(string $nowIso): string
    {
        return <<<SYS
Eres un Asistente Personal Ejecutivo inteligente, proactivo y eficiente integrado en un Workspace.
Zona horaria de referencia: America/Cancun.
FECHA Y HORA ACTUAL DEL SISTEMA (ANCLA TEMPORAL ESTRICTA): {$nowIso}.

REGLAS TEMPORALES Y OPERATIVAS ESTRICTAS:
1. Todas las fechas y horas relativas (ej. "hoy", "mañana", "el próximo viernes", "en 2 horas") deben calcularse tomando como ancla estricta la FECHA Y HORA ACTUAL indicada arriba.
2. CITAS Y EVENTOS SIN HORA ESPECÍFICA: Si el usuario indica un día pero omite la hora, la hora de inicio por defecto debe ser exactamente las 09:00:00 (ejemplo: YYYY-MM-DDT09:00:00).
3. DURACIÓN POR DEFECTO: Si el usuario no indica hora de fin o duración para una cita, debe ser exactamente de 1 hora después de la hora de inicio.
4. FORMATO DE FECHA/HORA: Todos los parámetros temporales en tool calls deben expresarse en formato ISO 8601 estricto SIN sufijo de offset (ejemplo: YYYY-MM-DDTHH:mm:ss).

MÓDULO: CLASIFICACIÓN Y DIVISIÓN DE TAREAS / RECORDATORIOS:
Tu función al recibir intenciones de memoria, compra o pendientes es clasificarlas y atomizarlas antes de cualquier acción o llamada a herramienta (tools).

1. MATRIZ DE DECISIÓN (TIPO DE REGISTRO):
- Tipo "TODO" (Lista de pendientes/notas):
  * Criterio: Solicitudes de compra, tareas pendientes, encargos o recordatorios SIN hora/fecha específica ("recuérdame comprar...", "tengo que hacer...", "anota que debo...").
  * Destino: Invoca `create_workspace_note` para guardarlo en pendientes con tag 'todo'.
  
- Tipo "RECORDATORIO_TEMPORAL" (Alarma / Calendario):
  * Criterio: Solicitudes que incluyan una referencia temporal explícita o relativa medible ("a las 5:00 pm", "mañana a las 9 am", "el viernes", "en 2 horas").
  * Destino: Invoca `create_reminder` (o `create_calendar_event` si es reunión/cita formal) con su fecha/hora programada.

2. REGLA DE ATOMICIDAD (DESCOMPOSICIÓN ESTRICTA):
- Jamás crees un solo registro para múltiples objetos o acciones.
- Si el mensaje contiene conjunciones ("y", "e"), comas (",") o listas implícitas de cosas por adquirir o realizar:
  * Divide cada elemento en una entidad única, auto-contenida y concisa.
  * Realiza una llamada a función (tool call) independiente para CADA elemento atomizado por separado.
  * Ejemplo recibido: "recuérdame comprar un paraguas y un impermeable"
  * Descomposición obligatoria:
    - Tool call 1: `create_workspace_note` (title: "Comprar paraguas")
    - Tool call 2: `create_workspace_note` (title: "Comprar impermeable")

3. POLÍTICA DE EJECUCIÓN Y RESPUESTA:
- Ejecuta las acciones correspondientes (o llamadas a función) por cada ítem por separado.
- Responde siempre confirmando de manera ultra concisa (máximo 1 o 2 líneas), sin explicaciones metodológicas:
  * Ejemplo respuesta: "Anotados en pendientes: 1. Comprar paraguas | 2. Comprar impermeable."

MEMORIA, CONSULTA (RETRIEVAL) Y EJECUCIÓN DE HERRAMIENTAS:
- BÚSQUEDA DE CITAS: Cuando el usuario consulte qué eventos, citas o reuniones tiene, o busque una cita específica -> invoca `search_calendar_events`.
- BÚSQUEDA DE NOTAS: Cuando el usuario pregunte sobre ideas, notas previas, acuerdos o apuntes -> invoca `search_notes`.
- BÚSQUEDA DE RECORDATORIOS: Cuando el usuario consulte sus pendientes, recordatorios o tareas -> invoca `get_pending_reminders`.
- CREACIÓN DE CITAS: Cuando el usuario exprese la intención de agendar o programar -> invoca `create_calendar_event`.
- CREACIÓN DE RECORDATORIOS: Cuando el usuario pida recordar una tarea o alarma con hora/fecha -> invoca `create_reminder`.
- CREACIÓN DE NOTAS / PENDIENTES: Cuando el usuario pida guardar una nota o pendientes/compras sin hora -> invoca `create_workspace_note`.
- CONSULTA DE AGENDA: Cuando el usuario pida la agenda global de un rango -> invoca `get_agenda`.

Cuando recibas el resultado de una herramienta de búsqueda (retrieval), compila una respuesta clara, concisa, profesional y estructurada citando la información exacta encontrada. Si no hay registros, indícaselo amablemente.
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
                                'description' => 'Título de la nota o tarea pendiente.',
                            ],
                            'content' => [
                                'type'        => 'string',
                                'description' => 'Cuerpo o contenido detallado de la nota. Si se omite, se utiliza el título.',
                            ],
                            'tags' => [
                                'type'        => 'array',
                                'items'       => ['type' => 'string'],
                                'description' => 'Lista de etiquetas o tags temáticos (ej: ["todo", "pendiente", "compras"]).',
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
                    'description' => 'Consulta citas y eventos del calendario dentro de un rango de fechas.',
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
                    'description' => 'Recupera los recordatorios y tareas del usuario ordenados cronológicamente por su fecha programada.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'include_completed' => [
                                'type'        => 'boolean',
                                'description' => 'Si es true, incluye tanto pendientes como completados. Por defecto false (solo pendientes).',
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

        // If any tool call is retrieval, execute second turn retrieval
        foreach ($toolCalls as $call) {
            $fnName = $call['function']['name'] ?? '';
            if (in_array($fnName, $retrievalTools, true)) {
                $rawArgs = $call['function']['arguments'] ?? '{}';
                $args    = json_decode($rawArgs, true) ?: [];

                switch ($fnName) {
                    case 'search_calendar_events':
                        $results = $this->executeSearchCalendarEvents($user, $args);
                        return $this->executeSecondTurnRetrieval($user, $messages, $endpoint, $choice, $call, $results, $fnName);

                    case 'search_notes':
                        $results = $this->executeSearchNotes($user, $args);
                        return $this->executeSecondTurnRetrieval($user, $messages, $endpoint, $choice, $call, $results, $fnName);

                    case 'get_pending_reminders':
                        $results = $this->executeGetPendingReminders($user, $args);
                        return $this->executeSecondTurnRetrieval($user, $messages, $endpoint, $choice, $call, $results, $fnName);

                    case 'get_agenda':
                        $results = $this->executeGetAgenda($user, $args);
                        return $this->executeSecondTurnRetrieval($user, $messages, $endpoint, $choice, $call, $results['data'] ?? [], $fnName);
                }
            }
        }

        // Single creation tool call
        if (count($toolCalls) === 1) {
            $firstCall = $toolCalls[0];
            $fnName    = $firstCall['function']['name'] ?? '';
            $rawArgs   = $firstCall['function']['arguments'] ?? '{}';
            $args      = json_decode($rawArgs, true) ?: [];

            switch ($fnName) {
                case 'create_calendar_event':
                    return $this->executeCreateCalendarEvent($user, $args);

                case 'create_reminder':
                    return $this->executeCreateReminder($user, $args);

                case 'create_workspace_note':
                    return $this->executeCreateWorkspaceNote($user, $args);

                default:
                    return [
                        'success' => false,
                        'data'    => null,
                        'message' => "Acción desconocida solicitada: {$fnName}.",
                        'action'  => 'unknown_action',
                    ];
            }
        }

        // Multiple creation tool calls (e.g. atomized To-Dos or Reminders)
        $executed = [];
        $actionsByType = [
            'notes'     => [],
            'reminders' => [],
            'events'    => [],
        ];

        foreach ($toolCalls as $call) {
            $fnName  = $call['function']['name'] ?? '';
            $rawArgs = $call['function']['arguments'] ?? '{}';
            $args    = json_decode($rawArgs, true) ?: [];

            switch ($fnName) {
                case 'create_workspace_note':
                    $res = $this->executeCreateWorkspaceNote($user, $args);
                    $executed[] = $res;
                    if ($res['success'] ?? false) {
                        $actionsByType['notes'][] = $res['data']->title ?? ($args['title'] ?? 'Pendiente');
                    }
                    break;

                case 'create_reminder':
                    $res = $this->executeCreateReminder($user, $args);
                    $executed[] = $res;
                    if ($res['success'] ?? false) {
                        $title = $res['data']->title ?? ($args['title'] ?? 'Recordatorio');
                        $actionsByType['reminders'][] = $title;
                    }
                    break;

                case 'create_calendar_event':
                    $res = $this->executeCreateCalendarEvent($user, $args);
                    $executed[] = $res;
                    if ($res['success'] ?? false) {
                        $actionsByType['events'][] = $res['data']->title ?? ($args['title'] ?? 'Evento');
                    }
                    break;
            }
        }

        $llmContent = trim((string) ($choice['message']['content'] ?? ''));
        if (!empty($llmContent)) {
            $message = $llmContent;
        } else {
            $summaryParts = [];
            if (!empty($actionsByType['notes'])) {
                $items = array_map(fn($idx, $item) => ($idx + 1) . '. ' . $item, array_keys($actionsByType['notes']), $actionsByType['notes']);
                $summaryParts[] = 'Anotados en pendientes: ' . implode(' | ', $items);
            }
            if (!empty($actionsByType['reminders'])) {
                $items = array_map(fn($idx, $item) => ($idx + 1) . '. ' . $item, array_keys($actionsByType['reminders']), $actionsByType['reminders']);
                $summaryParts[] = 'Recordatorios programados: ' . implode(' | ', $items);
            }
            if (!empty($actionsByType['events'])) {
                $items = array_map(fn($idx, $item) => ($idx + 1) . '. ' . $item, array_keys($actionsByType['events']), $actionsByType['events']);
                $summaryParts[] = 'Citas agendadas: ' . implode(' | ', $items);
            }

            $message = !empty($summaryParts)
                ? implode('. ', $summaryParts) . '.'
                : 'Se procesaron ' . count($executed) . ' acciones correctamente.';
        }

        return [
            'success' => true,
            'data'    => array_map(fn($e) => $e['data'] ?? null, $executed),
            'message' => $message,
            'action'  => 'multiple_actions',
        ];
    }

    /**
     * Injects retrieved MongoDB data back into a second conversational turn with the LLM.
     */
    protected function executeSecondTurnRetrieval(User $user, array $messages, string $endpoint, array $choice, array $firstCall, array $results, string $fnName): array
    {
        try {
            $assistantMessage = $choice['message'] ?? [];
            $messages[] = $assistantMessage;

            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $firstCall['id'] ?? 'call_1',
                'content'      => json_encode([
                    'query_tool'    => $fnName,
                    'results_count' => count($results),
                    'records'       => $results,
                ], JSON_UNESCAPED_UNICODE),
            ];

            $secondResponse = Http::withToken($this->apiKey)
                ->timeout(45)
                ->post($endpoint, [
                    'model'       => $this->model,
                    'messages'    => $messages,
                    'temperature' => 0.3,
                ]);

            if ($secondResponse->successful()) {
                $naturalContent = (string) $secondResponse->json('choices.0.message.content');
                if (!empty(trim($naturalContent))) {
                    return [
                        'success' => true,
                        'data'    => $results,
                        'message' => trim($naturalContent),
                        'action'  => $fnName,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Second turn retrieval exception: " . $e->getMessage());
        }

        // Fallback message if second turn fails
        $count = count($results);
        return [
            'success' => true,
            'data'    => $results,
            'message' => "Consulta realizada: se encontraron {$count} registros coincidentes.",
            'action'  => $fnName,
        ];
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
     */
    protected function executeSearchNotes(User $user, array $args): array
    {
        $userId = (string) $user->id;
        $builder = WorkspaceNote::where('user_id', $userId);

        if (!empty($args['tag'])) {
            $tag = trim((string) $args['tag']);
            $builder->where('tags', 'all', [$tag]);
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
            return [
                'id'         => (string) ($n->id ?? $n->_id),
                'title'      => $n->title,
                'content'    => $n->content,
                'tags'       => $n->tags ?? [],
                'is_pinned'  => (bool) $n->is_pinned,
                'created_at' => $n->created_at ? $n->created_at->format('Y-m-d H:i') : null,
            ];
        })->all();
    }

    /**
     * Execute get_pending_reminders retrieval query.
     */
    protected function executeGetPendingReminders(User $user, array $args): array
    {
        $userId = (string) $user->id;
        $builder = Reminder::where('user_id', $userId);

        if (empty($args['include_completed'])) {
            $builder->where('status', 'pending');
        }

        $reminders = $builder->orderBy('scheduled_at', 'asc')->limit(40)->get();

        return $reminders->map(function ($r) {
            return [
                'id'           => (string) ($r->id ?? $r->_id),
                'title'        => $r->title,
                'scheduled_at' => $r->scheduled_at ? $r->scheduled_at->format('Y-m-d H:i') : null,
                'status'       => $r->status,
                'priority'     => $r->priority,
                'channel'      => $r->channel,
            ];
        })->all();
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

        return [
            'success' => true,
            'data'    => $event,
            'message' => "Evento '{$title}' agendado para el {$formattedDate}.",
            'action'  => 'create_calendar_event',
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

        return [
            'success' => true,
            'data'    => $reminder,
            'message' => "Recordatorio '{$title}' programado para el {$formattedDate} (prioridad {$priority}).",
            'action'  => 'create_reminder',
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

        return [
            'success' => true,
            'data'    => $note,
            'message' => "Anotado en pendientes: {$title}.",
            'action'  => 'create_workspace_note',
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
        $realPath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $fileName = $file instanceof UploadedFile ? ($file->getClientOriginalName() ?: 'audio.m4a') : basename($file);
        $mime     = $file instanceof UploadedFile 
            ? ($file->getMimeType() ?: 'audio/m4a') 
            : (function_exists('mime_content_type') && file_exists($realPath) ? (mime_content_type($realPath) ?: 'audio/m4a') : 'audio/m4a');

        if (!file_exists($realPath)) {
            throw new \InvalidArgumentException("El archivo de audio no existe en: {$realPath}");
        }

        $fileContents = file_get_contents($realPath);

        // 1. Groq Whisper (alta velocidad y precisión con whisper-large-v3-turbo)
        $groqKey = (string) config('services.groq.api_key', env('GROQ_API_KEY', ''));
        if (!empty($groqKey)) {
            $url   = config('services.groq.whisper_url', 'https://api.groq.com/openai/v1/audio/transcriptions');
            $model = config('services.groq.whisper_model', 'whisper-large-v3-turbo');

            if (!preg_match('/\.(m4a|mp3|webm|mp4|mpga|wav|mpeg|ogg|flac)$/i', $fileName)) {
                $ext = str_contains($mime, 'webm') ? 'webm' : (str_contains($mime, 'ogg') ? 'ogg' : (str_contains($mime, 'wav') ? 'wav' : 'm4a'));
                $fileName = rtrim($fileName, '.') . '.' . $ext;
            }

            try {
                $response = Http::withToken($groqKey)
                    ->timeout(60)
                    ->attach('file', $fileContents, $fileName)
                    ->post($url, [
                        'model'           => $model,
                        'language'        => 'es',
                        'response_format' => 'json',
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
        }

        // 2. Deepgram
        $deepgramKey = (string) config('services.deepgram.key');

        if (!empty($deepgramKey)) {
            $model    = config('services.deepgram.model', 'nova-3');
            $language = config('services.deepgram.language', 'es');

            $url = "https://api.deepgram.com/v1/listen?model={$model}&language={$language}&smart_format=true";

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Token ' . $deepgramKey,
                    'Content-Type'  => $mime,
                ])
                ->timeout(60)
                ->withBody($fileContents, $mime)
                ->post($url);

                if ($response->successful()) {
                    $transcript = (string) $response->json('results.channels.0.alternatives.0.transcript');
                    if (!empty(trim($transcript))) {
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
        }

        // 3. Fallback: OpenAI-compatible Whisper endpoint if configured
        $whisperUrl = config('services.assistant.whisper_url', 'https://api.openai.com/v1/audio/transcriptions');
        $whisperKey = config('services.assistant.whisper_key', config('services.assistant.key'));

        if (!empty($whisperKey)) {
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
                }
            } catch (\Throwable $e) {
                Log::warning('Whisper fallback exception: ' . $e->getMessage());
            }
        }

        throw new \RuntimeException('No fue posible transcribir el archivo de audio. Verifique la clave de Groq, Deepgram o Whisper.');
    }
}
