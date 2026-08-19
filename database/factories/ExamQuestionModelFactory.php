<?php

namespace Database\Factories;

use App\Models\ExamQuestionModel;
use MongoDB\BSON\ObjectId;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamQuestionModel>
 */
class ExamQuestionModelFactory extends \Illuminate\Database\Eloquent\Factories\Factory
{
    protected $model = ExamQuestionModel::class;

    public function definition(): array
    {
        return [
            'examen_id' => new ObjectId(),
            'orden' => 1,
            'materia' => 'Amparo',
            'tipo' => 'opcion_unica',
            'pregunta' => '¿Cuál es la respuesta correcta?',
            'opciones' => [
                'Correcta',
                'Incorrecta',
            ],
            'correctas' => [0],
            'respuesta_correcta' => 'Correcta',
            'respuesta_modelo' => 'La primera opción es la correcta.',
            'criterios' => [],
        ];
    }
}
