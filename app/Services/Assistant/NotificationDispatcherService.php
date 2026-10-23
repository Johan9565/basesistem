<?php

namespace App\Services\Assistant;

use App\Models\NotificationsModel;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationDispatcherService
{
    /**
     * Dispatch an alert for a due reminder according to its channel.
     */
    public function dispatchReminder(User $user, Reminder $reminder): bool
    {
        $channel = strtolower((string) ($reminder->channel ?: 'push'));
        $priorityEmoji = '⏰ [MEDIA]';
        if ($reminder->priority === 'high') {
            $priorityEmoji = '🚨 [ALTA]';
        } elseif ($reminder->priority === 'low') {
            $priorityEmoji = 'ℹ️ [BAJA]';
        }

        $scheduledFormatted = $reminder->scheduled_at 
            ? $reminder->scheduled_at->format('d/m/Y H:i') 
            : 'Ahora';

        $text = "{$priorityEmoji} *Recordatorio:* {$reminder->title}\n"
              . "📅 *Programado:* {$scheduledFormatted}\n"
              . "⚡ *Workspace Assistant*";

        // Always record in-app notification
        $this->sendPush($user, "⏰ Recordatorio: {$reminder->title}", [
            'item_id' => (string) ($reminder->id ?? $reminder->_id),
            'routes'  => ['name' => 'assistant.workspace'],
        ]);

        if ($channel === 'whatsapp') {
            return $this->sendWhatsApp($this->resolveUserPhone($user), $text);
        }

        if ($channel === 'telegram') {
            return $this->sendTelegram($this->resolveUserTelegramChatId($user), $text);
        }

        return true;
    }

    /**
     * Dispatch daily morning digest to user.
     */
    public function dispatchDailyDigest(User $user, string $digestText, ?string $channel = null): bool
    {
        // Default to whatsapp if available, then telegram, then push
        $resolvedChannel = $channel ?: $this->resolvePreferredChannel($user);

        // Always record in-app
        $this->sendPush($user, "☀️ Resumen Matutino del Día", [
            'routes' => ['name' => 'assistant.workspace'],
            'body'   => $digestText,
        ]);

        if ($resolvedChannel === 'whatsapp') {
            return $this->sendWhatsApp($this->resolveUserPhone($user), $digestText);
        }

        if ($resolvedChannel === 'telegram') {
            return $this->sendTelegram($this->resolveUserTelegramChatId($user), $digestText);
        }

        return true;
    }

    /**
     * Send message via WhatsApp (Evolution API) with anti-ban and humanized chunking.
     */
    public function sendWhatsApp(?string $number, string $message): bool
    {
        if (empty($number)) {
            Log::warning('NotificationDispatcher: No se pudo enviar WhatsApp. Número destino vacío.');
            return false;
        }

        $instance = (string) config('services.evolution.instance', 'default');
        $cleanNumber = preg_replace('/\D+/', '', $number);

        try {
            /** @var \App\Services\Whatsapp\EvolutionApiService $evolution */
            $evolution = app(\App\Services\Whatsapp\EvolutionApiService::class);
            $results = $evolution->sendHumanizedMessage($instance, $cleanNumber, $message);

            if (!empty($results)) {
                Log::info("NotificationDispatcher: WhatsApp enviado con éxito a [{$cleanNumber}].");
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::error("NotificationDispatcher: Excepción al enviar WhatsApp: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send message via Telegram Bot API.
     */
    public function sendTelegram(?string $chatId, string $message): bool
    {
        if (empty($chatId)) {
            Log::warning('NotificationDispatcher: No se pudo enviar Telegram. Chat ID vacío.');
            return false;
        }

        $botToken = (string) config('services.telegram.bot_token', '');
        if (empty($botToken)) {
            Log::warning('NotificationDispatcher: TELEGRAM_BOT_TOKEN no configurado.');
            return false;
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        try {
            $response = Http::retry(2, 400)
                ->timeout(15)
                ->post($url, [
                    'chat_id'    => $chatId,
                    'text'       => $message,
                    'parse_mode' => 'Markdown',
                ]);

            if ($response->successful()) {
                Log::info("NotificationDispatcher: Telegram enviado con éxito a [{$chatId}].");
                return true;
            }

            Log::error("NotificationDispatcher: Error enviando Telegram ({$response->status()}): " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("NotificationDispatcher: Excepción al enviar Telegram: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send in-app / push notification and store in database.
     */
    public function sendPush(User $user, string $message, array $options = []): bool
    {
        try {
            NotificationsModel::create([
                'user_id' => (string) $user->id,
                'message' => $message,
                'item_id' => $options['item_id'] ?? null,
                'is_read' => false,
                'routes'  => $options['routes'] ?? ['name' => 'assistant.workspace'],
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("NotificationDispatcher: Error creando notificación push en BD: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve destination phone number for WhatsApp.
     */
    protected function resolveUserPhone(User $user): ?string
    {
        return $user->phone 
            ?? $user->whatsapp 
            ?? $user->telephone 
            ?? env('ASSISTANT_NOTIFICATION_PHONE');
    }

    /**
     * Resolve Telegram Chat ID.
     */
    protected function resolveUserTelegramChatId(User $user): ?string
    {
        return $user->telegram_chat_id 
            ?? config('services.telegram.chat_id');
    }

    /**
     * Resolve preferred channel for user.
     */
    protected function resolvePreferredChannel(User $user): string
    {
        if ($this->resolveUserPhone($user) && config('services.evolution.api_key')) {
            return 'whatsapp';
        }

        if ($this->resolveUserTelegramChatId($user) && config('services.telegram.bot_token')) {
            return 'telegram';
        }

        return 'push';
    }
}
