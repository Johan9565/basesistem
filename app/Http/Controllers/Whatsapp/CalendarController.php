<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAppointment;
use App\Models\WhatsappInstance;
use App\Services\Whatsapp\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class CalendarController extends Controller
{
    public function index()
    {
        $instances = WhatsappInstance::query()
            ->orderBy('instance_name')
            ->get()
            ->map(function (WhatsappInstance $row) {
                $name = (string) $row->instance_name;
                $sessionKey = $row->evolutionName();
                $count = WhatsappAppointment::query()
                    ->where('instance_name', $sessionKey)
                    ->count();

                $next = WhatsappAppointment::query()
                    ->where('instance_name', $sessionKey)
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at')
                    ->first();

                return [
                    'instance_name' => $name,
                    'evolution_instance_name' => $sessionKey,
                    'appointment_count' => $count,
                    'next_at' => $next?->starts_at?->toIso8601String(),
                    'next_summary' => $next?->summary,
                ];
            })
            ->values();

        return Inertia::render('Whatsapp/Calendar/Index', [
            'instances' => $instances,
        ]);
    }

    public function show(Request $request, string $instance)
    {
        $row = WhatsappInstance::query()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        $from = $request->query('from');
        $to = $request->query('to');

        // Default to current month so the calendar loads a coherent range.
        if ((! is_string($from) || $from === '') && (! is_string($to) || $to === '')) {
            $from = now()->startOfMonth()->toDateString();
            $to = now()->endOfMonth()->toDateString();
        }

        $query = WhatsappAppointment::query()
            ->where('instance_name', $row->evolutionName())
            ->orderBy('starts_at', 'asc');

        if (is_string($from) && $from !== '') {
            $query->where(
                'starts_at',
                '>=',
                Carbon::parse($from)->startOfDay(),
            );
        }
        if (is_string($to) && $to !== '') {
            $query->where(
                'starts_at',
                '<=',
                Carbon::parse($to)->endOfDay(),
            );
        }

        $appointments = $query
            ->limit(500)
            ->get()
            ->map(fn (WhatsappAppointment $a) => [
                'id' => (string) $a->getKey(),
                'summary' => (string) ($a->summary ?? ''),
                'description' => (string) ($a->description ?? ''),
                'user_phone' => (string) ($a->user_phone ?? ''),
                'starts_at' => $a->starts_at?->toIso8601String(),
                'ends_at' => $a->ends_at?->toIso8601String(),
                'timezone' => (string) ($a->timezone ?? ''),
                'sync_status' => (string) ($a->sync_status ?? 'local'),
                'sync_error' => (string) ($a->sync_error ?? ''),
                'google_html_link' => (string) ($a->google_html_link ?? ''),
                'has_google_event' => filled($a->google_event_id),
                'source' => (string) ($a->source ?? 'bot'),
            ])
            ->values();

        return Inertia::render('Whatsapp/Calendar/Show', [
            'instance' => [
                'instance_name' => (string) $row->instance_name,
                'google_calendar_id' => (string) ($row->google_calendar_id ?? ''),
                'has_credentials' => filled($row->google_credentials_path),
            ],
            'appointments' => $appointments,
            'filters' => [
                'from' => is_string($from) ? $from : '',
                'to' => is_string($to) ? $to : '',
            ],
        ]);
    }

    public function sync(string $instance, GoogleCalendarService $calendar)
    {
        $row = WhatsappInstance::query()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        try {
            $stats = $calendar->syncInstanceFromGoogle($row);
        } catch (Throwable $e) {
            return redirect()
                ->route('whatsapp.calendar.show', ['instance' => $instance])
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'No se pudo sincronizar: '.$e->getMessage(),
                ]);
        }

        return redirect()
            ->route('whatsapp.calendar.show', ['instance' => $instance])
            ->with('flash', [
                'type' => 'success',
                'message' => sprintf(
                    'Sincronizado: %d nuevas, %d actualizadas, %d omitidas.',
                    $stats['imported'],
                    $stats['updated'],
                    $stats['skipped'],
                ),
            ]);
    }

    public function destroy(
        Request $request,
        string $instance,
        string $appointment,
        GoogleCalendarService $calendar,
    ) {
        $row = WhatsappInstance::query()->where('instance_name', $instance)->first();
        if (! $row) {
            abort(404);
        }

        $apt = WhatsappAppointment::query()
            ->where('instance_name', $row->evolutionName())
            ->find($appointment);

        if (! $apt) {
            abort(404);
        }

        $from = $request->input('from', $request->query('from'));
        $to = $request->input('to', $request->query('to'));
        if (! is_string($from) || $from === '') {
            $from = $apt->starts_at?->copy()->startOfMonth()->toDateString()
                ?? now()->startOfMonth()->toDateString();
        }
        if (! is_string($to) || $to === '') {
            $to = $apt->starts_at?->copy()->endOfMonth()->toDateString()
                ?? now()->endOfMonth()->toDateString();
        }

        $googleNote = '';
        $deleteGoogle = filter_var(
            $request->input('delete_google', $request->query('delete_google', true)),
            FILTER_VALIDATE_BOOLEAN,
        );
        $eventId = (string) ($apt->google_event_id ?? '');
        $calendarId = (string) ($row->google_calendar_id ?? '');

        if ($deleteGoogle && $eventId !== '' && $calendarId !== '') {
            try {
                $calendar->deleteEvent(
                    $calendarId,
                    $eventId,
                    $row->google_credentials_path,
                );
                $googleNote = ' También se eliminó de Google Calendar.';
            } catch (Throwable $e) {
                $googleNote = ' Se eliminó en el sistema, pero no en Google: '.$e->getMessage();
            }
        }

        $summary = (string) ($apt->summary ?: 'Cita');
        $apt->delete();

        $type = str_contains($googleNote, 'pero no en Google') ? 'error' : 'success';

        return redirect()
            ->route('whatsapp.calendar.show', [
                'instance' => $instance,
                'from' => $from,
                'to' => $to,
            ])
            ->with('flash', [
                'type' => $type,
                'message' => 'Eliminada: '.$summary.'.'.$googleNote,
            ]);
    }
}
