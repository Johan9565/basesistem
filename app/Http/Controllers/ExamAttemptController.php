<?php

namespace App\Http\Controllers;

use App\Models\ExamAttemptModel;
use App\Models\ExamModel;
use App\Models\User;
use App\Services\EvaluarIntentoService;
use App\Support\ExamQuestionNormalizer;
use App\Support\MongoModelFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamAttemptController extends Controller
{
    public function store(Request $request, string $exam): RedirectResponse
    {
        $model = MongoModelFinder::findOrFail(ExamModel::class, $exam);
        $user = $request->user();
        abort_unless($model->isAccessibleBy($user), 403);

        if ($model->questionsCollection()->isEmpty()) {
            return back()->withErrors([
                'exam' => 'Este examen todavía no tiene preguntas.',
            ]);
        }

        $attempt = $this->currentAttempt($user, $model);

        if ($attempt && $attempt->isExpired()) {
            $attempt->finalize(ExamAttemptModel::STATUS_TIMED_OUT);
            $attempt = null;
        }

        if (! $attempt) {
            if ($model->accesoTipo() === ExamModel::ACCESO_PRUEBA && ! $user->esUsuarioPremium()) {
                if ($model->trialAttemptsUsedBy($user) >= $model->trialAttemptsLimit()) {
                    return redirect()
                        ->route('exams.show', (string) $model->getKey())
                        ->withErrors([
                            'exam' => 'Agotaste los '.$model->trialAttemptsLimit().' intentos de este examen de prueba.',
                        ]);
                }

                if (! $request->boolean('anuncio_visto')) {
                    return redirect()
                        ->route('exams.show', (string) $model->getKey())
                        ->with('requiere_anuncio', true)
                        ->withErrors([
                            'anuncio' => 'Mira el anuncio para comenzar el examen de prueba.',
                        ]);
                }
            }

            $minutes = max(1, (int) ($model->duration_minutes ?? 15));
            $questionIds = $model->questionIdsForAttempt($user);

            $attempt = ExamAttemptModel::create([
                'user_id' => (string) $user->getKey(),
                'exam_id' => (string) $model->getKey(),
                'exam_name' => (string) $model->name,
                'status' => ExamAttemptModel::STATUS_IN_PROGRESS,
                'answers' => [],
                'question_ids' => $questionIds,
                'ai_reviews' => [],
                'feedback' => [],
                'started_at' => now(),
                'ends_at' => now()->addMinutes($minutes),
            ]);
        }

        return redirect()->route('exams.attempts.show', [
            'exam' => (string) $model->getKey(),
            'attempt' => (string) $attempt->getKey(),
        ]);
    }

    public function show(Request $request, string $exam, string $attempt): Response|RedirectResponse
    {
        [$model, $attemptModel] = $this->resolveOwnedAttempt($request, $exam, $attempt);

        if ($attemptModel->isFinished()) {
            return redirect()->route('exams.attempts.result', [
                'exam' => (string) $model->getKey(),
                'attempt' => (string) $attemptModel->getKey(),
            ]);
        }

        if ($attemptModel->isExpired()) {
            $attemptModel->finalize(ExamAttemptModel::STATUS_TIMED_OUT);

            return redirect()->route('exams.attempts.result', [
                'exam' => (string) $model->getKey(),
                'attempt' => (string) $attemptModel->getKey(),
            ]);
        }

        return Inertia::render('Exams/Take', [
            'exam' => $model->toCardArray($request->user()),
            'attempt' => $attemptModel->toTakeArray($request->user()),
            'questions' => $attemptModel->questionsForTaking(),
        ]);
    }

    public function update(Request $request, string $exam, string $attempt): RedirectResponse
    {
        [, $attemptModel] = $this->resolveOwnedAttempt($request, $exam, $attempt);

        if ($attemptModel->isFinished()) {
            abort(409, 'El examen ya fue enviado.');
        }

        if ($attemptModel->isExpired()) {
            $attemptModel->finalize(ExamAttemptModel::STATUS_TIMED_OUT);

            return redirect()->route('exams.attempts.result', [
                'exam' => $exam,
                'attempt' => (string) $attemptModel->getKey(),
            ]);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $attemptModel->update([
            'answers' => $attemptModel->mergeAnswersKeepingRevealed($validated['answers']),
        ]);

        return back();
    }

    public function check(Request $request, string $exam, string $attempt, EvaluarIntentoService $evaluarIntento): RedirectResponse
    {
        [$model, $attemptModel] = $this->resolveOwnedAttempt($request, $exam, $attempt);

        abort_unless($model->esRepaso(), 404);

        if ($attemptModel->isFinished()) {
            abort(409, 'El examen ya fue enviado.');
        }

        if ($attemptModel->isExpired()) {
            $attemptModel->finalize(ExamAttemptModel::STATUS_TIMED_OUT);

            return redirect()->route('exams.attempts.result', [
                'exam' => (string) $model->getKey(),
                'attempt' => (string) $attemptModel->getKey(),
            ]);
        }

        $validated = $request->validate([
            'question_id' => ['required', 'string'],
            'answers' => ['required', 'array'],
        ]);

        $attemptModel->answers = $attemptModel->mergeAnswersKeepingRevealed($validated['answers']);
        $attemptModel->save();

        $questionId = (string) $validated['question_id'];
        $normalized = $this->questionFromAttempt($attemptModel, $questionId);
        $given = ($attemptModel->answers ?? [])[$questionId] ?? '';

        if (
            $normalized
            && ($normalized['type'] ?? '') === ExamQuestionNormalizer::TYPE_OPEN
            && is_string($given)
            && trim($given) !== ''
        ) {
            try {
                $reviews = $evaluarIntento->evaluarUna(
                    $request->user(),
                    $normalized,
                    $given,
                    $attemptModel->ai_reviews ?? []
                );
                $attemptModel->ai_reviews = $reviews;
                $attemptModel->save();
            } catch (\Throwable) {
                // Si DeepSeek falla, se califica en local al revelar.
            }
        }

        try {
            $attemptModel->revealQuestion($questionId);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors([
                'question_id' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function submit(Request $request, string $exam, string $attempt, EvaluarIntentoService $evaluarIntento): RedirectResponse
    {
        [$model, $attemptModel] = $this->resolveOwnedAttempt($request, $exam, $attempt);

        if (! $attemptModel->isFinished()) {
            if ($request->has('answers') && is_array($request->input('answers'))) {
                $attemptModel->answers = $attemptModel->mergeAnswersKeepingRevealed($request->input('answers'));
                $attemptModel->save();
            }

            $status = $attemptModel->isExpired()
                ? ExamAttemptModel::STATUS_TIMED_OUT
                : ExamAttemptModel::STATUS_SUBMITTED;

            $questions = $attemptModel->questionsCollection()
                ->values()
                ->map(fn ($question, int $index) => ExamQuestionNormalizer::normalize($question, $index));

            $aiReviews = $evaluarIntento->evaluarAbiertas(
                $request->user(),
                $questions,
                $attemptModel->answers ?? [],
                $attemptModel->ai_reviews ?? []
            );

            $attemptModel->finalize($status, $aiReviews);
        }

        return redirect()->route('exams.attempts.result', [
            'exam' => (string) $model->getKey(),
            'attempt' => (string) $attemptModel->getKey(),
        ]);
    }

    public function result(Request $request, string $exam, string $attempt): Response|RedirectResponse
    {
        [$model, $attemptModel] = $this->resolveOwnedAttempt($request, $exam, $attempt);

        if (! $attemptModel->isFinished()) {
            if ($attemptModel->isExpired()) {
                $attemptModel->finalize(ExamAttemptModel::STATUS_TIMED_OUT);
            } else {
                return redirect()->route('exams.attempts.show', [
                    'exam' => (string) $model->getKey(),
                    'attempt' => (string) $attemptModel->getKey(),
                ]);
            }
        }

        $attempt = $attemptModel->toResultArray();
        $attempt['intentos_ia_restantes'] = (int) ($request->user()->intentos_ia_restantes ?? 0);

        return Inertia::render('Exams/Result', [
            'exam' => $model->toCardArray($request->user()),
            'attempt' => $attempt,
        ]);
    }

    /**
     * @return array{0: ExamModel, 1: ExamAttemptModel}
     */
    private function resolveOwnedAttempt(Request $request, string $exam, string $attempt): array
    {
        $model = MongoModelFinder::findOrFail(ExamModel::class, $exam);
        abort_unless($model->isAccessibleBy($request->user()), 403);

        /** @var ExamAttemptModel $attemptModel */
        $attemptModel = MongoModelFinder::findOrFail(ExamAttemptModel::class, $attempt);

        abort_unless(
            (string) $attemptModel->exam_id === (string) $model->getKey(),
            404
        );
        abort_unless(
            (string) $attemptModel->user_id === (string) $request->user()->getKey(),
            403
        );

        return [$model, $attemptModel];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function questionFromAttempt(ExamAttemptModel $attempt, string $questionId): ?array
    {
        foreach ($attempt->questionsCollection()->values() as $index => $question) {
            $question = ExamQuestionNormalizer::normalize($question, $index);
            if ((string) $question['id'] === $questionId) {
                return $question;
            }
        }

        return null;
    }

    private function currentAttempt(User $user, ExamModel $exam): ?ExamAttemptModel
    {
        return ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $exam->getKey())
            ->where('status', ExamAttemptModel::STATUS_IN_PROGRESS)
            ->orderByDesc('started_at')
            ->first();
    }
}
