<?php

namespace App\Http\Controllers;

use App\Models\ExamModel;
use App\Support\ExamQuestionImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Exams/Import');
    }

    public function template(): StreamedResponse
    {
        $csv = ExamQuestionImporter::templateCsv();

        return response()->streamDownload(function () use ($csv) {
            echo "\xEF\xBB\xBF".$csv;
        }, 'template-examen-preguntas.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(Request $request, ExamQuestionImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'materia' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'is_public' => ['nullable', 'boolean'],
            'acceso' => ['nullable', 'in:gratis,prueba,premium'],
            'tipo' => ['nullable', 'in:normal,repaso'],
            'preguntas_por_materia' => ['nullable', 'integer', 'min:0', 'max:200'],
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Sube un CSV (en Excel: Guardar como → CSV UTF-8).',
            ]);
        }

        try {
            $parsed = $importer->parseFile($file->getRealPath());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'file' => $e->getMessage(),
            ]);
        }

        if ($parsed['errors'] !== []) {
            throw ValidationException::withMessages([
                'file' => implode(' ', array_slice($parsed['errors'], 0, 5)),
            ]);
        }

        $name = trim($validated['name']);
        $baseSlug = Str::slug($name) ?: 'examen';
        $slug = $baseSlug;
        $i = 2;
        while (ExamModel::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        $materia = trim($validated['materia']);

        $exam = ExamModel::create([
            'titulo' => $name,
            'slug' => $slug,
            'descripcion' => trim((string) ($validated['description'] ?? '')),
            'emoji' => $validated['emoji'] ?: '📘',
            'tone' => 'primary',
            'materias' => $materia !== '' ? [$materia] : [],
            'total_preguntas' => count($parsed['questions']),
            'duracion_minutos' => (int) $validated['duration_minutes'],
            'preguntas_por_materia' => (int) ($validated['preguntas_por_materia'] ?? 0),
            'es_publico' => $request->boolean('is_public', true),
            'acceso' => $validated['acceso'] ?? 'gratis',
            'tipo' => $validated['tipo'] ?? ExamModel::TIPO_NORMAL,
            'status' => 1,
            'order_index' => (int) (ExamModel::max('order_index') ?? 0) + 1,
        ]);

        $exam->replaceQuestions($parsed['questions']);

        return redirect()
            ->route('exams.show', $exam->getKey())
            ->with('flash', [
                'type' => 'success',
                'message' => 'Se cargaron '.count($parsed['questions']).' preguntas.',
            ]);
    }
}
