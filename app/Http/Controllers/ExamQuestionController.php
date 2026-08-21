<?php

namespace App\Http\Controllers;

use App\Models\ExamModel;
use App\Support\ExamQuestionNormalizer;
use App\Support\MongoModelFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExamQuestionController extends Controller
{
    public function create(string $exam): Response
    {
        $model = MongoModelFinder::findOrFail(ExamModel::class, $exam);

        return Inertia::render('Exams/AddQuestion', [
            'exam' => $model->toCardArray(),
        ]);
    }

    public function store(Request $request, string $exam): RedirectResponse
    {
        $model = MongoModelFinder::findOrFail(ExamModel::class, $exam);

        $validated = $request->validate([
            'tipo' => ['required', Rule::in([
                ExamQuestionNormalizer::TYPE_SINGLE,
                ExamQuestionNormalizer::TYPE_MULTIPLE,
                ExamQuestionNormalizer::TYPE_OPEN,
            ])],
            'pregunta' => ['required', 'string', 'max:5000'],
            'materia' => ['nullable', 'string', 'max:255'],
            'opciones' => ['nullable', 'array', 'max:6'],
            'opciones.*' => ['nullable', 'string', 'max:1000'],
            'correctas' => ['nullable', 'array'],
            'correctas.*' => ['integer', 'min:0', 'max:5'],
            'respuesta_correcta' => ['nullable', 'string', 'max:5000'],
            'respuesta_modelo' => ['nullable', 'string', 'max:5000'],
            'criterios' => ['nullable', 'string', 'max:2000'],
        ]);

        $tipo = $validated['tipo'];
        $opciones = collect($validated['opciones'] ?? [])
            ->map(fn ($text) => trim((string) $text))
            ->filter(fn ($text) => $text !== '')
            ->values()
            ->all();

        $correctas = array_values(array_unique(array_map('intval', $validated['correctas'] ?? [])));
        $correctas = array_values(array_filter(
            $correctas,
            fn (int $index) => $index >= 0 && $index < count($opciones)
        ));

        if ($tipo !== ExamQuestionNormalizer::TYPE_OPEN) {
            if (count($opciones) < 2) {
                throw ValidationException::withMessages([
                    'opciones' => 'Las preguntas de opción necesitan al menos 2 opciones.',
                ]);
            }

            if ($correctas === []) {
                throw ValidationException::withMessages([
                    'correctas' => 'Marca al menos una opción correcta.',
                ]);
            }

            if ($tipo === ExamQuestionNormalizer::TYPE_SINGLE && count($correctas) !== 1) {
                throw ValidationException::withMessages([
                    'correctas' => 'En opción única debe haber exactamente una respuesta correcta.',
                ]);
            }
        }

        $criterios = ExamQuestionNormalizer::splitList($validated['criterios'] ?? '');

        $model->appendQuestion([
            'tipo' => $tipo,
            'pregunta' => $validated['pregunta'],
            'materia' => $validated['materia'] ?? '',
            'opciones' => $tipo === ExamQuestionNormalizer::TYPE_OPEN ? [] : $opciones,
            'correctas' => $tipo === ExamQuestionNormalizer::TYPE_OPEN ? [] : $correctas,
            'respuesta_correcta' => $validated['respuesta_correcta'] ?? '',
            'respuesta_modelo' => $validated['respuesta_modelo'] ?? '',
            'criterios' => $criterios,
        ]);

        return redirect()
            ->route('exams.show', $model->getKey())
            ->with('flash', [
                'type' => 'success',
                'message' => 'Pregunta agregada al examen.',
            ]);
    }
}
