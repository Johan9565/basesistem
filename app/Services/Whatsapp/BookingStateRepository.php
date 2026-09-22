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
            return $state;
        }

        return WhatsappBookingState::query()->create([
            'instance_name' => $instanceName,
            'user_phone' => $userPhone,
            'step' => 'NEW',
            'name' => null,
            'service' => null,
            'date' => null,
            'time' => null,
            'notes' => null,
            'last_interaction_at' => now(),
            'welcome_sent_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function updateState(string $instanceName, string $userPhone, array $fields): WhatsappBookingState
    {
        $state = $this->findOrCreate($instanceName, $userPhone);
        $allowed = ['step', 'name', 'service', 'date', 'time', 'notes'];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields) && $fields[$key] !== null && $fields[$key] !== '') {
                $state->{$key} = is_string($fields[$key]) ? trim($fields[$key]) : $fields[$key];
            }
        }

        $state->last_interaction_at = now();
        $state->save();

        return $state;
    }

    public function markCompleted(string $instanceName, string $userPhone): void
    {
        $state = $this->findOrCreate($instanceName, $userPhone);
        $state->step = 'COMPLETED';
        $state->last_interaction_at = now();
        $state->save();
    }

    public function resetBookingFields(string $instanceName, string $userPhone): WhatsappBookingState
    {
        $state = $this->findOrCreate($instanceName, $userPhone);
        $state->step = 'NEW';
        $state->name = null;
        $state->service = null;
        $state->date = null;
        $state->time = null;
        $state->notes = null;
        $state->last_interaction_at = now();
        $state->save();

        return $state;
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
        if ($state->step === 'NEW' && ! $state->welcome_sent_at) {
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
        if ($state->step === 'NEW' || $state->step === 'COMPLETED') {
            $state->step = 'GREETING';
        }
        $state->save();
    }

    /**
     * Compact JSON for system prompt (multi-instance safe: already scoped by instance+phone).
     */
    public function toPromptJson(WhatsappBookingState $state): string
    {
        $payload = [
            'step' => $state->step ?: 'NEW',
            'name' => $state->name,
            'service' => $state->service,
            'date' => $state->date,
            'time' => $state->time,
            'notes' => $state->notes,
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
