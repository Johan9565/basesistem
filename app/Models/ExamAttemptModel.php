<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use App\Support\ExamQuestionNormalizer;

class ExamAttemptModel extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_TIMED_OUT = 'timed_out';

    protected $connection = 'mongodb';
    protected $collection = 'exam_attempts';
    protected $table = 'exam_attempts';

    protected $fillable = [
        'user_id',
        'exam_id',
        'exam_name',
        'status',
        'answers',
        'question_ids',
        'questions_snapshot',
        'review',
        'started_at',
        'ends_at',
        'submitted_at',
        'correct_count',
        'total',
        'score',
        'pending_count',
        'graded_total',
        'ai_reviews',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'question_ids' => 'array',
            'questions_snapshot' => 'array',
            'review' => 'array',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'submitted_at' => 'datetime',
            'correct_count' => 'integer',
            'total' => 'integer',
            'score' => 'integer',
            'pending_count' => 'integer',
            'graded_total' => 'integer',
            'ai_reviews' => 'array',
            'feedback' => 'array',
        ];
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_TIMED_OUT], true);
    }

    public function isExpired(): bool
    {
        if ($this->isFinished() || ! $this->ends_at) {
            return false;
        }

        return $this->ends_at->isPast();
    }

    public function remainingSeconds(): int
    {
        if ($this->isFinished() || ! $this->ends_at) {
            return 0;
        }

        return max(0, $this->ends_at->getTimestamp() - now()->getTimestamp());
    }

    public function questionsCollection()
    {
        $legacy = collect($this->questions_snapshot ?? [])
            ->map(fn ($question) => $this->documentToArray($question))
            ->filter(fn ($question) => ($question['id'] ?? '') !== '')
            ->values();

        if ($legacy->isNotEmpty()) {
            return $legacy;
        }

        $ids = collect($this->question_ids ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->values();

        if ($ids->isNotEmpty()) {
            $byId = ExamQuestionModel::query()
                ->whereIn('_id', $this->objectIds($ids->all()))
                ->get()
                ->keyBy(fn (ExamQuestionModel $question) => (string) $question->getKey());

            return $ids
                ->map(fn ($id) => $byId->get($id))
                ->filter()
                ->values()
                ->map(fn (ExamQuestionModel $question, int $index) => $question->toNormalizedArray($index));
        }

        if (($this->exam_id ?? '') === '') {
            return collect();
        }

        return ExamQuestionModel::query()
            ->where(function ($query) {
                $oid = null;
                try {
                    $oid = new \MongoDB\BSON\ObjectId((string) $this->exam_id);
                } catch (\Throwable $e) {
                    $oid = (string) $this->exam_id;
                }

                $query->where('examen_id', $oid)
                    ->orWhere('examen_id', (string) $this->exam_id)
                    ->orWhere('exam_id', (string) $this->exam_id);
            })
            ->orderBy('orden')
            ->orderBy('order_index')
            ->get()
            ->values()
            ->map(fn (ExamQuestionModel $question, int $index) => $question->toNormalizedArray($index));
    }

    /**
     * @param  list<string>  $ids
     * @return list<\MongoDB\BSON\ObjectId|string>
     */
    private function objectIds(array $ids): array
    {
        return collect($ids)
            ->map(function ($id) {
                try {
                    return new \MongoDB\BSON\ObjectId((string) $id);
                } catch (\Throwable $e) {
                    return (string) $id;
                }
            })
            ->all();
    }

    public function sanitizedAnswers(array $incoming): array
    {
        $allowed = [];

        foreach ($this->questionsCollection() as $index => $question) {
            $question = ExamQuestionNormalizer::normalize($question, $index);
            $questionId = (string) $question['id'];
            $raw = $incoming[$questionId] ?? null;

            if ($question['type'] === ExamQuestionNormalizer::TYPE_OPEN) {
                if (! is_string($raw)) {
                    continue;
                }
                $text = trim($raw);
                if ($text === '') {
                    continue;
                }
                $allowed[$questionId] = mb_substr($text, 0, 4000);
                continue;
            }

            $optionIds = collect($question['options'] ?? [])
                ->map(fn ($option) => (string) ($option['id'] ?? ''))
                ->filter(fn ($id) => $id !== '')
                ->all();

            if ($question['type'] === ExamQuestionNormalizer::TYPE_MULTIPLE) {
                $rawSelected = is_array($raw) ? $raw : ExamQuestionNormalizer::splitList($raw);
                $selected = collect($rawSelected)
                    ->map(fn ($id) => strtolower(trim((string) $id)))
                    ->filter(fn ($id) => in_array($id, $optionIds, true))
                    ->unique()
                    ->values();

                if ($selected->isEmpty()) {
                    continue;
                }

                $allowed[$questionId] = $selected->all();
                continue;
            }

            $selected = is_string($raw) || is_numeric($raw)
                ? strtolower(trim((string) $raw))
                : '';
            if ($selected === '' || ! in_array($selected, $optionIds, true)) {
                continue;
            }

            $allowed[$questionId] = $selected;
        }

        return $allowed;
    }

    public function questionsForTaking(): array
    {
        return $this->questionsCollection()
            ->values()
            ->map(function (array $question, int $index) {
                $question = ExamQuestionNormalizer::normalize($question, $index);

                return [
                    'id' => $question['id'],
                    'type' => $question['type'],
                    'prompt' => $question['prompt'],
                    'subject' => $question['subject'],
                    'options' => $question['options'],
                ];
            })
            ->all();
    }

    public function finalize(string $status = self::STATUS_SUBMITTED, array $aiReviews = []): void
    {
        if ($this->isFinished()) {
            return;
        }

        $answers = $this->answers ?? [];
        $storedAi = $this->documentToArray($this->ai_reviews ?? []);
        $aiReviews = array_replace($storedAi, $aiReviews);
        $review = [];
        $correct = 0;
        $pending = 0;
        $graded = 0;
        $nivelSum = 0;

        foreach ($this->questionsCollection()->values() as $index => $question) {
            $question = ExamQuestionNormalizer::normalize($question, $index);
            $questionId = $question['id'];
            $given = $answers[$questionId] ?? null;
            $ai = $this->documentToArray($aiReviews[$questionId] ?? []);
            $result = $this->gradeQuestion($question, $given, $ai);

            if ($result['needs_review']) {
                $pending++;
            } else {
                $graded++;
                $nivelSum += (int) ($result['nivel_acierto'] ?? ($result['is_correct'] ? 100 : 0));
                if ($result['is_correct']) {
                    $correct++;
                }
            }

            $review[] = $this->reviewRow($question, $given, $result);
        }

        $total = $this->questionsCollection()->count();

        $this->update([
            'status' => $status,
            'submitted_at' => now(),
            'correct_count' => $correct,
            'pending_count' => $pending,
            'graded_total' => $graded,
            'total' => $total,
            'score' => $graded > 0 ? (int) round($nivelSum / $graded) : 0,
            'review' => $review,
            'ai_reviews' => $aiReviews,
        ]);
    }

    /**
     * @param  array<string, mixed>  $evaluation
     */
    public function applyAiEvaluation(string $questionId, array $evaluation): void
    {
        $aiReviews = $this->documentToArray($this->ai_reviews ?? []);
        $aiReviews[$questionId] = $evaluation;
        $this->ai_reviews = $aiReviews;
        $this->save();

        if (! $this->isFinished()) {
            return;
        }

        $answers = $this->answers ?? [];
        $review = [];
        $correct = 0;
        $pending = 0;
        $graded = 0;
        $nivelSum = 0;

        foreach ($this->questionsCollection()->values() as $index => $question) {
            $question = ExamQuestionNormalizer::normalize($question, $index);
            $qid = $question['id'];
            $given = $answers[$qid] ?? null;
            $ai = $this->documentToArray($aiReviews[$qid] ?? []);
            $result = $this->gradeQuestion($question, $given, $ai);

            if ($result['needs_review']) {
                $pending++;
            } else {
                $graded++;
                $nivelSum += (int) ($result['nivel_acierto'] ?? ($result['is_correct'] ? 100 : 0));
                if ($result['is_correct']) {
                    $correct++;
                }
            }

            $review[] = $this->reviewRow($question, $given, $result);
        }

        $this->update([
            'correct_count' => $correct,
            'pending_count' => $pending,
            'graded_total' => $graded,
            'total' => $this->questionsCollection()->count(),
            'score' => $graded > 0 ? (int) round($nivelSum / $graded) : 0,
            'review' => $review,
            'ai_reviews' => $aiReviews,
        ]);
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergeAnswersKeepingRevealed(array $incoming): array
    {
        $sanitized = $this->sanitizedAnswers($incoming);
        $current = $this->answers ?? [];
        $revealedIds = array_map('strval', array_keys($this->documentToArray($this->feedback ?? [])));

        foreach ($revealedIds as $questionId) {
            if (array_key_exists($questionId, $current)) {
                $sanitized[$questionId] = $current[$questionId];
            } else {
                unset($sanitized[$questionId]);
            }
        }

        return $sanitized;
    }

    public function isQuestionRevealed(string $questionId): bool
    {
        return array_key_exists($questionId, $this->documentToArray($this->feedback ?? []));
    }

    public function revealQuestion(string $questionId): void
    {
        $questionId = (string) $questionId;
        $feedback = $this->documentToArray($this->feedback ?? []);

        if (array_key_exists($questionId, $feedback)) {
            return;
        }

        $normalized = null;
        foreach ($this->questionsCollection()->values() as $index => $question) {
            $question = ExamQuestionNormalizer::normalize($question, $index);
            if ((string) $question['id'] === $questionId) {
                $normalized = $question;
                break;
            }
        }

        if ($normalized === null) {
            throw new \InvalidArgumentException('La pregunta no pertenece a este intento.');
        }

        $given = ($this->answers ?? [])[$questionId] ?? null;
        $empty = $given === null
            || $given === ''
            || (is_array($given) && $given === []);

        if ($empty) {
            throw new \InvalidArgumentException('Contesta la pregunta antes de continuar.');
        }

        $ai = $this->documentToArray(($this->ai_reviews ?? [])[$questionId] ?? []);
        $result = $this->gradeQuestion($normalized, $given, $ai);

        $feedback[$questionId] = [
            'is_correct' => $result['is_correct'],
            'needs_review' => $result['needs_review'],
            'estado' => $result['estado'],
            'nivel_acierto' => (int) ($result['nivel_acierto'] ?? 0),
            'criterios_cumplidos' => $result['criterios_cumplidos'] ?? [],
            'criterios_omitidos' => $result['criterios_omitidos'] ?? [],
            'explanation' => trim((string) ($result['retroalimentacion'] ?? '')) !== ''
                ? (string) $result['retroalimentacion']
                : $this->reviewExplanation($normalized, $result),
            'correct_option_id' => $normalized['correct_option_id'] ?: null,
            'correct_option_ids' => $normalized['correct_option_ids'] ?? [],
            'respuesta_correcta' => $normalized['respuesta_correcta'] ?? '',
        ];

        $this->update(['feedback' => $feedback]);
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $result
     */
    private function reviewExplanation(array $question, array $result): string
    {
        $modelo = trim((string) ($question['respuesta_modelo'] ?? $question['explanation'] ?? ''));
        if ($modelo !== '') {
            return $modelo;
        }

        $correct = trim((string) ($question['respuesta_correcta'] ?? ''));

        if ($result['needs_review']) {
            return 'Esta pregunta queda pendiente de revisión.';
        }

        if ($result['is_correct']) {
            return $correct !== ''
                ? 'Está bien: coincide con «'.$correct.'».'
                : 'Tu respuesta es correcta.';
        }

        return $correct !== ''
            ? 'Está mal. Lo correcto es: '.$correct.'.'
            : 'Tu respuesta no coincide con la esperada.';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function feedbackForTaking(bool $premium): array
    {
        $out = [];

        foreach ($this->documentToArray($this->feedback ?? []) as $questionId => $row) {
            $row = $this->documentToArray($row);
            $estado = $row['estado'] ?? null;
            if (! is_string($estado) || $estado === '') {
                $estado = ($row['needs_review'] ?? false)
                    ? null
                    : (($row['is_correct'] ?? false) ? 'correcto' : 'incorrecto');
            }

            $item = [
                'is_correct' => (bool) ($row['is_correct'] ?? false),
                'needs_review' => (bool) ($row['needs_review'] ?? false),
                'estado' => $estado,
                'nivel_acierto' => max(0, min(100, (int) ($row['nivel_acierto'] ?? 0))),
            ];

            if ($premium) {
                $item['explanation'] = (string) ($row['explanation'] ?? '');
                $item['correct_option_id'] = $row['correct_option_id'] ?? null;
                $item['correct_option_ids'] = array_values($row['correct_option_ids'] ?? []);
                $item['respuesta_correcta'] = (string) ($row['respuesta_correcta'] ?? '');
                $item['criterios_cumplidos'] = array_values($row['criterios_cumplidos'] ?? []);
                $item['criterios_omitidos'] = array_values($row['criterios_omitidos'] ?? []);
            }

            $out[(string) $questionId] = $item;
        }

        return $out;
    }

    public function toTakeArray(?User $user = null): array
    {
        return [
            'id' => (string) $this->getKey(),
            'status' => (string) $this->status,
            'answers' => (object) ($this->answers ?? []),
            'feedback' => (object) $this->feedbackForTaking((bool) $user?->esUsuarioPremium()),
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'ends_at' => optional($this->ends_at)?->toIso8601String(),
            'remaining_seconds' => $this->remainingSeconds(),
        ];
    }

    public function toHistoryArray(): array
    {
        return [
            'id' => (string) $this->getKey(),
            'status' => (string) $this->status,
            'score' => $this->score,
            'correct_count' => $this->correct_count,
            'pending_count' => $this->pending_count,
            'graded_total' => $this->graded_total,
            'total' => $this->total,
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'submitted_at' => optional($this->submitted_at)?->toIso8601String(),
        ];
    }

    public function toResultArray(): array
    {
        $answers = $this->answers ?? [];
        $reviewById = collect($this->review ?? [])
            ->map(fn ($row) => $this->documentToArray($row))
            ->keyBy('question_id');

        $questions = $this->questionsCollection()
            ->values()
            ->map(function (array $question, int $index) use ($answers, $reviewById) {
                $question = ExamQuestionNormalizer::normalize($question, $index);
                $questionId = $question['id'];
                $row = $this->documentToArray($reviewById->get($questionId, []));
                $given = $answers[$questionId] ?? null;

                return [
                    'id' => $questionId,
                    'type' => $question['type'],
                    'prompt' => $question['prompt'],
                    'subject' => $question['subject'],
                    'explanation' => $question['explanation'],
                    'respuesta_correcta' => $question['respuesta_correcta'],
                    'accepted_answers' => $question['accepted_answers'],
                    'keywords' => $question['keywords'],
                    'correct_option_id' => $question['correct_option_id'],
                    'correct_option_ids' => $question['correct_option_ids'],
                    'selected_option_id' => $row['selected_option_id'] ?? (is_string($given) ? $given : null),
                    'selected_option_ids' => $row['selected_option_ids'] ?? (is_array($given) ? array_values($given) : []),
                    'text_answer' => $row['text_answer'] ?? (is_string($given) && $question['type'] === ExamQuestionNormalizer::TYPE_OPEN ? $given : null),
                    'is_correct' => array_key_exists('is_correct', $row) ? $row['is_correct'] : false,
                    'needs_review' => (bool) ($row['needs_review'] ?? false),
                    'estado' => $row['estado'] ?? null,
                    'nivel_acierto' => max(0, min(100, (int) ($row['nivel_acierto'] ?? 0))),
                    'criterios_cumplidos' => array_values($row['criterios_cumplidos'] ?? []),
                    'criterios_omitidos' => array_values($row['criterios_omitidos'] ?? []),
                    'retroalimentacion' => (string) ($row['retroalimentacion'] ?? ''),
                    'options' => $question['options'],
                ];
            })
            ->all();

        return [
            'id' => (string) $this->getKey(),
            'status' => (string) $this->status,
            'score' => (int) ($this->score ?? 0),
            'correct_count' => (int) ($this->correct_count ?? 0),
            'pending_count' => (int) ($this->pending_count ?? 0),
            'graded_total' => (int) ($this->graded_total ?? 0),
            'total' => (int) ($this->total ?? count($questions)),
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'submitted_at' => optional($this->submitted_at)?->toIso8601String(),
            'questions' => $questions,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $ai
     * @return array{is_correct: bool|null, needs_review: bool, estado: ?string, nivel_acierto: int, criterios_cumplidos: list<string>, criterios_omitidos: list<string>, retroalimentacion: string}
     */
    private function gradeQuestion(array $question, mixed $given, array $ai): array
    {
        $estado = strtolower(trim((string) ($ai['estado'] ?? '')));

        if (in_array($estado, ['correcto', 'parcial', 'incorrecto'], true)) {
            $cumplidos = $this->stringList($ai['criterios_cumplidos'] ?? []);
            $omitidos = $this->stringList($ai['criterios_omitidos'] ?? []);
            $nivel = $ai['nivel_acierto'] ?? $ai['nivel'] ?? null;
            if (! is_numeric($nivel)) {
                $den = count($cumplidos) + count($omitidos);
                $nivel = $den > 0
                    ? (int) round((count($cumplidos) / $den) * 100)
                    : match ($estado) {
                        'correcto' => 100,
                        'parcial' => 50,
                        default => 0,
                    };
            }

            return [
                'is_correct' => $estado === 'correcto',
                'needs_review' => false,
                'estado' => $estado,
                'nivel_acierto' => max(0, min(100, (int) round((float) $nivel))),
                'criterios_cumplidos' => $cumplidos,
                'criterios_omitidos' => $omitidos,
                'retroalimentacion' => (string) ($ai['retroalimentacion'] ?? ''),
            ];
        }

        $result = ExamQuestionNormalizer::grade($question, $given);
        $estadoFallback = null;
        if (! $result['needs_review']) {
            $nivel = (int) ($result['nivel_acierto'] ?? 0);
            $estadoFallback = match (true) {
                $result['is_correct'] => 'correcto',
                $nivel > 0 => 'parcial',
                default => 'incorrecto',
            };
        }

        return [
            'is_correct' => $result['is_correct'],
            'needs_review' => $result['needs_review'],
            'estado' => $estadoFallback,
            'nivel_acierto' => (int) ($result['nivel_acierto'] ?? 0),
            'criterios_cumplidos' => [],
            'criterios_omitidos' => [],
            'retroalimentacion' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function reviewRow(array $question, mixed $given, array $result): array
    {
        return [
            'question_id' => $question['id'],
            'type' => $question['type'],
            'selected_option_id' => is_string($given) ? $given : null,
            'selected_option_ids' => is_array($given) ? array_values($given) : [],
            'text_answer' => is_string($given) && $question['type'] === ExamQuestionNormalizer::TYPE_OPEN ? $given : null,
            'correct_option_id' => $question['correct_option_id'],
            'correct_option_ids' => $question['correct_option_ids'],
            'is_correct' => $result['is_correct'],
            'needs_review' => $result['needs_review'],
            'estado' => $result['estado'] ?? null,
            'nivel_acierto' => (int) ($result['nivel_acierto'] ?? 0),
            'criterios_cumplidos' => $result['criterios_cumplidos'] ?? [],
            'criterios_omitidos' => $result['criterios_omitidos'] ?? [],
            'retroalimentacion' => $result['retroalimentacion'] ?? '',
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();
    }

    private function documentToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }

        return [];
    }
}
