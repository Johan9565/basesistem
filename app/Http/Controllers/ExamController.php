<?php

namespace App\Http\Controllers;

use App\Models\ExamAttemptModel;
use App\Models\ExamModel;
use App\Support\MongoModelFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function show(Request $request, string $exam): Response
    {
        $model = MongoModelFinder::findOrFail(ExamModel::class, $exam);
        $canDelete = $request->user()->hasPermission('exams.delete');
        $canAddQuestions = $request->user()->hasPermission('exams.import');
        $canManage = $canDelete || $canAddQuestions;

        abort_unless($canManage || $model->isAccessibleBy($request->user()), 403);

        $userId = (string) $request->user()->getKey();
        $examId = (string) $model->getKey();

        $currentAttempt = ExamAttemptModel::query()
            ->where('user_id', $userId)
            ->where('exam_id', $examId)
            ->where('status', ExamAttemptModel::STATUS_IN_PROGRESS)
            ->orderByDesc('started_at')
            ->first();

        if ($currentAttempt && $currentAttempt->isExpired()) {
            $currentAttempt->finalize(ExamAttemptModel::STATUS_TIMED_OUT);
            $currentAttempt = null;
        }

        $attempts = ExamAttemptModel::query()
            ->where('user_id', $userId)
            ->where('exam_id', $examId)
            ->whereIn('status', [
                ExamAttemptModel::STATUS_SUBMITTED,
                ExamAttemptModel::STATUS_TIMED_OUT,
            ])
            ->orderByDesc('submitted_at')
            ->limit(10)
            ->get()
            ->map(fn (ExamAttemptModel $attempt) => $attempt->toHistoryArray())
            ->values()
            ->all();

        return Inertia::render('Exams/Show', [
            'exam' => $model->toCardArray($request->user()),
            'current_attempt_id' => $currentAttempt ? (string) $currentAttempt->getKey() : null,
            'attempts' => $attempts,
            'requiere_anuncio' => (bool) $request->session()->pull('requiere_anuncio', false),
            'can_delete_exam' => $canDelete,
            'can_add_questions' => $canAddQuestions,
        ]);
    }

    public function destroy(string $exam): RedirectResponse
    {
        $model = MongoModelFinder::findOrFail(ExamModel::class, $exam);
        $name = $model->name;
        $model->deleteCascade();

        return redirect()
            ->route('dashboard')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Se borró el examen «'.$name.'» y sus preguntas.',
            ]);
    }

    public function update(Request $request, string $exam): RedirectResponse
    {
        $model = MongoModelFinder::findOrFail(ExamModel::class, $exam);

        $validated = $request->validate([
            'preguntas_por_materia' => ['required', 'integer', 'min:0', 'max:200'],
        ]);

        $model->preguntas_por_materia = (int) $validated['preguntas_por_materia'];
        $model->save();

        $n = (int) $validated['preguntas_por_materia'];
        $message = $n > 0
            ? "Cada intento tomará {$n} pregunta(s) al azar por materia."
            : 'Cada intento usará todas las preguntas del banco.';

        return redirect()
            ->route('exams.show', $model->getKey())
            ->with('flash', [
                'type' => 'success',
                'message' => $message,
            ]);
    }
}
