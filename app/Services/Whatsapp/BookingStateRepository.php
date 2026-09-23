<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappBookingState;
use Carbon\Carbon;

class BookingStateRepository
{
    public function find(string $instanceName, string $userPhone): ?WhatsappBookingState
    {
        return WhatsappBookingState::query()
            ->where('instance_name', $instanceName)
            ->where('user_phone', $userPhone)
            ->first();
    }

    public function touch(string $instanceName, string $userPhone): WhatsappBookingState
    {
        $state = $this->findOrCreate($instanceName, $userPhone);
        $state->last_interaction_at = now();
        $state->save();

        return $state;
    }

    public function findOrCreate(string $instanceName, string $userPhone): WhatsappBookingState
    {
        $state = $this->find($instanceName, $userPhone);
        if ($state) {
            $normalized = $this->normalizeStage((string) ($state->step ?? ''));
            if ($normalized !== (string) $state->step) {
                $state->step = $normalized;
                $state->save();
            }

            return $state;
        }

        return WhatsappBookingState::query()->create([
            'instance_name' => $instanceName,
            'user_phone' => $userPhone,
            'step' => WhatsappBookingState::STAGE_RECEPTION,
            'name' => null,
            'service' => null,
            'date' => null,
            'time' => null,
            'location' => null,
            'notes' => null,
            'intent' => null,
            'friction_count' => 0,
            'bot_paused_at' => null,
            'escalation_reason' => null,
            'escalation_detail' => null,
            'last_interaction_at' => now(),
            'welcome_sent_at' => null,
        ]);
    }

    /**
     * Map legacy soft-steps into the 3-stage machine.
     */
    public function normalizeStage(string $step): string
    {
        $step = strtoupper(trim($step));

        return match ($step) {
            WhatsappBookingState::STAGE_RECEPTION,
            WhatsappBookingState::STAGE_COLLECTING,
            WhatsappBookingState::STAGE_CONFIRMING,
            WhatsappBookingState::STAGE_COMPLETED => $step,
            'NEW', 'GREETING', '' => WhatsappBookingState::STAGE_RECEPTION,
            'AWAITING_NAME',
            'AWAITING_SERVICE',
            'AWAITING_DATE',
            'AWAITING_TIME',
            'READY' => $step === 'READY'
                ? WhatsappBookingState::STAGE_CONFIRMING
                : WhatsappBookingState::STAGE_COLLECTING,
            default => WhatsappBookingState::STAGE_RECEPTION,
        };
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function updateState(string $instanceName, string $userPhone, array $fields): WhatsappBookingState
    {
        $state = $this->findOrCreate($instanceName, $userPhone);
        $allowed = ['step', 'name', 'service', 'date', 'time', 'location', 'notes', 'intent'];

        $clearFields = $fields['clear_fields'] ?? [];
        if (is_array($clearFields)) {
            foreach ($clearFields as $key) {
                if (in_array($key, ['name', 'service', 'date', 'time', 'location', 'notes', 'intent'], true)) {
                    $state->{$key} = null;
                }
            }
        }

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $fields)) {
                continue;
            }

            $value = $fields[$key];

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                if ($key !== 'step') {
                    $state->{$key} = null;
                }

