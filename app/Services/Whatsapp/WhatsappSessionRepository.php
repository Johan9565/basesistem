<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;

class WhatsappSessionRepository
{
    public function open(
        string $instanceName,
        string $userPhone,
        string $sessionId,
        ?string $companyId = null
    ): WhatsappSession {
        return WhatsappSession::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'instance_name' => $instanceName,
            'user_phone' => $userPhone,
            'session_id' => $sessionId,
            'status' => WhatsappSession::STATUS_ACTIVA,
            'opened_at' => now(),
        ]);
    }

    public function findActive(string $instanceName, string $userPhone): ?WhatsappSession
    {
        return WhatsappSession::withoutGlobalScopes()
            ->where('instance_name', $instanceName)
            ->where('user_phone', $userPhone)
            ->where('status', WhatsappSession::STATUS_ACTIVA)
            ->orderBy('_id', 'desc')
            ->first();
    }

    public function closeActive(string $instanceName, string $userPhone, string $reason): ?WhatsappSession
    {
        $row = $this->findActive($instanceName, $userPhone);

        if (! $row) {
            return null;
        }

        $row->status = WhatsappSession::STATUS_CERRADA;
        $row->close_reason = $reason;
        $row->closed_at = now();
        $row->save();

        return $row;
    }
}
