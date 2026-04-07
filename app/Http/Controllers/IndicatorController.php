<?php

namespace App\Http\Controllers;

use App\Services\Mir\MirDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IndicatorController extends Controller
{
    private const DEFAULT_META_YEARS = [2025, 2026, 2027];

    private const NIVELES = ['Fin', 'Proposito', 'Componente', 'Actividad'];

    public function __construct(
        private MirDocumentService $mir,
    ) {}

    public function create(string $program): Response
    {
        $doc = $this->mir->findDocument($program);
        if (!$doc) {
            throw new NotFoundHttpException;
        }

        $pp = $doc->programa_presupuestario ?? [];
        if (!is_array($pp)) {
            $pp = [];
        }

        return Inertia::render('Mir/Indicators/Create', [
            'program' => [
                'id' => (string) $doc->getKey(),
                'clave' => (string) ($pp['clave'] ?? ''),
                'nombre' => (string) ($pp['nombre'] ?? ''),
            ],
            'niveles' => self::NIVELES,
            'default_meta_years' => self::DEFAULT_META_YEARS,
        ]);
    }

    public function store(Request $request, string $program): RedirectResponse
    {
        $doc = $this->mir->findDocument($program);
        if (!$doc) {
            throw new NotFoundHttpException;
        }

        $validated = $request->validate([
            'nivel' => 'required|string|in:Fin,Proposito,Componente,Actividad',
            'codigo' => 'required|string|max:64',
            'nombre' => 'required|string|max:2000',
            'objetivo' => 'required|string|max:8000',
            'frecuencia' => 'required|string|max:64',
            'definicion' => 'nullable|string',
            'dimension' => 'nullable|string|max:64',
            'unidad_medida' => 'nullable|string',
            'sentido' => 'nullable|string|in:Ascendente,Descendente',
            'linea_base' => 'nullable|string',
            'medios_verificacion' => 'nullable|string',
            'supuestos' => 'nullable|string',
            'metodo_calculo' => 'nullable|string',
            'inicializar_metas' => 'sometimes|boolean',
        ]);

        $pp = $doc->programa_presupuestario ?? [];
        if (!is_array($pp)) {
            $pp = [];
        }
        $matriz = $pp['matriz_indicadores'] ?? [];
        if (!is_array($matriz)) {
            $matriz = [];
        }

        $codigo = trim($validated['codigo']);
        if ($this->mir->findIndicatorIndexByCodigo($matriz, $codigo) !== null) {
            return back()
                ->withErrors(['codigo' => 'Ya existe un indicador con este código en el programa.'])
                ->withInput();
        }

        $mc = $validated['metodo_calculo'] ?? '';
        $nuevo = [
            'nivel' => $validated['nivel'],
            'objetivo' => $validated['objetivo'],
            'nombre' => $validated['nombre'],
            'codigo' => $codigo,
            'definicion' => $validated['definicion'] ?? '',
            'dimension' => $validated['dimension'] ?? 'Eficacia',
            'frecuencia' => $validated['frecuencia'],
            'metodo_calculo' => $mc === '' ? null : $mc,
            'unidad_medida' => $validated['unidad_medida'] ?? '',
            'sentido' => $validated['sentido'] ?? 'Ascendente',
            'linea_base' => $validated['linea_base'] ?? '',
            'medios_verificacion' => $validated['medios_verificacion'] ?? '',
            'supuestos' => $validated['supuestos'] ?? '',
            'metas' => $request->boolean('inicializar_metas')
                ? $this->mir->defaultMetasBlock(self::DEFAULT_META_YEARS)
                : [],
        ];

        $matriz[] = $nuevo;
        $pp['matriz_indicadores'] = array_values($matriz);
        $doc->programa_presupuestario = $pp;
        $doc->save();

        return redirect()
            ->route('indicators.index', ['program' => (string) $doc->getKey()])
            ->with('flash', ['type' => 'success', 'message' => 'Indicador agregado al programa.']);
    }

    public function index(string $program): Response
    {
        $doc = $this->mir->findDocument($program);
        if (!$doc) {
            throw new NotFoundHttpException;
        }

        $pp = $doc->programa_presupuestario ?? [];
        $matriz = is_array($pp) ? ($pp['matriz_indicadores'] ?? []) : [];
        if (!is_array($matriz)) {
            $matriz = [];
        }

        $rows = [];
        foreach ($matriz as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'index' => $i,
                'nivel' => (string) ($row['nivel'] ?? ''),
                'codigo' => trim((string) ($row['codigo'] ?? '')),
                'nombre' => (string) ($row['nombre'] ?? ''),
                'frecuencia' => (string) ($row['frecuencia'] ?? ''),
                'objetivo' => (string) ($row['objetivo'] ?? ''),
            ];
        }

        return Inertia::render('Mir/Indicators/Index', [
            'program' => [
                'id' => (string) $doc->getKey(),
                'clave' => (string) ($pp['clave'] ?? ''),
                'nombre' => (string) ($pp['nombre'] ?? ''),
            ],
            'indicators' => $rows,
            'metric_years' => range(2024, 2035),
        ]);
    }

    public function edit(string $program, string $codigo): Response
    {
        $doc = $this->mir->findDocument($program);
        if (!$doc) {
            throw new NotFoundHttpException;
        }

        $pp = $doc->programa_presupuestario ?? [];
        $matriz = is_array($pp) ? ($pp['matriz_indicadores'] ?? []) : [];
        if (!is_array($matriz)) {
            $matriz = [];
        }

        $idx = $this->mir->findIndicatorIndexByCodigo($matriz, $codigo);
        if ($idx === null) {
            throw new NotFoundHttpException;
        }

        $row = $matriz[$idx];

        return Inertia::render('Mir/Indicators/Edit', [
            'program' => [
                'id' => (string) $doc->getKey(),
                'clave' => (string) ($pp['clave'] ?? ''),
                'nombre' => (string) ($pp['nombre'] ?? ''),
            ],
            'indicator' => [
                'codigo' => trim((string) ($row['codigo'] ?? '')),
                'nombre' => (string) ($row['nombre'] ?? ''),
                'nivel' => (string) ($row['nivel'] ?? ''),
                'definicion' => (string) ($row['definicion'] ?? ''),
                'metodo_calculo' => $row['metodo_calculo'] !== null && $row['metodo_calculo'] !== ''
                    ? (string) $row['metodo_calculo']
                    : '',
                'supuestos' => (string) ($row['supuestos'] ?? ''),
            ],
        ]);
    }

    public function update(Request $request, string $program, string $codigo): RedirectResponse
    {
        $doc = $this->mir->findDocument($program);
        if (!$doc) {
            throw new NotFoundHttpException;
        }

        $validated = $request->validate([
            'definicion' => 'nullable|string',
            'metodo_calculo' => 'nullable|string',
            'supuestos' => 'nullable|string',
        ]);

        $pp = $doc->programa_presupuestario ?? [];
        if (!is_array($pp)) {
            $pp = [];
        }
        $matriz = $pp['matriz_indicadores'] ?? [];
        if (!is_array($matriz)) {
            $matriz = [];
        }

        $idx = $this->mir->findIndicatorIndexByCodigo($matriz, $codigo);
        if ($idx === null) {
            throw new NotFoundHttpException;
        }

        $matriz[$idx]['definicion'] = $validated['definicion'] ?? '';
        $mc = $validated['metodo_calculo'] ?? '';
        $matriz[$idx]['metodo_calculo'] = $mc === '' ? null : $mc;
        $matriz[$idx]['supuestos'] = $validated['supuestos'] ?? '';

        $pp['matriz_indicadores'] = $matriz;
        $doc->programa_presupuestario = $pp;
        $doc->save();

        return redirect()
            ->route('indicators.index', ['program' => (string) $doc->getKey()])
            ->with('flash', ['type' => 'success', 'message' => 'Indicador actualizado.']);
    }
}
