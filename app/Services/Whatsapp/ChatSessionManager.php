<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappInstance;
use App\Models\WhatsappSession;
use Illuminate\Support\Str;

/**
 * Ciclo de vida de la conversación:
 * - Puntero de sesión en memoria rápida con TTL = tiempo_expiracion_sesion.
 * - Registro de la conversación activa/cerrada en MongoDB (whatsapp_sessions).
 *
 * El cierre ocurre por tres vías: expiración natural del TTL, comando #cerrar
 * del operador o la etiqueta [CONVERSATION_CLOSED] devuelta por el modelo.
 */
class ChatSessionManager
{
    public function __construct(
        protected ChatStateStore $store,
        protected WhatsappSessionRepository $sessions,
    ) {
    }

    /**
     * Devuelve el identificador de la sesión activa, creándolo si no existe.
     * Si el puntero expiró por inactividad, la conversación previa se cierra
     * y el próximo mensaje comienza una sesión nueva desde cero.
     */
    public function resolveOrCreate(string $instanceName, string $userPhone, ?WhatsappInstance $profile = null): string
    {
        $hours = $this->sessionHours($profile);
        $existing = $this->store->sessionId($instanceName, $userPhone);

        if ($existing !== null) {
            // La conversación sigue viva: se renueva su tiempo de expiración.
            $this->store->renewSession($instanceName, $userPhone, $existing, $hours);

            return $existing;
        }

        // El puntero expiró: la conversación anterior murió por inactividad.
        $this->sessions->closeActive($instanceName, $userPhone, WhatsappSession::REASON_INACTIVIDAD);

        $sessionId = (string) Str::uuid();
        $this->sessions->open(
            $instanceName,
            $userPhone,
            $sessionId,
            $profile && $profile->company_id ? (string) $profile->company_id : null
        );
        $this->store->renewSession($instanceName, $userPhone, $sessionId, $hours);

        return $sessionId;
    }

    /**
     * Renueva el TTL de la sesión activa sin crear una nueva.
     */
    public function renew(string $instanceName, string $userPhone, ?WhatsappInstance $profile = null): void
    {
        $existing = $this->store->sessionId($instanceName, $userPhone);

        if ($existing !== null) {
            $this->store->renewSession($instanceName, $userPhone, $existing, $this->sessionHours($profile));
        }
    }

    /**
     * Cierra la conversación: pasa a estado "Cerrada" en base de datos
     * y se elimina el puntero de la memoria rápida.
     */
    public function close(string $instanceName, string $userPhone, string $reason): void
    {
        $this->sessions->closeActive($instanceName, $userPhone, $reason);
        $this->store->clearSession($instanceName, $userPhone);
    }

    protected function sessionHours(?WhatsappInstance $profile): int
    {
        return $profile?->sessionHours()
            ?? max(1, (int) config('services.whatsapp.tiempo_expiracion_sesion', 24));
    }
}
