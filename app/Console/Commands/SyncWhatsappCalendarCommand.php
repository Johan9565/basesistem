<?php

namespace App\Console\Commands;

use App\Models\WhatsappInstance;
use App\Services\Whatsapp\GoogleCalendarService;
use Illuminate\Console\Command;
use Throwable;

class SyncWhatsappCalendarCommand extends Command
{
    protected $signature = 'whatsapp:sync-calendar
        {instance? : Nombre de instancia (todas si se omite)}
        {--days-back=7 : Días hacia atrás}
        {--days-forward=60 : Días hacia adelante}';

    protected $description = 'Importa eventos de Google Calendar al calendario interno por instancia';

    public function handle(GoogleCalendarService $calendar): int
    {
        $name = $this->argument('instance');
        $query = WhatsappInstance::query()->orderBy('instance_name');
        if (is_string($name) && $name !== '') {
            $query->where('instance_name', $name);
        }

        $instances = $query->get();
        if ($instances->isEmpty()) {
            $this->error('No hay instancias.');

            return self::FAILURE;
        }

        $back = max(0, (int) $this->option('days-back'));
        $forward = max(1, (int) $this->option('days-forward'));

        foreach ($instances as $instance) {
            $tz = (string) ($instance->timezone ?: config('services.google_calendar.timezone'));
            try {
                $stats = $calendar->syncInstanceFromGoogle(
                    $instance,
                    now($tz)->subDays($back)->startOfDay(),
                    now($tz)->addDays($forward)->endOfDay(),
                );
                $this->info(sprintf(
                    '%s → imported=%d updated=%d skipped=%d',
                    $instance->instance_name,
                    $stats['imported'],
                    $stats['updated'],
                    $stats['skipped'],
                ));
            } catch (Throwable $e) {
                $this->error($instance->instance_name.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
