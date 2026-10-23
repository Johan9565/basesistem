<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Services\Assistant\AssistantOrchestratorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssistantController extends Controller
{
    protected AssistantOrchestratorService $orchestrator;

    public function __construct(AssistantOrchestratorService $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /**
     * Standardized helper response format:
     * {"success": bool, "data": mixed, "message": string, "action": string}
     */
    protected function jsonResponse(bool $success, mixed $data, string $message, string $action, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'data'    => $data,
            'message' => $message,
            'action'  => $action,
        ], $status);
    }

    /**
     * GET|POST /api/v1/assistant/status
     * Used by mobile apps to verify token and assistant connection status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'status'  => 'unauthenticated',
                'message' => 'No autenticado.',
                'action'  => 'unauthenticated',
            ], 401);
        }

        $userData = [
            'id'    => (string) ($user->_id ?? $user->id),
            'name'  => $user->name,
            'email' => $user->email,
        ];

        return response()->json([
            'success' => true,
            'status'  => 'online',
            'data'    => [
                'status' => 'online',
                'user'   => $userData,
            ],
            'user'    => $userData,
            'message' => 'Conexión exitosa con el asistente.',
            'action'  => 'status',
        ]);
    }

    /**
     * POST /api/v1/assistant/interact
     */
    public function interact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt'               => 'required|string|max:5000',
            'conversation_history' => 'sometimes|array',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        $result = $this->orchestrator->interact(
            $user,
            $validated['prompt'],
            $validated['conversation_history'] ?? []
        );

        $status = ($result['success'] ?? false) ? 200 : 422;

        return $this->jsonResponse(
            $result['success'] ?? false,
            $result['data'] ?? null,
            $result['message'] ?? '',
            $result['action'] ?? 'unknown',
            $status
        );
    }

    /**
     * POST /api/v1/assistant/voice
     */
    public function voice(Request $request): JsonResponse
    {
        $request->validate([
            'audio'                => 'required|file|max:20480', // up to 20MB
            'conversation_history' => 'sometimes',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        $audioFile = $request->file('audio');

        try {
            $transcription = $this->orchestrator->transcribeAudio($audioFile);
        } catch (\Throwable $e) {
            Log::error('Audio transcription error: ' . $e->getMessage());

            return $this->jsonResponse(
                false,
                null,
                'Error al transcribir el audio: ' . $e->getMessage(),
                'transcription_failed',
                422
            );
        }

        $history = $request->input('conversation_history', []);
        if (is_string($history)) {
            $history = json_decode($history, true) ?: [];
        }

        $result = $this->orchestrator->interact($user, $transcription, (array) $history);

        return $this->jsonResponse(
            $result['success'] ?? false,
            [
                'execution'     => $result['data'] ?? null,
                'transcription' => $transcription,
            ],
            $result['message'] ?? '',
            $result['action'] ?? 'voice_interaction',
            ($result['success'] ?? false) ? 200 : 422
        );
    }

    /**
     * GET /api/v1/assistant/feed
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        $feed = $this->orchestrator->getFeed($user);

        return $this->jsonResponse(
            true,
            $feed,
            'Feed obtenido con éxito.',
            'feed'
        );
    }

    /**
     * PATCH /api/v1/reminders/{id}/complete
     */
    public function completeReminder(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        $reminder = Reminder::where('user_id', (string) $user->id)
            ->where('_id', $id)
            ->first();

        if (!$reminder) {
            // Also try with standard id column
            $reminder = Reminder::where('user_id', (string) $user->id)
                ->where('id', $id)
                ->first();
        }

        if (!$reminder) {
            return $this->jsonResponse(
                false,
                null,
                'El recordatorio no existe o no pertenece al usuario.',
                'reminder_not_found',
                404
            );
        }

        $reminder->update([
            'status' => 'completed',
        ]);

        return $this->jsonResponse(
            true,
            $reminder->fresh(),
            'Recordatorio completado exitosamente.',
            'reminder_completed'
        );
    }

    /**
     * GET /api/v1/assistant/agenda
     */
    public function agenda(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        $request->validate([
            'start_date' => 'nullable|string',
            'end_date'   => 'nullable|string',
        ]);

        $startDate = $request->query('start_date', Carbon::now('America/Cancun')->toDateString());
        $endDate   = $request->query('end_date', Carbon::now('America/Cancun')->addDays(7)->toDateString());

        $agenda = $this->orchestrator->getAgenda($user, $startDate, $endDate);

        return $this->jsonResponse(
            true,
            $agenda,
            'Agenda obtenida con éxito.',
            'agenda'
        );
    }

    /**
     * GET /api/v1/assistant/google-calendar/settings
     */
    public function getGoogleCalendarSettings(Request $request, \App\Services\GoogleCalendar\GoogleCalendarService $googleService): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        $setting = $googleService->getSettings($user);

        return $this->jsonResponse(
            true,
            [
                'calendar_id'          => $setting->calendar_id,
                'timezone'             => $setting->timezone,
                'sync_enabled'         => (bool) $setting->sync_enabled,
                'auto_sync_on_create'  => (bool) $setting->auto_sync_on_create,
                'has_service_account'  => !empty($setting->service_account_json),
                'last_synced_at'       => $setting->last_synced_at ? $setting->last_synced_at->toIso8601String() : null,
            ],
            'Configuración de Google Calendar obtenida.',
            'google_calendar_settings'
        );
    }

    /**
     * POST /api/v1/assistant/google-calendar/settings
     */
    public function saveGoogleCalendarSettings(Request $request, \App\Services\GoogleCalendar\GoogleCalendarService $googleService): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        $validated = $request->validate([
            'calendar_id'          => 'nullable|string|max:255',
            'timezone'             => 'nullable|string|max:100',
            'sync_enabled'         => 'nullable|boolean',
            'auto_sync_on_create'  => 'nullable|boolean',
            'service_account_json' => 'nullable|string',
        ]);

        $setting = $googleService->saveSettings($user, $validated);

        return $this->jsonResponse(
            true,
            [
                'calendar_id'          => $setting->calendar_id,
                'timezone'             => $setting->timezone,
                'sync_enabled'         => (bool) $setting->sync_enabled,
                'auto_sync_on_create'  => (bool) $setting->auto_sync_on_create,
                'has_service_account'  => !empty($setting->service_account_json),
                'last_synced_at'       => $setting->last_synced_at ? $setting->last_synced_at->toIso8601String() : null,
            ],
            'Configuración de Google Calendar guardada correctamente.',
            'google_calendar_settings_saved'
        );
    }

    /**
     * POST /api/v1/assistant/google-calendar/sync
     */
    public function syncGoogleCalendar(Request $request, \App\Services\GoogleCalendar\GoogleCalendarService $googleService): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        try {
            $result = $googleService->sync($user);

            return $this->jsonResponse(
                true,
                $result,
                $result['message'] ?? 'Sincronización completada.',
                'google_calendar_sync'
            );
        } catch (\Throwable $e) {
            return $this->jsonResponse(
                false,
                null,
                'Error al sincronizar con Google Calendar: ' . $e->getMessage(),
                'google_calendar_sync_error',
                422
            );
        }
    }

    /**
     * POST /api/v1/assistant/google-calendar/test
     */
    public function testGoogleCalendar(Request $request, \App\Services\GoogleCalendar\GoogleCalendarService $googleService): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        $testResult = $googleService->testConnection($user);

        return $this->jsonResponse(
            $testResult['success'] ?? false,
            $testResult['calendar'] ?? null,
            $testResult['message'] ?? '',
            'google_calendar_test',
            ($testResult['success'] ?? false) ? 200 : 422
        );
    }

    /**
     * POST /api/v1/assistant/events
     */
    public function storeEvent(Request $request, \App\Services\GoogleCalendar\GoogleCalendarService $googleService): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'start_time'  => 'required|string',
            'end_time'    => 'nullable|string',
            'location'    => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $tz = 'America/Cancun';
        $start = Carbon::parse($validated['start_time'], $tz);
        $end = !empty($validated['end_time'])
            ? Carbon::parse($validated['end_time'], $tz)
            : (clone $start)->addHour();

        $event = \App\Models\CalendarEvent::create([
            'user_id'     => (string) $user->id,
            'title'       => $validated['title'],
            'start_time'  => $start,
            'end_time'    => $end,
            'location'    => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'status'      => 'scheduled',
        ]);

        try {
            $googleService->pushEventToGoogle($user, $event);
        } catch (\Throwable $e) {
            Log::warning('Google auto-push error: ' . $e->getMessage());
        }

        return $this->jsonResponse(
            true,
            $event->fresh(),
            "Evento '{$event->title}' creado exitosamente en el calendario.",
            'event_created'
        );
    }

    /**
     * DELETE /api/v1/assistant/events/{id}
     */
    public function destroyEvent(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonResponse(false, null, 'No autenticado.', 'unauthenticated', 401);
        }

        $event = \App\Models\CalendarEvent::where('user_id', (string) $user->id)
            ->where(function ($q) use ($id) {
                $q->where('_id', $id)->orWhere('id', $id);
            })
            ->first();

        if (!$event) {
            return $this->jsonResponse(false, null, 'Evento no encontrado.', 'not_found', 404);
        }

        $event->delete();

        return $this->jsonResponse(true, null, 'Evento eliminado del calendario.', 'event_deleted');
    }
}
