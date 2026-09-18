<?php

namespace App\Services\Whatsapp;

use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleCalendarService
{
    public function createEvent(
        string $calendarId,
        string $summary,
        string $startIso,
        string $endIso,
        ?string $timezone = null,
        ?string $description = null,
    ): array {
        if (! class_exists(GoogleClient::class)) {
            throw new RuntimeException(
                'Falta el paquete google/apiclient. Ejecuta: composer require google/apiclient'
            );
        }

        $timezone = $timezone ?: (string) config('services.google_calendar.timezone', 'America/Merida');
        $client = $this->makeClient();
        $calendar = new Calendar($client);

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

    protected function makeClient(): GoogleClient
    {
        $relativePath = (string) config('services.google_calendar.credentials_path');
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
