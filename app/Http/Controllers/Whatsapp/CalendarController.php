<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAppointment;
use App\Models\WhatsappInstance;
use App\Services\Whatsapp\GoogleCalendarService;
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
                $count = WhatsappAppointment::query()
                    ->where('instance_name', $name)
                    ->count();

                $next = WhatsappAppointment::query()
                    ->where('instance_name', $name)
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at')
                    ->first();

                return [
                    'instance_name' => $name,
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

        $query = WhatsappAppointment::query()
            ->where('instance_name', $instance)
            ->orderBy('starts_at', 'asc');

        if (is_string($from) && $from !== '') {
            $query->where('starts_at', '>=', $from);
        }
        if (is_string($to) && $to !== '') {
            $query->where('starts_at', '<=', $to);
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
}
