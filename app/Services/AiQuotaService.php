<?php

namespace App\Services;

use App\Exceptions\CuotaIaAgotadaException;
use App\Models\User;
use MongoDB\BSON\ObjectId;
use MongoDB\Operation\FindOneAndUpdate;

class AiQuotaService
{
    public function verificarYResetear(User $user): User
    {
        if (! $user->esUsuarioPremium()) {
            if ((int) ($user->intentos_ia_restantes ?? 0) !== 0) {
                $user->intentos_ia_restantes = 0;
                $user->save();
            }

            return $user->refresh();
        }

        $reseteaEl = $user->limite_ia_resetea_el;
        $vencido = $reseteaEl === null || $reseteaEl->lte(now());

        if (! $vencido) {
            return $user;
        }

        $cupo = $user->cupoIaBase();
        $siguiente = now()->addDay()->startOfDay();

        $query = User::query()->whereKey($user->getKey());

        if ($reseteaEl === null) {
            $query->where(function ($builder) {
                $builder->whereNull('limite_ia_resetea_el')
                    ->orWhere('limite_ia_resetea_el', '<=', now());
            });
        } else {
            $query->where(function ($builder) use ($reseteaEl) {
                $builder->whereNull('limite_ia_resetea_el')
                    ->orWhere('limite_ia_resetea_el', '<=', $reseteaEl)
                    ->orWhere('limite_ia_resetea_el', '<=', now());
            });
        }

        $query->update([
            'intentos_ia_restantes' => $cupo,
            'limite_ia_resetea_el' => $siguiente,
        ]);

        return $user->refresh();
    }

    public function descontarAtomicamente(User $user, int $n): int
    {
        if ($n <= 0) {
            return 0;
        }

        $this->verificarYResetear($user);

        if (! $user->esUsuarioPremium()) {
            return 0;
        }

        if ($this->findAndInc($user, -$n, $n) !== null) {
            $user->refresh();

            return $n;
        }

        $user->refresh();
        $available = max(0, (int) ($user->intentos_ia_restantes ?? 0));

        if ($available <= 0) {
            return 0;
        }

        if ($this->findAndInc($user, -$available, $available) !== null) {
            $user->refresh();

            return $available;
        }

        return 0;
    }

    public function descontarUnoOFallar(User $user): User
    {
        $this->verificarYResetear($user);

        if (! $user->esUsuarioPremium()) {
            throw new CuotaIaAgotadaException(
                optional($user->limite_ia_resetea_el)?->toIso8601String()
            );
        }

        if ($this->findAndInc($user, -1, 1) === null) {
            $user->refresh();

            throw new CuotaIaAgotadaException(
                optional($user->limite_ia_resetea_el)?->toIso8601String()
            );
        }

        return $user->refresh();
    }

    public function reembolsar(User $user, int $n = 1): User
    {
        if ($n <= 0) {
            return $user;
        }

        $this->findAndInc($user, $n, null);
        $user->refresh();

        $cupo = $user->cupoIaBase();
        if ((int) $user->intentos_ia_restantes > $cupo) {
            $user->intentos_ia_restantes = $cupo;
            $user->save();
        }

        return $user->refresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAndInc(User $user, int $delta, ?int $minRemaining): ?array
    {
        $filter = ['_id' => $this->userId($user)];

        if ($minRemaining !== null) {
            $filter['intentos_ia_restantes'] = ['$gte' => $minRemaining];
        }

        $document = User::query()->raw()->findOneAndUpdate(
            $filter,
            ['$inc' => ['intentos_ia_restantes' => $delta]],
            ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );

        return is_array($document) ? $document : ($document !== null ? json_decode(json_encode($document), true) : null);
    }

    private function userId(User $user): ObjectId|string
    {
        try {
            return new ObjectId((string) $user->getKey());
        } catch (\Throwable $e) {
            return (string) $user->getKey();
        }
    }
}
