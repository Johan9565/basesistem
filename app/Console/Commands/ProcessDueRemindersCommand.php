<?php

namespace App\Console\Commands;

use App\Models\NotificationsModel;
use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDueRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assistant:process-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evalúa y procesa los recordatorios pendientes cuya hora programada ha llegado.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cancunNow = Carbon::now('America/Cancun');

        $dueReminders = Reminder::where('status', 'pending')
            ->where('scheduled_at', '<=', $cancunNow)
            ->get();

        $count = $dueReminders->count();

        if ($count === 0) {
            $this->line("Sin recordatorios pendientes al {$cancunNow->toDateTimeString()}.");
            return Command::SUCCESS;
        }

        $this->info("Procesando {$count} recordatorios pendientes...");

        foreach ($dueReminders as $reminder) {
            // 1. Marcar como completado
            $reminder->update([
                'status' => 'completed',
            ]);

            // 2. Persistir notificación en la base de datos
            NotificationsModel::create([
                'user_id' => (string) $reminder->user_id,
                'message' => "⏰ Recordatorio: {$reminder->title}",
                'item_id' => (string) ($reminder->id ?? $reminder->_id),
                'is_read' => false,
                'routes'  => [
                    'name' => 'assistant.workspace',
                ],
            ]);

            // 3. Dejar preparado el dispatch de notificación push / mensaje
            Log::info("Alerta de recordatorio disparada:", [
                'reminder_id'  => (string) ($reminder->id ?? $reminder->_id),
                'user_id'      => (string) $reminder->user_id,
                'title'        => $reminder->title,
                'scheduled_at' => $reminder->scheduled_at ? $reminder->scheduled_at->toIso8601String() : null,
                'priority'     => $reminder->priority,
                'channel'      => $reminder->channel,
            ]);

            $this->line("✔ Recordatorio completado y notificado: [{$reminder->title}]");
        }

        $this->info("Se procesaron con éxito {$count} recordatorios.");

        return Command::SUCCESS;
    }
}
