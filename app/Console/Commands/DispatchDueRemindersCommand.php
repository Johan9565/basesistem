<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\User;
use App\Services\Assistant\NotificationDispatcherService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchDueRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assistant:dispatch-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Despacha proactivamente alertas de recordatorios vencidos mediante WhatsApp, Telegram o Push.';

    /**
     * Execute the console command.
     */
    public function handle(NotificationDispatcherService $dispatcher): int
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

        $this->info("Despachando {$count} recordatorios pendientes...");

        foreach ($dueReminders as $reminder) {
            // Actualizar atómicamente a completed para prevenir disparos duplicados
            $reminder->update([
                'status' => 'completed',
            ]);

            $user = User::find($reminder->user_id);
            if (!$user) {
                // If user relation not found, create minimal representation or fallback
                $user = new User(['id' => $reminder->user_id]);
                $user->id = $reminder->user_id;
            }

            // Invocar el canal configurado
            $dispatched = $dispatcher->dispatchReminder($user, $reminder);

            $idStr = (string) ($reminder->id ?? $reminder->_id);
            $logMsg = sprintf(
                '[%s] Alerta despachada - ID: %s | Usuario: %s | Canal: %s | Título: %s | Éxito: %s',
                Carbon::now('America/Cancun')->toIso8601String(),
                $idStr,
                $reminder->user_id,
                $reminder->channel ?: 'push',
                $reminder->title,
                $dispatched ? 'SI' : 'NO'
            );

            Log::info($logMsg);
            $this->line("✔ {$logMsg}");
        }

        $this->info("Se completó el despacho de {$count} recordatorios.");

        return Command::SUCCESS;
    }
}
