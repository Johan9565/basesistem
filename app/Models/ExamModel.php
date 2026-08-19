<?php

namespace App\Models;

use App\Support\ExamQuestionNormalizer;
use Database\Factories\ExamModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Eloquent\Model;

class ExamModel extends Model
{
    use HasFactory;

    public const ACCESO_GRATIS = 'gratis';
    public const ACCESO_PRUEBA = 'prueba';
    public const ACCESO_PREMIUM = 'premium';

    public const TIPO_NORMAL = 'normal';
    public const TIPO_REPASO = 'repaso';

    protected $connection = 'mongodb';
    protected $collection = 'exams';
    protected $table = 'exams';

    protected static function newFactory(): ExamModelFactory
    {
        return ExamModelFactory::new();
    }

    protected $fillable = [
        'titulo',
        'descripcion',
        'materias',
        'total_preguntas',
        'duracion_minutos',
        'es_publico',
        'slug',
        'emoji',
        'tone',
        'status',
        'name',
        'description',
        'subjects',
        'question_count',
        'duration_minutes',
        'is_public',
        'order_index',
        'acceso',
        'tipo',
    ];

    protected function casts(): array
    {
        return [
            'total_preguntas' => 'integer',
            'question_count' => 'integer',
            'duracion_minutos' => 'integer',
            'duration_minutes' => 'integer',
            'es_publico' => 'boolean',
            'is_public' => 'boolean',
            'status' => 'integer',
            'order_index' => 'integer',
        ];
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['titulo'] ?? $this->attributes['name'] ?? '');
    }

    public function setNameAttribute(mixed $value): void
    {
        $this->attributes['titulo'] = $value;
    }

    public function getDescriptionAttribute(): string
    {
        return (string) ($this->attributes['descripcion'] ?? $this->attributes['description'] ?? '');
    }

    public function setDescriptionAttribute(mixed $value): void
    {
        $this->attributes['descripcion'] = $value;
    }

    public function getSubjectsAttribute(): array
    {
        return $this->mongoArray($this->attributes['materias'] ?? $this->attributes['subjects'] ?? []);
    }

    public function setSubjectsAttribute(mixed $value): void
    {
        $this->attributes['materias'] = $this->mongoArray($value);
    }

    public function getMateriasAttribute(): array
    {
        return $this->mongoArray($this->attributes['materias'] ?? $this->attributes['subjects'] ?? []);
    }

    public function setMateriasAttribute(mixed $value): void
    {
        $this->attributes['materias'] = $this->mongoArray($value);
    }

    public function getQuestionCountAttribute(): int
    {
        return (int) ($this->attributes['total_preguntas'] ?? $this->attributes['question_count'] ?? 0);
    }

    public function setQuestionCountAttribute(mixed $value): void
    {
        $this->attributes['total_preguntas'] = (int) $value;
    }

    public function getDurationMinutesAttribute(): int
    {
        return (int) ($this->attributes['duracion_minutos'] ?? $this->attributes['duration_minutes'] ?? 0);
    }

    public function setDurationMinutesAttribute(mixed $value): void
    {
        $this->attributes['duracion_minutos'] = (int) $value;
    }

    public function getIsPublicAttribute(): bool
    {
        $value = $this->attributes['es_publico'] ?? $this->attributes['is_public'] ?? true;

        return $value === true || $value === 1 || $value === '1';
    }

    public function setIsPublicAttribute(mixed $value): void
    {
        $this->attributes['es_publico'] = (bool) $value;
    }

    public function questionRecords()
    {
        $oid = $this->objectId();

        return ExamQuestionModel::query()->where(function ($query) use ($oid) {
            $query->where('examen_id', $oid)
                ->orWhere('examen_id', (string) $this->getKey())
                ->orWhere('exam_id', (string) $this->getKey());
        });
    }

    public function accesoTipo(): string
    {
        $acceso = strtolower(trim((string) ($this->acceso ?? self::ACCESO_GRATIS)));

        return match ($acceso) {
            self::ACCESO_PRUEBA => self::ACCESO_PRUEBA,
            self::ACCESO_PREMIUM => self::ACCESO_PREMIUM,
            default => self::ACCESO_GRATIS,
        };
    }

    public function tipoExamen(): string
    {
        $tipo = strtolower(trim((string) ($this->tipo ?? self::TIPO_NORMAL)));

        return $tipo === self::TIPO_REPASO ? self::TIPO_REPASO : self::TIPO_NORMAL;
    }

    public function esRepaso(): bool
    {
        return $this->tipoExamen() === self::TIPO_REPASO;
    }

    public function allowsPlan(User $user): bool
    {
        if ($user->esUsuarioPremium()) {
            return true;
        }

        return in_array($this->accesoTipo(), [self::ACCESO_GRATIS, self::ACCESO_PRUEBA], true);
    }

    public function trialAttemptsLimit(): int
    {
        return max(1, (int) config('ia.examen_prueba_intentos', 3));
    }

    public function trialQuestionLimit(): int
    {
        return max(1, (int) config('ia.examen_prueba_preguntas', 10));
    }

    public function trialAttemptsUsedBy(User $user): int
    {
        return ExamAttemptModel::query()
            ->where('user_id', (string) $user->getKey())
            ->where('exam_id', (string) $this->getKey())
            ->count();
    }

    public function trialAttemptsRemainingFor(User $user): ?int
    {
        if ($this->accesoTipo() !== self::ACCESO_PRUEBA || $user->esUsuarioPremium()) {
            return null;
        }

        return max(0, $this->trialAttemptsLimit() - $this->trialAttemptsUsedBy($user));
    }

    public function questionIdsForAttempt(User $user): array
    {
        $ids = $this->questionIds();

        if ($this->accesoTipo() !== self::ACCESO_PRUEBA) {
            return $ids;
        }

        return array_slice($ids, 0, $this->trialQuestionLimit());
    }

    public static function accessibleTo(User $user)
    {
        $objectIds = collect($user->exam_ids ?? [])
            ->map(function ($id) {
                try {
                    return new ObjectId((string) $id);
                } catch (\Throwable $e) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();

        return static::query()
            ->where(function ($query) {
                $query->where('status', 1)->orWhereNull('status');
            })
            ->where(function ($query) use ($objectIds) {
                $query->where('es_publico', true)
                    ->orWhere('is_public', true);

                if ($objectIds !== []) {
                    $query->orWhereIn('_id', $objectIds);
                }
            })
            ->orderBy('order_index')
            ->orderBy('titulo')
            ->get()
            ->filter(fn (self $exam) => $exam->allowsPlan($user))
            ->values();
    }

    public function isAccessibleBy(User $user): bool
    {
        if (array_key_exists('status', $this->attributes) && (int) ($this->status ?? 1) === 0) {
            return false;
        }

        $hasBaseAccess = $this->is_public
            || collect($user->exam_ids ?? [])
                ->map(fn ($id) => (string) $id)
                ->contains((string) $this->getKey());

        return $hasBaseAccess && $this->allowsPlan($user);
    }

    public function questionsCollection()
    {
        return $this->questionRecords()
            ->orderBy('orden')
            ->get()
            ->values()
            ->map(fn (ExamQuestionModel $question, int $index) => $question->toNormalizedArray($index));
    }

    public function questionCount(): int
    {
        $count = $this->questionRecords()->count();

        return $count > 0 ? $count : (int) ($this->total_preguntas ?? $this->question_count ?? 0);
    }

    public function questionIds(): array
    {
        return $this->questionRecords()
            ->orderBy('orden')
            ->get()
            ->map(fn (ExamQuestionModel $question) => (string) $question->getKey())
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     */
    public function replaceQuestions(array $questions): void
    {
        $examId = (string) $this->getKey();
        $oid = $this->objectId();

        ExamQuestionModel::where('examen_id', $oid)->delete();
        ExamQuestionModel::where('examen_id', $examId)->delete();
        ExamQuestionModel::where('exam_id', $examId)->delete();

        foreach (array_values($questions) as $index => $question) {
            if (! is_array($question)) {
                $question = json_decode(json_encode($question), true) ?: [];
            }

            $storage = ExamQuestionNormalizer::toStorage($question, $index + 1);
            $storage['examen_id'] = $oid;

            ExamQuestionModel::create($storage);
        }

        $this->total_preguntas = count($questions);
        $this->materias = collect($questions)
            ->map(fn ($question) => is_array($question)
                ? ($question['materia'] ?? $question['subject'] ?? '')
                : '')
            ->filter()
            ->unique()
            ->values()
            ->all() ?: ($this->materias ?? $this->subjects ?? []);
        $this->unset(['questions', 'name', 'description', 'subjects', 'question_count', 'duration_minutes', 'is_public']);
        $this->save();
    }

    public function clearEmbeddedQuestions(): void
    {
        $this->unset('questions');
        $this->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function embeddedQuestionsArray(): array
    {
        $embedded = $this->getAttributes()['questions'] ?? null;

        if (is_string($embedded) && $embedded !== '') {
            $decoded = json_decode($embedded, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        $rows = json_decode(json_encode($embedded), true);

        return is_array($rows) ? array_values($rows) : [];
    }

    public function objectId(): ObjectId|string
    {
        try {
            return new ObjectId((string) $this->getKey());
        } catch (\Throwable $e) {
            return (string) $this->getKey();
        }
    }

    /**
     * @return list<mixed>
     */
    private function mongoArray(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? array_values($value) : [];
    }

    public function toCardArray(?User $user = null): array
    {
        $acceso = $this->accesoTipo();
        $questionCount = $this->questionCount();

        if ($acceso === self::ACCESO_PRUEBA) {
            $questionCount = min($questionCount, $this->trialQuestionLimit());
        }

        $card = [
            'id' => (string) $this->getKey(),
            'name' => $this->name,
            'slug' => (string) ($this->slug ?? ''),
            'description' => $this->description,
            'emoji' => (string) ($this->emoji ?? '📘'),
            'tone' => (string) ($this->tone ?? 'primary'),
            'subjects' => collect($this->subjects)->map(fn ($s) => (string) $s)->values()->all(),
            'question_count' => $questionCount,
            'duration_minutes' => $this->duration_minutes,
            'acceso' => $acceso,
            'tipo' => $this->tipoExamen(),
            'intentos_prueba_limite' => $acceso === self::ACCESO_PRUEBA ? $this->trialAttemptsLimit() : null,
            'intentos_prueba_restantes' => $user ? $this->trialAttemptsRemainingFor($user) : null,
        ];

        return $card;
    }
}
