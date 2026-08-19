<?php

namespace App\Models;

use App\Support\ExamQuestionNormalizer;
use Database\Factories\ExamQuestionModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class ExamQuestionModel extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'questions';
    protected $table = 'questions';

    protected static function newFactory(): ExamQuestionModelFactory
    {
        return ExamQuestionModelFactory::new();
    }

    protected $fillable = [
        'examen_id',
        'orden',
        'materia',
        'tipo',
        'pregunta',
        'opciones',
        'correctas',
        'respuesta_correcta',
        'respuesta_modelo',
        'criterios',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(ExamModel::class, 'examen_id');
    }

    public function toNormalizedArray(?int $index = null): array
    {
        $orden = (int) ($this->orden ?? (($index ?? 0) + 1));

        $normalized = ExamQuestionNormalizer::normalize([
            'id' => (string) $this->getKey(),
            'tipo' => $this->tipo ?? $this->getAttribute('type'),
            'pregunta' => $this->pregunta ?? $this->getAttribute('prompt'),
            'materia' => $this->materia ?? $this->getAttribute('subject'),
            'opciones' => $this->mongoArray($this->opciones ?? $this->getAttribute('options') ?? []),
            'correctas' => $this->mongoArray($this->correctas ?? $this->getAttribute('correct_option_ids') ?? []),
            'respuesta_modelo' => $this->respuesta_modelo ?? $this->getAttribute('explanation') ?? '',
            'respuesta_correcta' => ExamQuestionNormalizer::asText(
                $this->respuesta_correcta ?? $this->getAttribute('correct_answer') ?? ''
            ),
            'criterios' => $this->mongoArray($this->criterios ?? $this->getAttribute('keywords') ?? []),
            'orden' => $orden,
        ], max(0, $orden - 1));

        $normalized['id'] = (string) $this->getKey();

        return $normalized;
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
}
