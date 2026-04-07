<?php

namespace App\Services\Mir;

use App\Models\MirModel;
use MongoDB\BSON\ObjectId;

class MirDocumentService
{
    public function normalizeId(string $id): mixed
    {
        return strlen($id) === 24 && ctype_xdigit($id) ? new ObjectId($id) : $id;
    }

    public function findDocument(string $programDocumentId): ?MirModel
    {
        return MirModel::find($this->normalizeId($programDocumentId));
    }

    public function programaClaveExists(string $clave, ?string $exceptDocumentId = null): bool
    {
        $clave = trim($clave);
        if ($clave === '') {
            return false;
        }

        $q = MirModel::query()->where('programa_presupuestario.clave', $clave);
        if ($exceptDocumentId !== null && $exceptDocumentId !== '') {
            $q->where('_id', '!=', $this->normalizeId($exceptDocumentId));
        }

        return $q->exists();
    }

    /**
     * @param  list<int>  $years
     * @return list<array{anio: int, anual: null, trimestres: list{null, null, null, null}}>
     */
    public function defaultMetasBlock(array $years): array
    {
        $out = [];
        foreach ($years as $y) {
            $out[] = [
                'anio' => (int) $y,
                'anual' => null,
                'trimestres' => [null, null, null, null],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, clave: string, nombre: string}>
     */
    public function listProgramSummaries(): array
    {
        return MirModel::query()
            ->get()
            ->map(function (MirModel $doc) {
                $pp = $doc->programa_presupuestario ?? [];
                $clave = (string) ($pp['clave'] ?? '');
                $nombre = (string) ($pp['nombre'] ?? '');

                return [
                    'id' => (string) ($doc->getKey()),
                    'clave' => $clave,
                    'nombre' => $nombre,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $matriz
     */
    public function findIndicatorIndexByCodigo(array $matriz, string $codigo): ?int
    {
        $codigo = trim($codigo);
        foreach ($matriz as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (trim((string) ($row['codigo'] ?? '')) === $codigo) {
                return (int) $i;
            }
        }

        return null;
    }

    public function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $t = str_replace(',', '.', trim($value));
            return is_numeric($t) ? (float) $t : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $programado
     * @param  array<string, mixed>|null  $alcanzado
     * @return array{t1: ?string, t2: ?string, t3: ?string, t4: ?string}
     */
    public function quarterSemaphores(?array $programado, ?array $alcanzado): array
    {
        $out = [];
        foreach (['t1', 't2', 't3', 't4'] as $k) {
            $p = $this->parseNumber($programado[$k] ?? null);
            $a = $this->parseNumber($alcanzado[$k] ?? null);
            $out[$k] = $this->semaphoreLabel($p, $a);
        }

        return $out;
    }

    public function compliancePercent(?float $programado, ?float $alcanzado): ?float
    {
        if ($programado === null || $programado == 0.0 || $alcanzado === null) {
            return null;
        }

        return round(($alcanzado / $programado) * 100, 2);
    }

    public function semaphoreLabel(?float $programado, ?float $alcanzado): ?string
    {
        $pct = $this->compliancePercent($programado, $alcanzado);
        if ($pct === null) {
            return null;
        }
        if ($pct >= 90) {
            return 'green';
        }
        if ($pct >= 70) {
            return 'yellow';
        }

        return 'red';
    }

    /**
     * Avance agregado del indicador de Fin (p. ej. IGOB_HUM_R) para el año dado, usando seguimiento_{year}.
     */
    public function globalFinProgressPercent(MirModel $doc, int $year): ?float
    {
        $pp = $doc->programa_presupuestario ?? [];
        $matriz = $pp['matriz_indicadores'] ?? [];
        if (!is_array($matriz)) {
            return null;
        }

        $fin = null;
        foreach ($matriz as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['nivel'] ?? '') === 'Fin') {
                $fin = $row;
                break;
            }
        }
        if ($fin === null) {
            return null;
        }

        $key = 'seguimiento_' . $year;
        $seg = $fin[$key] ?? null;
        if (!is_array($seg)) {
            return null;
        }
        $prog = $seg['programado'] ?? null;
        $alc = $seg['alcanzado'] ?? null;
        if (!is_array($prog) || !is_array($alc)) {
            return null;
        }

        $sumP = 0.0;
        $sumA = 0.0;
        foreach (['t1', 't2', 't3', 't4'] as $q) {
            $p = $this->parseNumber($prog[$q] ?? null);
            $a = $this->parseNumber($alc[$q] ?? null);
            if ($p !== null && $p > 0 && $a !== null) {
                $sumP += $p;
                $sumA += $a;
            }
        }

        if ($sumP <= 0) {
            return null;
        }

        return round(($sumA / $sumP) * 100, 2);
    }
}