                continue;
            }

            if ($key === 'step') {
                $state->step = $this->normalizeStage((string) $value);

                continue;
            }

            $state->{$key} = is_string($value) ? trim($value) : $value;
        }

        if (
            $this->hasRequiredBookingData($state)
            && $this->normalizeStage((string) $state->step) === WhatsappBookingState::STAGE_COLLECTING
        ) {
            $state->step = WhatsappBookingState::STAGE_CONFIRMING;
        }

        $state->last_interaction_at = now();
        $state->save();

        return $state;
    }

    public function hasRequiredBookingData(WhatsappBookingState $state): bool
    {
        return filled($state->name)
            && filled($state->service)
            && filled($state->date)
            && filled($state->time);
    }

    public function markCompleted(string $instanceName, string $userPhone): void
    {
        $state = $this->findOrCreate($instanceName, $userPhone);
        $state->step = WhatsappBookingState::STAGE_COMPLETED;
        $state->friction_count = 0;
        $state->last_interaction_at = now();
        $state->save();
    }

    public function resetBookingFields(string $instanceName, string $userPhone): WhatsappBookingState
    {
        return $this->resetToReception($instanceName, $userPhone, resumeBot: true);
    }

    public function resetToReception(
        string $instanceName,
        string $userPhone,
        bool $resumeBot = true,
    ): WhatsappBookingState {
        $state = $this->findOrCreate($instanceName, $userPhone);
        $state->step = WhatsappBookingState::STAGE_RECEPTION;
        $state->name = null;
        $state->service = null;
        $state->date = null;
        $state->time = null;
        $state->location = null;
        $state->notes = null;
        $state->intent = null;
        $state->friction_count = 0;
        if ($resumeBot) {
            $state->bot_paused_at = null;
            $state->escalation_reason = null;
            $state->escalation_detail = null;
        }
        $state->last_interaction_at = now();
        $state->save();

        return $state;
    }

    public function isBotPaused(WhatsappBookingState $state): bool
    {
        return $state->bot_paused_at !== null;
    }

    public function pauseBot(
        string $instanceName,
        string $userPhone,
        string $reason,
        ?string $detail = null,
    ): WhatsappBookingState {
        $state = $this->findOrCreate($instanceName, $userPhone);
        $state->bot_paused_at = now();
        $state->escalation_reason = $reason;
        $state->escalation_detail = $detail;
        $state->last_interaction_at = now();
        $state->save();

        return $state;
    }

    public function resumeBot(string $instanceName, string $userPhone): WhatsappBookingState
    {
        $state = $this->findOrCreate($instanceName, $userPhone);
        $state->bot_paused_at = null;
        $state->escalation_reason = null;
        $state->escalation_detail = null;
        $state->friction_count = 0;
        $state->last_interaction_at = now();
        $state->save();

        return $state;
    }

    public function incrementFriction(string $instanceName, string $userPhone): int
    {
        $state = $this->findOrCreate($instanceName, $userPhone);
        $state->friction_count = (int) ($state->friction_count ?? 0) + 1;
        $state->last_interaction_at = now();
        $state->save();

        return (int) $state->friction_count;
    }

    public function isInactive(WhatsappBookingState $state, int $hours = 24): bool
    {
        if (! $state->last_interaction_at) {
            return true;
        }

        return Carbon::parse($state->last_interaction_at)->lt(now()->subHours($hours));
    }

    public function shouldSendWelcome(WhatsappBookingState $state, int $hours = 24): bool
    {
        $stage = $this->normalizeStage((string) ($state->step ?? ''));

        if ($stage === WhatsappBookingState::STAGE_RECEPTION && ! $state->welcome_sent_at) {
            return true;
        }

        if ($this->isInactive($state, $hours)) {
            return true;
        }

        return false;
    }

    public function markWelcomeSent(WhatsappBookingState $state): void
    {
        $state->welcome_sent_at = now();
        $state->last_interaction_at = now();
        $stage = $this->normalizeStage((string) ($state->step ?? ''));
        if (
            $stage === WhatsappBookingState::STAGE_RECEPTION
            || $stage === WhatsappBookingState::STAGE_COMPLETED
        ) {
            $state->step = WhatsappBookingState::STAGE_RECEPTION;
        }
        $state->save();
    }

    /**
     * Compact JSON for system prompt (multi-instance safe: already scoped by instance+phone).
     */
    public function toPromptJson(WhatsappBookingState $state): string
    {
        $stage = $this->normalizeStage((string) ($state->step ?? ''));

        $payload = [
            'etapa' => $stage,
            'etapa_label' => match ($stage) {
                WhatsappBookingState::STAGE_COLLECTING => 'RECOLECTANDO',
                WhatsappBookingState::STAGE_CONFIRMING => 'CONFIRMANDO',
                WhatsappBookingState::STAGE_COMPLETED => 'COMPLETADO',
                default => 'RECEPCION',
            },
            'nombre' => $state->name ?: 'Pendiente',
            'servicio_producto' => $state->service ?: 'Pendiente',
            'fecha_deseada' => $state->date ?: 'Pendiente',
            'horario' => $state->time ?: 'Pendiente',
            'ubicacion' => $state->location ?: 'Pendiente',
            'notas' => $state->notes ?: null,
            'intent' => $state->intent ?: null,
            'datos_completos' => $this->hasRequiredBookingData($state),
            'bot_pausado' => $this->isBotPaused($state),
            'friction_count' => (int) ($state->friction_count ?? 0),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(WhatsappBookingState $state): array
    {
        $stage = $this->normalizeStage((string) ($state->step ?? ''));

        return [
            'step' => $stage,
            'step_label' => match ($stage) {
                WhatsappBookingState::STAGE_COLLECTING => 'Recolectando datos',
                WhatsappBookingState::STAGE_CONFIRMING => 'Confirmando',
                WhatsappBookingState::STAGE_COMPLETED => 'Completado',
                default => 'Recepción',
            },
            'name' => $state->name,
            'service' => $state->service,
            'date' => $state->date,
            'time' => $state->time,
            'location' => $state->location,
            'notes' => $state->notes,
            'bot_paused' => $this->isBotPaused($state),
            'escalation_reason' => $state->escalation_reason,
            'escalation_detail' => $state->escalation_detail,
            'friction_count' => (int) ($state->friction_count ?? 0),
            'paused_at' => $state->bot_paused_at?->toIso8601String(),
        ];
    }
}
