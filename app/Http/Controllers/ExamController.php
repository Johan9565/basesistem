<?php

namespace App\Http\Controllers;

use App\Models\ExamAttemptModel;
use App\Models\ExamModel;
use App\Support\MongoModelFinder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function show(Request $request, string $exam): Response
    {
        $model = MongoModelFinder::findOrFail(ExamModel::class, $exam);
        abort_unless($model->isAccessibleBy($request->user()), 403);

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
        ]);
    }
}
