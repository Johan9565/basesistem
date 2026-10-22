<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Assistant\NotificationDispatcherService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendDailyDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assistant:send-daily-digest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera y envía de forma autónoma el resumen matutino con la agenda del día.';

    /**
     * Execute the console command.
     */
    public function handle(NotificationDispatcherService $dispatcher): int
    {
        $tz = 'America/Cancun';
        $today = Carbon::now($tz);
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay   = $today->copy()->endOfDay();

        $this->info("Generando Daily Digest para la fecha: {$today->toDateString()} ({$tz})");

        $users = User::all();
        if ($users->isEmpty()) {
            $this->warn('No se encontraron usuarios en la base de datos.');
            return Command::SUCCESS;
        }

        foreach ($users as $user) {
            $userId = (string) $user->id;

            $events = CalendarEvent::where('user_id', $userId)
                ->where('start_time', '>=', $startOfDay)
                ->where('start_time', '<=', $endOfDay)
                ->orderBy('start_time', 'asc')
                ->get();

            $reminders = Reminder::where('user_id', $userId)
                ->where('status', 'pending')
                ->where('scheduled_at', '>=', $startOfDay)
                ->where('scheduled_at', '<=', $endOfDay)
                ->orderBy('scheduled_at', 'asc')
                ->get();

            if ($events->isEmpty() && $reminders->isEmpty()) {
                $this->line("Usuario [{$user->name}]: Sin compromisos agendados hoy. Enviando aviso de día despejado.");
                $freeDayMsg = "☀️ *¡Buenos días, {$user->name}!*\n\n"
                    . "📅 *Fecha:* {$today->locale('es')->isoFormat('dddd D [de] MMMM')}\n\n"
                    . "🎉 No tienes citas ni tareas pendientes registradas para hoy. ¡Disfruta un día productivo y despejado!";

                $dispatcher->dispatchDailyDigest($user, $freeDayMsg);
                continue;
            }

            // Compile context for LLM
            $contextEvents = $events->map(function ($e) {
                $start = $e->start_time ? $e->start_time->format('H:i') : '09:00';
                $end   = $e->end_time ? $e->end_time->format('H:i') : '';
                $range = $end ? "{$start} - {$end}" : $start;
                $loc   = $e->location ? " (Lugar: {$e->location})" : '';
                return "• [{$range}] {$e->title}{$loc}";
            })->implode("\n");

            $contextReminders = $reminders->map(function ($r) {
                $time = $r->scheduled_at ? $r->scheduled_at->format('H:i') : 'Durante el día';
                $prio = strtoupper($r->priority ?: 'normal');
                return "• [{$time}] {$r->title} (Prioridad: {$prio})";
            })->implode("\n");

            $digestText = $this->generateExecutiveSummary($user, $today, $contextEvents, $contextReminders);

            $dispatcher->dispatchDailyDigest($user, $digestText);

            $this->line("✔ Resumen matutino despachado para: {$user->name}");
            Log::info("Daily digest despachado para usuario: {$user->name} ({$userId})");
        }

        $this->info('Proceso de Daily Digest finalizado con éxito.');

        return Command::SUCCESS;
    }

    /**
     * Request an executive, motivating morning summary from LLM.
     */
    protected function generateExecutiveSummary(User $user, Carbon $today, string $eventsList, string $remindersList): string
    {
        $apiUrl = config('services.assistant.url', 'https://api.deepseek.com');
        $apiKey = config('services.assistant.key', '');
        $model  = config('services.assistant.model', 'deepseek-chat');

        $endpoint = rtrim($apiUrl, '/') . (str_ends_with($apiUrl, '/chat/completions') ? '' : '/chat/completions');

        $prompt = <<<PROMPT
Eres el Asistente Personal Ejecutivo de {$user->name}.
Fecha actual: {$today->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')}.

CITAS Y EVENTOS DE HOY:
{$eventsList}

RECORDATORIOS Y PENDIENTES DE HOY:
{$remindersList}

INSTRUCCIONES:
Redacta un mensaje matutino de buenos días ejecutivo, elegante, breve y motivador.
- Usa formato compatible con WhatsApp/Telegram (*negrita*, viñetas limpias).
- Comienza saludando cordialmente por su nombre.
- Organiza claramente los compromisos en orden cronológico.
- Finaliza con una breve frase motivadora de éxito para su jornada.
PROMPT;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(35)
                ->post($endpoint, [
                    'model'       => $model,
                    'messages'    => [
                        ['role' => 'system', 'content' => 'Eres un Asistente Personal Ejecutivo sobrio, proactivo y muy claro.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.4,
                ]);

            if ($response->successful()) {
                $content = (string) $response->json('choices.0.message.content');
                if (!empty(trim($content))) {
                    return trim($content);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Error generando Daily Digest con LLM: " . $e->getMessage());
        }

        // Fallback text if LLM call fails
        $fallback = "☀️ *¡Buenos días, {$user->name}!*\n\n"
            . "📅 *Agenda de hoy ({$today->format('d/m/Y')}):*\n\n";

        if (!empty($eventsList)) {
            $fallback .= "*Citas y Eventos:*\n{$eventsList}\n\n";
        }
        if (!empty($remindersList)) {
            $fallback .= "*Recordatorios Pendientes:*\n{$remindersList}\n\n";
        }
        $fallback .= "¡Que tengas un día muy productivo!";

        return $fallback;
    }
}
