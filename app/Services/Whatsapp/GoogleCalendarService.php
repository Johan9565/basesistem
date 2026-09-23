<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappAppointment;
use App\Models\WhatsappInstance;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GoogleCalendarService
{
    public function createEvent(
        string $calendarId,
        string $summary,
        string $startIso,
        string $endIso,
        ?string $timezone = null,
        ?string $description = null,
        ?string $credentialsPath = null,
    ): array {
        $timezone = $timezone ?: (string) config('services.google_calendar.timezone', 'America/Merida');
        $calendar = new Calendar($this->makeClient($credentialsPath));

        $event = new Event([
            'summary' => $summary,
            'description' => $description,
            'start' => new EventDateTime([
                'dateTime' => $startIso,
                'timeZone' => $timezone,
            ]),
            'end' => new EventDateTime([
                'dateTime' => $endIso,
                'timeZone' => $timezone,
            ]),
        ]);

        $created = $calendar->events->insert($calendarId, $event);

        Log::info('Google Calendar event created', [
            'calendar_id' => $calendarId,
            'event_id' => $created->getId(),
            'summary' => $summary,
        ]);

        return [
            'id' => $created->getId(),
            'html_link' => $created->getHtmlLink(),
            'summary' => $created->getSummary(),
            'start' => $created->getStart()?->getDateTime(),
            'end' => $created->getEnd()?->getDateTime(),
            'status' => $created->getStatus(),
        ];
    }

    public function deleteEvent(
        string $calendarId,
        string $eventId,
        ?string $credentialsPath = null,
    ): void {
        $calendar = new Calendar($this->makeClient($credentialsPath));
        $calendar->events->delete($calendarId, $eventId);

        Log::info('Google Calendar event deleted', [
            'calendar_id' => $calendarId,
            'event_id' => $eventId,
        ]);
    }

    /**
     * @return list<array{id: string, summary: string, description: ?string, start: ?string, end: ?string, html_link: ?string, status: ?string}>
     */
    public function listEvents(
        string $calendarId,
        Carbon $timeMin,
        Carbon $timeMax,
        ?string $credentialsPath = null,
        int $maxResults = 250,
    ): array {
        $calendar = new Calendar($this->makeClient($credentialsPath));

        $response = $calendar->events->listEvents($calendarId, [
            'timeMin' => $timeMin->toRfc3339String(),
            'timeMax' => $timeMax->toRfc3339String(),
            'singleEvents' => true,
            'orderBy' => 'startTime',
            'maxResults' => $maxResults,
        ]);

        $out = [];
        foreach ($response->getItems() ?? [] as $event) {
            /** @var Event $event */
            if (($event->getStatus() ?? '') === 'cancelled') {
                continue;
            }

            $start = $event->getStart()?->getDateTime() ?: $event->getStart()?->getDate();
            $end = $event->getEnd()?->getDateTime() ?: $event->getEnd()?->getDate();

            $out[] = [
                'id' => (string) $event->getId(),
                'summary' => (string) ($event->getSummary() ?? '(Sin título)'),
                'description' => $event->getDescription(),
                'start' => $start,
                'end' => $end,
                'html_link' => $event->getHtmlLink(),
                'status' => $event->getStatus(),
                'all_day' => $event->getStart()?->getDateTime() === null && $event->getStart()?->getDate() !== null,
            ];
        }

        return $out;
    }

    /**
     * True si el rango solapa con eventos ocupados en Google Calendar.
     */
    public function hasBusyConflict(
        string $calendarId,
        Carbon $start,
        Carbon $end,
        ?string $credentialsPath = null,
    ): bool {
        try {
            $calendar = new Calendar($this->makeClient($credentialsPath));
            $item = new FreeBusyRequestItem;
            $item->setId($calendarId);

            $req = new FreeBusyRequest;
            $req->setTimeMin($start->toRfc3339String());
            $req->setTimeMax($end->toRfc3339String());
            $req->setItems([$item]);

            $fb = $calendar->freebusy->query($req);
            $calendars = $fb->getCalendars() ?? [];
            $busy = $calendars[$calendarId]->getBusy() ?? [];

            return count($busy) > 0;
        } catch (Throwable $e) {
            Log::warning('Google freebusy failed; falling back to events.list', [
                'error' => $e->getMessage(),
            ]);

            $events = $this->listEvents($calendarId, $start, $end, $credentialsPath, 50);

            return $events !== [];
        }
    }

    /**
     * Importa / actualiza citas locales desde Google para una instancia.
     *
     * @return array{imported: int, updated: int, skipped: int}
     */
    public function syncInstanceFromGoogle(WhatsappInstance $instance, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $calendarId = (string) ($instance->google_calendar_id ?: config('services.google_calendar.calendar_id'));
        if ($calendarId === '') {
            throw new RuntimeException('La instancia no tiene Google Calendar ID.');
        }

        $timezone = (string) ($instance->timezone ?: config('services.google_calendar.timezone', 'America/Merida'));
        $from ??= Carbon::now($timezone)->subDays(7)->startOfDay();
        $to ??= Carbon::now($timezone)->addDays(60)->endOfDay();

        $events = $this->listEvents(
            $calendarId,
            $from,
            $to,
            $instance->google_credentials_path ?: null,
        );

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($events as $event) {
            if (empty($event['id']) || empty($event['start']) || empty($event['end'])) {
                $skipped++;

                continue;
            }

            // All-day: date only → parse as start of day / next day.
            $start = Carbon::parse($event['start'], $timezone);
            $end = Carbon::parse($event['end'], $timezone);

            $existing = WhatsappAppointment::query()
                ->where('instance_name', $instance->evolutionName())
                ->where('google_event_id', $event['id'])
                ->first();

            $payload = [
                'instance_name' => $instance->evolutionName(),
                'user_phone' => $existing?->user_phone ?: 'google',
                'summary' => $event['summary'],
                'description' => $event['description'],
                'starts_at' => $start,
                'ends_at' => $end,
                'timezone' => $timezone,
                'google_calendar_id' => $calendarId,
                'google_event_id' => $event['id'],
                'google_html_link' => $event['html_link'],
                'sync_status' => 'synced',
                'sync_error' => null,
                'source' => $existing && ($existing->source ?? '') === 'bot' ? 'bot' : 'google',
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                WhatsappAppointment::query()->create($payload);
                $imported++;
            }
        }

        return compact('imported', 'updated', 'skipped');
    }

    /**
     * Citas locales que se solapan con [start, end).
     *
     * @return list<WhatsappAppointment>
     */
    public function findLocalConflicts(string $instanceName, Carbon $start, Carbon $end): array
    {
        $startUtc = $start->copy()->utc();
        $endUtc = $end->copy()->utc();

        // Filtrado en memoria: Mongo puede guardar fechas con offsets distintos.
        return WhatsappAppointment::query()
            ->where('instance_name', $instanceName)
            ->where('starts_at', '>=', $startUtc->copy()->subDays(2))
            ->where('starts_at', '<=', $endUtc->copy()->addDays(2))
            ->orderBy('starts_at')
            ->get()
            ->filter(function (WhatsappAppointment $a) use ($startUtc, $endUtc) {
                if (! $a->starts_at || ! $a->ends_at) {
                    return false;
                }

                $s = Carbon::parse($a->starts_at)->utc();
                $e = Carbon::parse($a->ends_at)->utc();

                return $s->lt($endUtc) && $e->gt($startUtc);
            })
            ->values()
            ->all();
    }

    protected function makeClient(?string $credentialsPath = null): GoogleClient
    {
        if (! class_exists(GoogleClient::class)) {
            throw new RuntimeException(
                'Falta el paquete google/apiclient. Ejecuta: composer require google/apiclient'
            );
        }

        $relativePath = $credentialsPath
            ?: (string) config('services.google_calendar.credentials_path');

        $path = str_starts_with($relativePath, '/')
            ? $relativePath
            : base_path($relativePath);

        if (! is_readable($path)) {
            throw new RuntimeException("No se encontró el JSON de la cuenta de servicio en: {$path}");
        }

        $client = new GoogleClient;
        $client->setAuthConfig($path);
        $client->setScopes([Calendar::CALENDAR]);
        $client->setApplicationName(config('app.name', 'Laravel').' WhatsApp Calendar');

        return $client;
    }
}
