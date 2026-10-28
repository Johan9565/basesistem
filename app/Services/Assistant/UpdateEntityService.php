<?php

namespace App\Services\Assistant;

use App\Models\CalendarEvent;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WorkspaceNote;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateEntityService
{
    protected string $timezone = 'America/Cancun';

    public function __construct()
    {
        $this->timezone = config('app.timezone', 'America/Cancun');
    }

    /**
     * Centralized update method for all entities (event, reminder, note, task).
     *
     * Supports both calling conventions:
     * - update($user, $entityType, $entityId, $fields)
     * - update($entityType, $entityId, $fields, $user = null)
     *
     * @param User|string $userOrType
     * @param string $typeOrId
     * @param string|array $idOrFields
     * @param array|User|string|null $fieldsOrUser
     * @return array Standardized result payload
     */
    public function update(
        User|string $userOrType,
        string $typeOrId,
        string|array $idOrFields,
        array|User|string|null $fieldsOrUser = null
    ): array {
        // Resolve parameter signatures
        if ($userOrType instanceof User || (is_string($userOrType) && is_string($typeOrId) && is_string($idOrFields) && is_array($fieldsOrUser))) {
            $user       = $userOrType;
            $entityType = (string) $typeOrId;
            $entityId   = (string) $idOrFields;
            $fields     = (array) $fieldsOrUser;
        } else {
            $entityType = (string) $userOrType;
            $entityId   = (string) $typeOrId;
            $fields     = (array) $idOrFields;
            $user       = $fieldsOrUser instanceof User ? $fieldsOrUser : (is_string($fieldsOrUser) ? $fieldsOrUser : auth()->user());
        }

        $userId = $user instanceof User ? (string) ($user->_id ?? $user->id) : (is_string($user) ? $user : null);
        $normalizedType = $this->normalizeEntityType($entityType);

        if (!$normalizedType) {
            return [
                'success'     => false,
                'data'        => null,
                'message'     => "Tipo de entidad '{$entityType}' no válido. Use 'event', 'reminder', 'note' o 'task'.",
                'status_code' => 422,
            ];
        }

        // Locate entity with user scoping
        $entity = $this->findEntityModel($normalizedType, $entityId, $userId);

        if (!$entity) {
            return [
                'success'     => false,
                'data'        => null,
                'message'     => "No se encontró el registro con ID '{$entityId}'.",
                'status_code' => 404,
            ];
        }

        // Unpack nested 'fields' or 'updates' if supplied as a container
        if (isset($fields['fields']) && is_array($fields['fields'])) {
            $fields = array_merge($fields['fields'], array_diff_key($fields, ['fields' => true]));
        } elseif (isset($fields['updates']) && is_array($fields['updates'])) {
            $fields = array_merge($fields['updates'], array_diff_key($fields, ['updates' => true]));
        }

        return match ($normalizedType) {
            'event'    => $this->updateCalendarEvent($entity, $fields, $user),
            'reminder' => $this->updateReminder($entity, $fields),
            'note'     => $this->updateWorkspaceNote($entity, $fields, 'note'),
            'task'     => $this->updateTaskEntity($entity, $fields),
        };
    }

    /**
     * Resolve entity by ID or title/text search for LLM Tool handler.
     * Evaluates ambiguity and returns candidates if multiple matches are found.
     */
    public function resolveEntityByIdentifier(User $user, string $identifier, ?string $entityType = null): array
    {
        $userId = (string) ($user->_id ?? $user->id);
        $identifier = trim($identifier);

        if ($identifier === '') {
            return [
                'status'  => 'not_found',
                'message' => 'El identificador de búsqueda no puede estar vacío.',
            ];
        }

        $normalizedType = $entityType ? $this->normalizeEntityType($entityType) : null;

        // 1. Try exact ID match across models
        $exactMatch = $this->findEntityByIdAcrossModels($identifier, $userId, $normalizedType);
        if ($exactMatch) {
            return [
                'status'    => 'exact',
                'type'      => $exactMatch['type'],
                'match'     => $exactMatch['model'],
                'candidate' => $exactMatch,
            ];
        }

        // 2. Query candidates by text search across scoped collections
        $candidates = $this->searchCandidateEntities($userId, $identifier, $normalizedType);

        if (empty($candidates)) {
            return [
                'status'  => 'not_found',
                'message' => "No encontré ningún registro que coincida con '{$identifier}'.",
            ];
        }

        if (count($candidates) === 1) {
            return [
                'status'    => 'exact',
                'type'      => $candidates[0]['type'],
                'match'     => $candidates[0]['model'],
                'candidate' => $candidates[0],
            ];
        }

        // 3. Ambiguity detected: format user-friendly clarification message
        $lines = [];
        foreach ($candidates as $idx => $c) {
            $num = $idx + 1;
            $typeLabel = ucfirst($c['type']);
            $detail = !empty($c['details']) ? " ({$c['details']})" : '';
            $lines[] = "{$num}. [{$typeLabel}] {$c['title']}{$detail}";
        }

        $clarification = "Encontré " . count($candidates) . " coincidencias para \"{$identifier}\":\n" .
            implode("\n", $lines) . "\n\n¿Cuál de ellas deseas modificar?";

        return [
            'status'     => 'ambiguous',
            'candidates' => $candidates,
            'message'    => $clarification,
        ];
    }

    /**
     * Update CalendarEvent with chronological checking and Google Calendar synchronization.
     */
    protected function updateCalendarEvent(CalendarEvent $event, array $fields, User|string|null $user): array
    {
        $tz = $this->timezone;
        $now = Carbon::now($tz);

        if (array_key_exists('title', $fields) && filled($fields['title'])) {
            $event->title = trim((string) $fields['title']);
        }
        if (array_key_exists('description', $fields)) {
            $event->description = $fields['description'];
        }
        if (array_key_exists('location', $fields)) {
            $event->location = $fields['location'];
        }

        $startTimeChanged = false;
        $newStart = null;
        $newEnd   = null;

        // Extract dates/times from various parameter styles
        $rawStart = $fields['start_time'] ?? $fields['datetime'] ?? null;
        $rawEnd   = $fields['end_time'] ?? null;
        $rawDate  = $fields['date'] ?? $fields['due_date'] ?? $fields['start_date'] ?? null;
        $rawTime  = $fields['time'] ?? null;

        if ($rawStart) {
            $newStart = Carbon::parse($rawStart, $tz);
            $startTimeChanged = true;
        } elseif ($rawDate || $rawTime) {
            $baseStart = $event->start_time ? Carbon::parse($event->start_time, $tz) : $now->copy();
            $datePart = $rawDate ?: $baseStart->toDateString();
            $timePart = $rawTime ?: ($event->start_time ? Carbon::parse($event->start_time, $tz)->format('H:i:s') : '09:00:00');
            $newStart = Carbon::parse("{$datePart} {$timePart}", $tz);
            $startTimeChanged = true;
        }

        if ($rawEnd) {
            $newEnd = Carbon::parse($rawEnd, $tz);
        }

        // Chronological consistency check
        if ($newStart && $newEnd) {
            if ($newEnd->lessThanOrEqualTo($newStart)) {
                return [
                    'success'     => false,
                    'data'        => null,
                    'message'     => 'La fecha y hora de finalización debe ser posterior a la fecha de inicio.',
                    'status_code' => 422,
                ];
            }
            $event->start_time = $newStart;
            $event->end_time   = $newEnd;
        } elseif ($newStart && !$newEnd) {
            $event->start_time = $newStart;
            // Maintain original duration or default 1 hour
            if ($event->end_time && $event->getOriginal('start_time')) {
                $origStart = Carbon::parse($event->getOriginal('start_time'), $tz);
                $origEnd   = Carbon::parse($event->getOriginal('end_time'), $tz);
                $durationMinutes = max(15, $origEnd->diffInMinutes($origStart));
                $event->end_time = (clone $newStart)->addMinutes($durationMinutes);
            } else {
                $event->end_time = (clone $newStart)->addHour();
            }
        } elseif (!$newStart && $newEnd) {
            $currentStart = Carbon::parse($event->start_time, $tz);
            if ($newEnd->lessThanOrEqualTo($currentStart)) {
                return [
                    'success'     => false,
                    'data'        => null,
                    'message'     => 'La fecha y hora de finalización debe ser posterior a la fecha de inicio.',
                    'status_code' => 422,
                ];
            }
            $event->end_time = $newEnd;
        }

        // Recalculate status if rescheduled to the future
        if (array_key_exists('status', $fields) && filled($fields['status'])) {
            $event->status = trim((string) $fields['status']);
        } elseif ($startTimeChanged && $event->start_time && Carbon::parse($event->start_time, $tz)->greaterThan($now)) {
            if (in_array($event->status, ['completed', 'cancelled'])) {
                $event->status = 'scheduled';
            }
        }

        $event->save();

        // Push update to Google Calendar if enabled
        if ($user instanceof User) {
            try {
                app(GoogleCalendarService::class)->pushEventToGoogle($user, $event);
            } catch (\Throwable $e) {
                Log::warning('Google Calendar sync update error: ' . $e->getMessage());
            }
        }

        $clean = $this->formatEntity($event, 'event');

        return [
            'success'     => true,
            'entity_type' => 'event',
            'id'          => $clean['id'],
            'data'        => $clean,
            'model'       => $event,
            'message'     => "Evento '{$event->title}' actualizado correctamente.",
            'status_code' => 200,
        ];
    }

    /**
     * Update Reminder with status recalculation on future reschedule.
     */
    protected function updateReminder(Reminder $reminder, array $fields): array
    {
        $tz = $this->timezone;
        $now = Carbon::now($tz);

        if (array_key_exists('title', $fields) && filled($fields['title'])) {
            $reminder->title = trim((string) $fields['title']);
        }
        if (array_key_exists('priority', $fields) && in_array($fields['priority'], ['low', 'medium', 'high'])) {
            $reminder->priority = $fields['priority'];
        }
        if (array_key_exists('channel', $fields) && filled($fields['channel'])) {
            $reminder->channel = trim((string) $fields['channel']);
        }

        $dateChanged = false;
        $rawSchedule = $fields['scheduled_at'] ?? $fields['remind_at'] ?? $fields['datetime'] ?? null;
        $rawDate     = $fields['date'] ?? $fields['due_date'] ?? null;
        $rawTime     = $fields['time'] ?? null;

        if ($rawSchedule) {
            $reminder->scheduled_at = Carbon::parse($rawSchedule, $tz);
            $dateChanged = true;
        } elseif ($rawDate || $rawTime) {
            $base = $reminder->scheduled_at ? Carbon::parse($reminder->scheduled_at, $tz) : $now->copy();
            $d = $rawDate ?: $base->toDateString();
            $t = $rawTime ?: ($reminder->scheduled_at ? Carbon::parse($reminder->scheduled_at, $tz)->format('H:i:s') : '09:00:00');
            $reminder->scheduled_at = Carbon::parse("{$d} {$t}", $tz);
            $dateChanged = true;
        }

        // Recalculate status: if moved to future, reset completed/expired status to pending
        if (array_key_exists('status', $fields) && filled($fields['status'])) {
            $reminder->status = trim((string) $fields['status']);
        } elseif ($dateChanged && $reminder->scheduled_at && Carbon::parse($reminder->scheduled_at, $tz)->greaterThan($now)) {
            if ($reminder->status === 'completed' || $reminder->status === 'expired') {
                $reminder->status = 'pending';
            }
        }

        // Keep remind_at in sync for backward compatibility
        if ($dateChanged && isset($reminder->remind_at)) {
            $reminder->remind_at = $reminder->scheduled_at;
        }

        $reminder->save();

        $clean = $this->formatEntity($reminder, 'reminder');

        return [
            'success'     => true,
            'entity_type' => 'reminder',
            'id'          => $clean['id'],
            'data'        => $clean,
            'model'       => $reminder,
            'message'     => "Recordatorio '{$reminder->title}' actualizado correctamente.",
            'status_code' => 200,
        ];
    }

    /**
     * Update WorkspaceNote.
     */
    protected function updateWorkspaceNote(WorkspaceNote $note, array $fields, string $reportedType = 'note'): array
    {
        if (array_key_exists('title', $fields) && filled($fields['title'])) {
            $note->title = trim((string) $fields['title']);
        }
        if (array_key_exists('content', $fields)) {
            $note->content = trim((string) $fields['content']);
        } elseif (array_key_exists('description', $fields)) {
            $note->content = trim((string) $fields['description']);
        }

        if (array_key_exists('is_pinned', $fields)) {
            $note->is_pinned = (bool) $fields['is_pinned'];
        }

        if (array_key_exists('tags', $fields)) {
            $tags = is_array($fields['tags']) ? $fields['tags'] : (json_decode((string) $fields['tags'], true) ?: []);
            $note->tags = array_values(array_unique(array_filter($tags)));
        }

        if (array_key_exists('status', $fields)) {
            $note->status = trim((string) $fields['status']);
        }

        $note->save();

        $clean = $this->formatEntity($note, $reportedType);

        return [
            'success'     => true,
            'entity_type' => $reportedType,
            'id'          => $clean['id'],
            'data'        => $clean,
            'model'       => $note,
            'message'     => ucfirst($reportedType) . " '{$note->title}' actualizado correctamente.",
            'status_code' => 200,
        ];
    }

    /**
     * Update task entity (routes to WorkspaceNote or Reminder).
     */
    protected function updateTaskEntity(WorkspaceNote|Reminder $entity, array $fields): array
    {
        if ($entity instanceof Reminder) {
            return $this->updateReminder($entity, $fields);
        }

        return $this->updateWorkspaceNote($entity, $fields, 'task');
    }

    /**
     * Format entity into clean, consistent JSON-ready array.
     */
    public function formatEntity(mixed $model, string $type): array
    {
        $id = (string) ($model->_id ?? $model->id);
        $tz = $this->timezone;

        if ($model instanceof CalendarEvent || $type === 'event') {
            $start = $model->start_time ? Carbon::parse($model->start_time, $tz) : null;
            $end   = $model->end_time ? Carbon::parse($model->end_time, $tz) : null;

            return [
                'id'             => $id,
                'entity_type'    => 'event',
                'title'          => (string) $model->title,
                'start_time'     => $start ? $start->format('Y-m-d H:i') : null,
                'end_time'       => $end ? $end->format('Y-m-d H:i') : null,
                'start_time_iso' => $start ? $start->toIso8601String() : null,
                'end_time_iso'   => $end ? $end->toIso8601String() : null,
                'description'    => $model->description,
                'location'       => $model->location,
                'status'         => $model->status ?? 'scheduled',
                'synced_at'      => $model->synced_at ? Carbon::parse($model->synced_at, $tz)->toIso8601String() : null,
                'created_at'     => $model->created_at ? Carbon::parse($model->created_at, $tz)->toIso8601String() : null,
                'updated_at'     => $model->updated_at ? Carbon::parse($model->updated_at, $tz)->toIso8601String() : null,
            ];
        }

        if ($model instanceof Reminder || $type === 'reminder') {
            $scheduled = $model->scheduled_at ? Carbon::parse($model->scheduled_at, $tz) : null;

            return [
                'id'               => $id,
                'entity_type'      => 'reminder',
                'title'            => (string) $model->title,
                'scheduled_at'     => $scheduled ? $scheduled->format('Y-m-d H:i') : null,
                'scheduled_at_iso' => $scheduled ? $scheduled->toIso8601String() : null,
                'status'           => $model->status ?? 'pending',
                'priority'         => $model->priority ?? 'medium',
                'channel'          => $model->channel ?? 'whatsapp',
                'created_at'       => $model->created_at ? Carbon::parse($model->created_at, $tz)->toIso8601String() : null,
                'updated_at'       => $model->updated_at ? Carbon::parse($model->updated_at, $tz)->toIso8601String() : null,
            ];
        }

        // WorkspaceNote (note or task)
        $rawTags = $model->tags;
        $tags = is_array($rawTags) ? $rawTags : (json_decode((string) $rawTags, true) ?: []);

        return [
            'id'          => $id,
            'entity_type' => $type === 'task' ? 'task' : 'note',
            'title'       => (string) $model->title,
            'content'     => (string) ($model->content ?? ''),
            'tags'        => array_values($tags),
            'is_pinned'   => (bool) ($model->is_pinned ?? false),
            'status'      => $model->status ?? 'pending',
            'created_at'  => $model->created_at ? Carbon::parse($model->created_at, $tz)->toIso8601String() : null,
            'updated_at'  => $model->updated_at ? Carbon::parse($model->updated_at, $tz)->toIso8601String() : null,
        ];
    }

    /**
     * Map aliases to primary canonical entity types.
     */
    public function normalizeEntityType(string $type): ?string
    {
        $clean = strtolower(trim($type));

        return match ($clean) {
            'event', 'calendar_event', 'cita', 'evento' => 'event',
            'reminder', 'recordatorio'                  => 'reminder',
            'note', 'nota', 'workspace_note'            => 'note',
            'task', 'tarea', 'pendiente', 'todo'        => 'task',
            default                                     => null,
        };
    }

    /**
     * Find entity by ID in the targeted model class.
     */
    protected function findEntityModel(string $normalizedType, string $id, ?string $userId = null): mixed
    {
        if ($normalizedType === 'event') {
            return $this->findInModel(CalendarEvent::class, $id, $userId);
        }

        if ($normalizedType === 'reminder') {
            return $this->findInModel(Reminder::class, $id, $userId);
        }

        if ($normalizedType === 'note') {
            return $this->findInModel(WorkspaceNote::class, $id, $userId);
        }

        if ($normalizedType === 'task') {
            $note = $this->findInModel(WorkspaceNote::class, $id, $userId);
            if ($note) return $note;
            return $this->findInModel(Reminder::class, $id, $userId);
        }

        return null;
    }

    /**
     * Find model by ID with MongoDB ObjectId fallback.
     */
    protected function findInModel(string $modelClass, string $id, ?string $userId = null): mixed
    {
        $query = $modelClass::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->where(function ($q) use ($id) {
            $q->where('_id', $id)->orWhere('id', $id);
            if (preg_match('/^[a-f\d]{24}$/i', $id)) {
                try {
                    $q->orWhere('_id', new \MongoDB\BSON\ObjectId($id));
                } catch (\Throwable $e) {}
            }
        })->first();
    }

    /**
     * Search an ID across all models when type is unknown or fuzzy.
     */
    protected function findEntityByIdAcrossModels(string $id, string $userId, ?string $normalizedType = null): ?array
    {
        $modelsToSearch = match ($normalizedType) {
            'event'    => ['event' => CalendarEvent::class],
            'reminder' => ['reminder' => Reminder::class],
            'note'     => ['note' => WorkspaceNote::class],
            'task'     => ['task' => WorkspaceNote::class, 'reminder' => Reminder::class],
            default    => [
                'event'    => CalendarEvent::class,
                'reminder' => Reminder::class,
                'note'     => WorkspaceNote::class,
            ],
        };

        foreach ($modelsToSearch as $type => $modelClass) {
            $found = $this->findInModel($modelClass, $id, $userId);
            if ($found) {
                return [
                    'id'      => (string) ($found->_id ?? $found->id),
                    'type'    => $type,
                    'title'   => (string) $found->title,
                    'model'   => $found,
                    'details' => $this->describeCandidate($found, $type),
                ];
            }
        }

        return null;
    }

    /**
     * Search candidate entities across user records by text regex.
     */
    protected function searchCandidateEntities(string $userId, string $queryText, ?string $normalizedType = null): array
    {
        $candidates = [];
        $regex = new \MongoDB\BSON\Regex(preg_quote($queryText), 'i');
        $tz = $this->timezone;

        // 1. Calendar Events
        if (!$normalizedType || $normalizedType === 'event') {
            $events = CalendarEvent::where('user_id', $userId)
                ->where(function ($sub) use ($regex) {
                    $sub->where('title', 'regex', $regex)
                        ->orWhere('description', 'regex', $regex);
                })
                ->orderBy('start_time', 'desc')
                ->limit(5)
                ->get();

            foreach ($events as $e) {
                $candidates[] = [
                    'id'      => (string) ($e->_id ?? $e->id),
                    'type'    => 'event',
                    'title'   => (string) $e->title,
                    'details' => $e->start_time ? Carbon::parse($e->start_time, $tz)->format('Y-m-d H:i') : 'Sin fecha',
                    'model'   => $e,
                ];
            }
        }

        // 2. Reminders
        if (!$normalizedType || $normalizedType === 'reminder' || $normalizedType === 'task') {
            $reminders = Reminder::where('user_id', $userId)
                ->where('title', 'regex', $regex)
                ->orderBy('scheduled_at', 'desc')
                ->limit(5)
                ->get();

            foreach ($reminders as $r) {
                $candidates[] = [
                    'id'      => (string) ($r->_id ?? $r->id),
                    'type'    => 'reminder',
                    'title'   => (string) $r->title,
                    'details' => $r->scheduled_at ? Carbon::parse($r->scheduled_at, $tz)->format('Y-m-d H:i') : 'Sin fecha',
                    'model'   => $r,
                ];
            }
        }

        // 3. Workspace Notes / Tasks
        if (!$normalizedType || $normalizedType === 'note' || $normalizedType === 'task') {
            $notes = WorkspaceNote::where('user_id', $userId)
                ->where(function ($sub) use ($regex) {
                    $sub->where('title', 'regex', $regex)
                        ->orWhere('content', 'regex', $regex);
                })
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            foreach ($notes as $n) {
                $type = ($normalizedType === 'task' || in_array('todo', (array) $n->tags)) ? 'task' : 'note';
                $candidates[] = [
                    'id'      => (string) ($n->_id ?? $n->id),
                    'type'    => $type,
                    'title'   => (string) $n->title,
                    'details' => mb_strimwidth((string) $n->content, 0, 40, '...'),
                    'model'   => $n,
                ];
            }
        }

        return $candidates;
    }

    /**
     * Build short human-readable description for candidate ambiguity disambiguation.
     */
    protected function describeCandidate(mixed $model, string $type): string
    {
        $tz = $this->timezone;

        if ($type === 'event' && $model->start_time) {
            return Carbon::parse($model->start_time, $tz)->format('Y-m-d H:i');
        }
        if ($type === 'reminder' && $model->scheduled_at) {
            return Carbon::parse($model->scheduled_at, $tz)->format('Y-m-d H:i');
        }
        if ($model->content) {
            return mb_strimwidth((string) $model->content, 0, 40, '...');
        }

        return '';
    }
}

