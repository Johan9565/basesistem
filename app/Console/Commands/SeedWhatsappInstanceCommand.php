<?php

namespace App\Console\Commands;

use App\Models\WhatsappInstance;
use Illuminate\Console\Command;

class SeedWhatsappInstanceCommand extends Command
{
    protected $signature = 'whatsapp:seed-instance
        {name=instancia_local : Nombre de la instancia Evolution}
        {--calendar= : Google Calendar ID (default: GOOGLE_CALENDAR_ID)}
        {--timezone= : Zona horaria (default: GOOGLE_CALENDAR_TIMEZONE)}
        {--prompt= : System prompt personalizado}';

    protected $description = 'Crea o actualiza un documento whatsapp_instances en MongoDB';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $calendarId = (string) ($this->option('calendar') ?: config('services.google_calendar.calendar_id'));
        $timezone = (string) ($this->option('timezone') ?: config('services.google_calendar.timezone'));
        $prompt = $this->option('prompt');

        if ($calendarId === '') {
            $this->error('Debes indicar --calendar=... o configurar GOOGLE_CALENDAR_ID.');

            return self::FAILURE;
        }

        $instance = WhatsappInstance::query()->updateOrCreate(
            ['instance_name' => $name],
            [
                'google_calendar_id' => $calendarId,
                'timezone' => $timezone,
                'system_prompt' => is_string($prompt) && $prompt !== '' ? $prompt : null,
                'status' => 'active',
            ],
        );

        $this->info("Instancia guardada: {$instance->instance_name} → calendar {$instance->google_calendar_id}");

        return self::SUCCESS;
    }
}
