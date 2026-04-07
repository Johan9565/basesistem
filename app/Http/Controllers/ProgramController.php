<?php

namespace App\Http\Controllers;

use App\Models\MirModel;
use App\Services\Mir\MirDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProgramController extends Controller
{
    public function __construct(
        private MirDocumentService $mir,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Mir/Programs/Index', [
            'programs' => $this->mir->listProgramSummaries(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Mir/Programs/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:32',
            'nombre' => 'required|string|max:2000',
            'finalidad' => 'nullable|string|max:500',
            'funcion' => 'nullable|string|max:500',
            'subfuncion' => 'nullable|string|max:500',
            'unidad_responsable' => 'nullable|string|max:500',
            'unidad_administrativa' => 'nullable|string|max:500',
            'actividad_institucional' => 'nullable|string|max:500',
        ]);

        $clave = trim($validated['clave']);
        if ($this->mir->programaClaveExists($clave)) {
            return back()
                ->withErrors(['clave' => 'Ya existe un programa con esta clave.'])
                ->withInput();
        }

        $doc = MirModel::create([
            'programa_presupuestario' => [
                'clave' => $clave,
                'nombre' => $validated['nombre'],
                'clasificacion_funcional' => [
                    'finalidad' => $validated['finalidad'] ?? '',
                    'funcion' => $validated['funcion'] ?? '',
                    'subfuncion' => $validated['subfuncion'] ?? '',
                ],
                'estructura_administrativa' => [
                    'unidad_responsable' => $validated['unidad_responsable'] ?? '',
                    'unidad_administrativa' => $validated['unidad_administrativa'] ?? '',
                    'actividad_institucional' => $validated['actividad_institucional'] ?? '',
                ],
                'matriz_indicadores' => [],
            ],
        ]);

        return redirect()
            ->route('programs.show', ['program' => (string) $doc->getKey()])
            ->with('flash', ['type' => 'success', 'message' => 'Programa creado. Agrega indicadores desde la matriz MIR.']);
    }

    public function show(string $program): Response
    {
        $doc = $this->mir->findDocument($program);
        if (!$doc) {
            throw new NotFoundHttpException;
        }

        $pp = $doc->programa_presupuestario ?? [];
        if (!is_array($pp)) {
            $pp = [];
        }

        $matriz = $pp['matriz_indicadores'] ?? [];
        $finObjetivo = null;
        $finNombre = null;
        if (is_array($matriz)) {
            foreach ($matriz as $row) {
                if (is_array($row) && ($row['nivel'] ?? '') === 'Fin') {
                    $finObjetivo = $row['objetivo'] ?? null;
                    $finNombre = $row['nombre'] ?? null;
                    break;
                }
            }
        }

        $estructura = $pp['estructura_administrativa'] ?? [];
        $clasificacion = $pp['clasificacion_funcional'] ?? [];

        $year = (int) date('Y');
        $globalProgress = $this->mir->globalFinProgressPercent($doc, $year);

        return Inertia::render('Mir/Programs/Show', [
            'program' => [
                'id' => (string) $doc->getKey(),
                'clave' => (string) ($pp['clave'] ?? ''),
                'nombre' => (string) ($pp['nombre'] ?? ''),
                'clasificacion_funcional' => is_array($clasificacion) ? $clasificacion : [],
                'estructura_administrativa' => is_array($estructura) ? $estructura : [],
                'fin_objetivo' => $finObjetivo,
                'fin_indicador_nombre' => $finNombre,
            ],
            'global_progress_percent' => $globalProgress,
            'global_progress_year' => $year,
        ]);
    }
}
