<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Cache;

/**
 * Estado compartido en memoria rápida (Redis) del pipeline de WhatsApp.
 *
 * Todas las claves se componen bajo el esquema [prefijo]:[instancia]:[chat]
 * para soportar múltiples instancias de forma aislada:
 * - pausa:[instancia]:[chat]      → bandera de control humano (TTL = tiempo_pausa_humano).
 * - sesion:[instancia]:[chat]     → identificador de sesión activa (TTL = tiempo_expiracion_sesion).
 * - buffer:[instancia]:[chat]     → lista temporal de textos de la ráfaga actual.
 * - mensajes_bot:[id_mensaje]     → envíos propios del bot (TTL corto, evita la autopausa).
 */
class ChatStateStore
{
    public function pauseKey(string $instance, string $chat): string
    {
        return "pausa:{$instance}:{$chat}";
    }

    public function sessionKey(string $instance, string $chat): string
    {
        return "sesion:{$instance}:{$chat}";
    }

    public function bufferKey(string $instance, string $chat): string
    {
        return "buffer:{$instance}:{$chat}";
    }

    public static function botMessageKey(string $messageId): string
    {
        return "mensajes_bot:{$messageId}";
    }

    // ------------------------------------------------------------
    // Bandera de pausa (control humano)
    // ------------------------------------------------------------

    public function isPaused(string $instance, string $chat): bool
    {
        return Cache::has($this->pauseKey($instance, $chat));
    }

    public function pause(string $instance, string $chat, int $minutes): void
    {
        Cache::put(
            $this->pauseKey($instance, $chat),
            now()->toIso8601String(),
            max(1, $minutes) * 60
        );
    }

    public function clearPause(string $instance, string $chat): void
    {
        Cache::forget($this->pauseKey($instance, $chat));
    }

    // ------------------------------------------------------------
    // Identificador de sesión
    // ------------------------------------------------------------

    public function sessionId(string $instance, string $chat): ?string
    {
        $value = Cache::get($this->sessionKey($instance, $chat));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function renewSession(string $instance, string $chat, string $sessionId, int $hours): void
    {
        Cache::put(
            $this->sessionKey($instance, $chat),
            $sessionId,
            max(1, $hours) * 3600
        );
    }

    public function clearSession(string $instance, string $chat): void
    {
        Cache::forget($this->sessionKey($instance, $chat));
    }

    // ------------------------------------------------------------
    // Búfer anti-metralleta
    // ------------------------------------------------------------

    public function pushBuffer(string $instance, string $chat, string $text, ?float $at = null): void
    {
        $key = $this->bufferKey($instance, $chat);
        $items = Cache::get($key, []);
        $items[] = ['text' => $text, 'at' => $at ?? microtime(true)];
        Cache::put($key, $items, 3600);
    }

    /**
     * Extrae el búfer consolidado solo si la ventana de espera ya está en reposo.
     *
     * @return list<string>|null Textos acumulados o null si aún siguen llegando mensajes
     */
    public function pullBufferIfIdle(string $instance, string $chat, int $windowSeconds): ?array
    {
        $key = $this->bufferKey($instance, $chat);
        $items = Cache::get($key, []);

        if ($items === []) {
            return [];
        }

        $last = end($items);
        $elapsed = microtime(true) - (float) ($last['at'] ?? 0);

        if ($windowSeconds > 0 && $elapsed < $windowSeconds) {
            return null;
        }

        Cache::forget($key);

        return array_map(fn ($item) => (string) ($item['text'] ?? ''), $items);
    }

    /**
     * Segundos que faltan para que el búfer quede en reposo (0 si ya estálo).
     */
    public function bufferIdleIn(string $instance, string $chat, int $windowSeconds): float
    {
        $items = Cache::get($this->bufferKey($instance, $chat), []);

        if ($items === []) {
            return 0.0;
        }

        $last = end($items);
        $elapsed = microtime(true) - (float) ($last['at'] ?? 0);

        return max(0.0, $windowSeconds - $elapsed);
    }

    // ------------------------------------------------------------
    // Registro de envíos propios del bot
    // ------------------------------------------------------------

    public function registerBotMessage(string $messageId, ?int $ttlSeconds = null): void
    {
        if ($messageId === '') {
            return;
        }

        Cache::put(
            self::botMessageKey($messageId),
            true,
            $ttlSeconds ?? (int) config('services.whatsapp.bot_message_ttl', 60)
        );
    }

    public function isBotMessage(string $messageId): bool
    {
        return $messageId !== '' && Cache::has(self::botMessageKey($messageId));
    }
}
