<?php

namespace Database\Factories;

use App\Models\ExamModel;
use App\Support\ExamQuestionNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamModel>
 */
class ExamModelFactory extends Factory
{
    protected $model = ExamModel::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'titulo' => Str::title($name),
            'slug' => Str::slug($name),
            'descripcion' => fake()->sentence(),
            'emoji' => '📘',
            'tone' => 'primary',
            'materias' => ['Tema 1', 'Tema 2'],
            'total_preguntas' => 2,
            'duracion_minutos' => 10,
            'es_publico' => true,
            'status' => 1,
            'order_index' => 1,
            'acceso' => 'gratis',
            'tipo' => ExamModel::TIPO_NORMAL,
        ];
    }

    public function gratis(): static
    {
        return $this->state(fn () => ['acceso' => 'gratis']);
    }

    public function prueba(): static
    {
        return $this->state(fn () => ['acceso' => 'prueba']);
    }

    public function premiumAccess(): static
    {
        return $this->state(fn () => ['acceso' => 'premium']);
    }

    public function repaso(): static
    {
        return $this->state(fn () => ['tipo' => ExamModel::TIPO_REPASO]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ExamModel $exam) {
            if ($exam->questionRecords()->exists()) {
                return;
            }

            $exam->replaceQuestions($this->defaultQuestions());
        });
    }

    public function withOpenQuestion(): static
    {
        return $this->afterCreating(function (ExamModel $exam) {
            $questions = $this->defaultQuestions();
            $questions[] = ExamQuestionNormalizer::normalize([
                'tipo' => 'abierta',
                'pregunta' => 'Explique el principio de supremacía constitucional.',
                'materia' => 'Tema 1',
                'respuesta_modelo' => 'Todas las normas del orden jurídico deben ajustarse a la Constitución.',
                'criterios' => ['supremacia constitucional', 'normas se ajustan a la constitucion'],
            ], 2);
            $exam->replaceQuestions($questions);
        });
    }

    public function private(): static
    {
        return $this->state(fn () => ['es_publico' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 0]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultQuestions(): array
    {
        return [
            ExamQuestionNormalizer::normalize([
                'tipo' => 'opcion_unica',
                'pregunta' => '¿Cuál es la respuesta correcta de la primera pregunta?',
                'materia' => 'Tema 1',
                'respuesta_modelo' => 'La opción A es la correcta porque coincide con el enunciado.',
                'opciones' => ['Correcta', 'Incorrecta'],
                'correctas' => [0],
                'respuesta_correcta' => 'Correcta',
            ], 0),
            ExamQuestionNormalizer::normalize([
                'tipo' => 'opcion_unica',
                'pregunta' => '¿Cuál es la respuesta correcta de la segunda pregunta?',
                'materia' => 'Tema 2',
                'respuesta_modelo' => 'La opción B es la correcta porque coincide con el enunciado.',
                'opciones' => ['Incorrecta', 'Correcta'],
                'correctas' => [1],
                'respuesta_correcta' => 'Correcta',
            ], 1),
        ];
    }
}
