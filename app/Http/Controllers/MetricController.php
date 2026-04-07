<?php

namespace App\Http\Controllers;

use App\Services\Mir\MirDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MetricController extends Controller
{
    private const YEAR_MIN = 2024;

    private const YEAR_MAX = 2035;

    public function __construct(
        private MirDocumentService $mir,
    ) {}

    /**
     * Agrega un ejercicio fiscal vacío (meta anual y T1–T4) a todos los indicadores que aún no lo tengan.
     */
    public function storeYear(Request $request, string $program): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:' . self::YEAR_MIN . '|max:' . self::YEAR_MAX,
        ]);

        $year = (int) $validated['year'];
        $doc = $this->requireDocument($program);
        $pp = $this->programaArray($doc);
        $matriz = $pp['matriz_indicadores'] ?? [];
        if (!is_array($matriz)) {
            $matriz = [];
        }

        if ($matriz === []) {
            return back()
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'No hay indicadores en el programa. Agrega indicadores antes de definir metas por ejercicio.',
                ]);
        }

        $added = 0;
        foreach ($matriz as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($this->indicatorHasMetaYear($row, $year)) {
                continue;
            }
            $matriz[$idx]['metas'] = $this->upsertMetaYear(
                is_array($matriz[$idx]['metas'] ?? null) ? $matriz[$idx]['metas'] : [],
                $year,
                null,
                [null, null, null, null],
            );
            $added++;
        }

        $pp['matriz_indicadores'] = $matriz;
        $doc->programa_presupuestario = $pp;
        if ($added > 0) {
            $doc->save();
        }

        $msg = $added > 0
            ? "Ejercicio {$year} agregado en {$added} indicador(es)."
            : "Todos los indicadores ya tenían metas para {$year}.";

        return redirect()
            ->route('metrics.targets', ['program' => (string) $doc->getKey(), 'year' => $year])
            ->with('flash', ['type' => 'success', 'message' => $msg]);
    }

    public function editTargets(string $program, int $year): Response
    {
        $this->assertYear($year);
        $doc = $this->requireDocument($program);
        $pp = $this->programaArray($doc);
        $matriz = $pp['matriz_indicadores'] ?? [];

        $rows = [];
        foreach ($matriz as $row) {
            if (!is_array($row)) {
                continue;
            }
            $meta = $this->metaForYear($row, $year);
            $trim = is_array($meta['trimestres'] ?? null) ? $meta['trimestres'] : [null, null, null, null];
            $rows[] = [
                'codigo' => trim((string) ($row['codigo'] ?? '')),
                'nombre' => (string) ($row['nombre'] ?? ''),
                'nivel' => (string) ($row['nivel'] ?? ''),
                'anual' => $meta['anual'],
                't1' => $trim[0] ?? null,
                't2' => $trim[1] ?? null,
                't3' => $trim[2] ?? null,
                't4' => $trim[3] ?? null,
            ];
        }

        return Inertia::render('Mir/Metrics/Targets', [
            'program' => $this->programSummary($doc, $pp),
            'year' => $year,
            'allowed_years' => self::allowedYearsList(),
            'rows' => $rows,
        ]);
    }

    public function updateTargets(Request $request, string $program, int $year): \Illuminate\Http\RedirectResponse
    {
        $this->assertYear($year);
        $doc = $this->requireDocument($program);

        $validated = $request->validate([
            'rows' => 'required|array',
            'rows.*.codigo' => 'required|string|max:64',
            'rows.*.anual' => 'nullable',
            'rows.*.t1' => 'nullable',
            'rows.*.t2' => 'nullable',
            'rows.*.t3' => 'nullable',
            'rows.*.t4' => 'nullable',
        ]);

        $pp = $this->programaArray($doc);
        $matriz = $pp['matriz_indicadores'] ?? [];
        if (!is_array($matriz)) {
            $matriz = [];
        }

        foreach ($validated['rows'] as $r) {
            $idx = $this->mir->findIndicatorIndexByCodigo($matriz, $r['codigo']);
            if ($idx === null) {
                continue;
            }
            $matriz[$idx]['metas'] = $this->upsertMetaYear(
                is_array($matriz[$idx]['metas'] ?? null) ? $matriz[$idx]['metas'] : [],
                $year,
                $r['anual'] ?? null,
                [
                    $r['t1'] ?? null,
                    $r['t2'] ?? null,
                    $r['t3'] ?? null,
                    $r['t4'] ?? null,
                ],
            );
        }

        $pp['matriz_indicadores'] = $matriz;
        $doc->programa_presupuestario = $pp;
        $doc->save();

        return redirect()
            ->route('metrics.targets', ['program' => (string) $doc->getKey(), 'year' => $year])
            ->with('flash', ['type' => 'success', 'message' => 'Metas guardadas.']);
    }

    public function editTracking(string $program, int $year): Response
    {
        $this->assertYear($year);
        $doc = $this->requireDocument($program);
        $pp = $this->programaArray($doc);
        $matriz = $pp['matriz_indicadores'] ?? [];

        $key = 'seguimiento_' . $year;
        $rows = [];
        foreach ($matriz as $row) {
            if (!is_array($row)) {
                continue;
            }
            $codigo = trim((string) ($row['codigo'] ?? ''));
            $seg = is_array($row[$key] ?? null) ? $row[$key] : null;
            $programado = is_array($seg['programado'] ?? null) ? $seg['programado'] : null;
            $alcanzado = is_array($seg['alcanzado'] ?? null) ? $seg['alcanzado'] : null;

            if ($programado === null) {
                $programado = $this->programadoFromMeta($row, $year);
            }
            if ($alcanzado === null) {
                $alcanzado = ['t1' => null, 't2' => null, 't3' => null, 't4' => null];
            }

            $semaforos = $this->mir->quarterSemaphores($programado, $alcanzado);

            $rows[] = [
                'codigo' => $codigo,
                'nombre' => (string) ($row['nombre'] ?? ''),
                'nivel' => (string) ($row['nivel'] ?? ''),
                'programado' => [
                    'anual' => $programado['anual'] ?? null,
                    't1' => $programado['t1'] ?? null,
                    't2' => $programado['t2'] ?? null,
                    't3' => $programado['t3'] ?? null,
                    't4' => $programado['t4'] ?? null,
                ],
                'alcanzado' => [
                    't1' => $alcanzado['t1'] ?? null,
                    't2' => $alcanzado['t2'] ?? null,
                    't3' => $alcanzado['t3'] ?? null,
                    't4' => $alcanzado['t4'] ?? null,
                ],
                'semaforos' => $semaforos,
            ];
        }

        return Inertia::render('Mir/Metrics/Tracking', [
            'program' => $this->programSummary($doc, $pp),
            'year' => $year,
            'allowed_years' => self::allowedYearsList(),
            'rows' => $rows,
        ]);
    }

    public function updateTracking(Request $request, string $program): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:' . self::YEAR_MIN . '|max:' . self::YEAR_MAX,
            'rows' => 'required|array',
            'rows.*.codigo' => 'required|string|max:64',
            'rows.*.t1' => 'nullable',
            'rows.*.t2' => 'nullable',
            'rows.*.t3' => 'nullable',
            'rows.*.t4' => 'nullable',
        ]);

        $year = (int) $validated['year'];
        $doc = $this->requireDocument($program);
        $pp = $this->programaArray($doc);
        $matriz = $pp['matriz_indicadores'] ?? [];
        if (!is_array($matriz)) {
            $matriz = [];
        }

        $key = 'seguimiento_' . $year;

        foreach ($validated['rows'] as $r) {
            $idx = $this->mir->findIndicatorIndexByCodigo($matriz, $r['codigo']);
            if ($idx === null) {
                continue;
            }
            $row = $matriz[$idx];
            $existing = is_array($row[$key] ?? null) ? $row[$key] : [];
            $programado = is_array($existing['programado'] ?? null)
                ? $existing['programado']
                : $this->programadoFromMeta($row, $year);
            if ($programado === null) {
                $programado = [
                    'anual' => null,
                    't1' => null,
                    't2' => null,
                    't3' => null,
                    't4' => null,
                ];
            }

            $matriz[$idx][$key] = [
                'programado' => $programado,
                'alcanzado' => [
                    't1' => $this->normalizeCell($r['t1'] ?? null),
                    't2' => $this->normalizeCell($r['t2'] ?? null),
                    't3' => $this->normalizeCell($r['t3'] ?? null),
                    't4' => $this->normalizeCell($r['t4'] ?? null),
                ],
            ];
        }

        $pp['matriz_indicadores'] = $matriz;
        $doc->programa_presupuestario = $pp;
        $doc->save();

        return redirect()
            ->route('metrics.tracking', ['program' => (string) $doc->getKey(), 'year' => $year])
            ->with('flash', ['type' => 'success', 'message' => 'Seguimiento guardado.']);
    }

    public function exportCsv(string $program, int $year): StreamedResponse
    {
        $this->assertYear($year);
        $doc = $this->requireDocument($program);
        $pp = $this->programaArray($doc);
        $matriz = $pp['matriz_indicadores'] ?? [];

        $key = 'seguimiento_' . $year;
        $clave = (string) ($pp['clave'] ?? 'programa');
        $clave = preg_replace('/[^\w\-\.]+/', '_', $clave) ?: 'programa';
        $filename = 'mir_' . $clave . '_' . $year . '.csv';

        return response()->streamDownload(function () use ($matriz, $year, $key) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'codigo',
                'nivel',
                'nombre',
                'meta_anual',
                'meta_t1',
                'meta_t2',
                'meta_t3',
                'meta_t4',
                'prog_anual',
                'prog_t1',
                'prog_t2',
                'prog_t3',
                'prog_t4',
                'alc_t1',
                'alc_t2',
                'alc_t3',
                'alc_t4',
            ]);

            foreach ($matriz as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $meta = $this->metaForYear($row, $year);
                $trim = is_array($meta['trimestres'] ?? null) ? $meta['trimestres'] : [];
                $seg = is_array($row[$key] ?? null) ? $row[$key] : [];
                $prog = is_array($seg['programado'] ?? null) ? $seg['programado'] : $this->programadoFromMeta($row, $year);
                $alc = is_array($seg['alcanzado'] ?? null) ? $seg['alcanzado'] : [];

                fputcsv($out, [
                    $row['codigo'] ?? '',
                    $row['nivel'] ?? '',
                    $row['nombre'] ?? '',
                    $meta['anual'],
                    $trim[0] ?? '',
                    $trim[1] ?? '',
                    $trim[2] ?? '',
                    $trim[3] ?? '',
                    $prog['anual'] ?? '',
                    $prog['t1'] ?? '',
                    $prog['t2'] ?? '',
                    $prog['t3'] ?? '',
                    $prog['t4'] ?? '',
                    $alc['t1'] ?? '',
                    $alc['t2'] ?? '',
                    $alc['t3'] ?? '',
                    $alc['t4'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function assertYear(int $year): void
    {
        if ($year < self::YEAR_MIN || $year > self::YEAR_MAX) {
            abort(404);
        }
    }

    /**
     * @return list<int>
     */
    private static function allowedYearsList(): array
    {
        return range(self::YEAR_MIN, self::YEAR_MAX);
    }

    private function indicatorHasMetaYear(array $indicator, int $year): bool
    {
        $metas = $indicator['metas'] ?? [];
        if (!is_array($metas)) {
            return false;
        }
        foreach ($metas as $m) {
            if (is_array($m) && (int) ($m['anio'] ?? 0) === $year) {
                return true;
            }
        }

        return false;
    }

    private function requireDocument(string $program): \App\Models\MirModel
    {
        $doc = $this->mir->findDocument($program);
        if (!$doc) {
            throw new NotFoundHttpException;
        }

        return $doc;
    }

    /**
     * @return array<string, mixed>
     */
    private function programaArray(\App\Models\MirModel $doc): array
    {
        $pp = $doc->programa_presupuestario ?? [];

        return is_array($pp) ? $pp : [];
    }

    /**
     * @param  array<string, mixed>  $pp
     * @return array{id: string, clave: string, nombre: string}
     */
    private function programSummary(\App\Models\MirModel $doc, array $pp): array
    {
        return [
            'id' => (string) $doc->getKey(),
            'clave' => (string) ($pp['clave'] ?? ''),
            'nombre' => (string) ($pp['nombre'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $indicator
     * @return array{anual: mixed, trimestres: array<int, mixed>|null}
     */
    private function metaForYear(array $indicator, int $year): array
    {
        $metas = $indicator['metas'] ?? [];
        if (!is_array($metas)) {
            return ['anual' => null, 'trimestres' => null];
        }
        foreach ($metas as $m) {
            if (!is_array($m)) {
                continue;
            }
            if ((int) ($m['anio'] ?? 0) === $year) {
                return [
                    'anual' => $m['anual'] ?? null,
                    'trimestres' => is_array($m['trimestres'] ?? null) ? $m['trimestres'] : null,
                ];
            }
        }

        return ['anual' => null, 'trimestres' => null];
    }

    /**
     * @param  array<int, mixed>  $metas
     * @return array<int, mixed>
     */
    private function upsertMetaYear(array $metas, int $year, mixed $anual, array $trimestres): array
    {
        $trimStored = [];
        for ($i = 0; $i < 4; $i++) {
            $v = $trimestres[$i] ?? null;
            $trimStored[] = $this->normalizeCell($v);
        }

        foreach ($metas as $j => $m) {
            if (!is_array($m)) {
                continue;
            }
            if ((int) ($m['anio'] ?? 0) === $year) {
                $metas[$j]['anual'] = $this->normalizeCell($anual);
                $metas[$j]['trimestres'] = $trimStored;

                return array_values($metas);
            }
        }

        $metas[] = [
            'anio' => $year,
            'anual' => $this->normalizeCell($anual),
            'trimestres' => $trimStored,
        ];

        return array_values($metas);
    }

    /**
     * @param  array<string, mixed>  $indicator
     * @return array<string, mixed>|null
     */
    private function programadoFromMeta(array $indicator, int $year): ?array
    {
        $meta = $this->metaForYear($indicator, $year);
        $trim = $meta['trimestres'];
        if (!is_array($trim)) {
            $trim = [null, null, null, null];
        }

        return [
            'anual' => $meta['anual'],
            't1' => $trim[0] ?? null,
            't2' => $trim[1] ?? null,
            't3' => $trim[2] ?? null,
            't4' => $trim[3] ?? null,
        ];
    }

    private function normalizeCell(mixed $v): mixed
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return Str::contains((string) $v, '.') ? (float) $v : (int) $v;
        }
        if (is_string($v)) {
            $t = str_replace(',', '.', trim($v));
            if (is_numeric($t)) {
                return Str::contains($t, '.') ? (float) $t : (int) $t;
            }

            return $v;
        }

        return $v;
    }
}
