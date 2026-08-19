<?php

namespace Database\Seeders;

use App\Models\ExamModel;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    public function run(): void
    {
        $exams = [
            [
                'titulo' => 'Constitucional',
                'slug' => 'constitucional',
                'descripcion' => 'Principios, derechos y control constitucional. Bloques cortos para el examen.',
                'emoji' => '⚖️',
                'tone' => 'violet',
                'materias' => ['Principios', 'Derechos', 'Control constitucional'],
                'total_preguntas' => 40,
                'duracion_minutos' => 15,
                'order_index' => 1,
                'acceso' => 'premium',
            ],
            [
                'titulo' => 'Civil',
                'slug' => 'civil',
                'descripcion' => 'Personas, contratos y bienes. Lo esencial de norma y criterio.',
                'emoji' => '🏠',
                'tone' => 'sky',
                'materias' => ['Personas', 'Contratos', 'Bienes'],
                'total_preguntas' => 40,
                'duracion_minutos' => 15,
                'order_index' => 2,
                'acceso' => 'premium',
            ],
            [
                'titulo' => 'Penal',
                'slug' => 'penal',
                'descripcion' => 'Delito, tipos penales y proceso. Casos tipo examen.',
                'emoji' => '🔍',
                'tone' => 'coral',
                'materias' => ['Teoría del delito', 'Tipos penales', 'Proceso penal'],
                'total_preguntas' => 40,
                'duracion_minutos' => 15,
                'order_index' => 3,
                'acceso' => 'premium',
            ],
            [
                'titulo' => 'Administrativo',
                'slug' => 'administrativo',
                'descripcion' => 'Acto, procedimiento y Estado. Criterio y jurisprudencia clave.',
                'emoji' => '🏛️',
                'tone' => 'mint',
                'materias' => ['Acto administrativo', 'Procedimiento', 'Responsabilidad'],
                'total_preguntas' => 40,
                'duracion_minutos' => 15,
                'order_index' => 4,
                'acceso' => 'premium',
            ],
            [
                'titulo' => 'Laboral',
                'slug' => 'laboral',
                'descripcion' => 'Trabajo, derechos y conflictos. Lo que suele caer en el examen.',
                'emoji' => '🤝',
                'tone' => 'sun',
                'materias' => ['Relación laboral', 'Derechos', 'Conflictos'],
                'total_preguntas' => 40,
                'duracion_minutos' => 15,
                'order_index' => 5,
                'acceso' => 'premium',
            ],
            [
                'titulo' => 'Amparo',
                'slug' => 'amparo',
                'descripcion' => 'Procedencia, principios y sentencias. Definitividad sin drama.',
                'emoji' => '🛡️',
                'tone' => 'pink',
                'materias' => ['Procedencia', 'Principios', 'Sentencias'],
                'total_preguntas' => 40,
                'duracion_minutos' => 15,
                'order_index' => 6,
                'acceso' => 'premium',
            ],
        ];

        foreach ($exams as $exam) {
            $questions = ExamQuestionBank::forSlug($exam['slug']);

            $exam = ExamModel::updateOrCreate(
                ['slug' => $exam['slug']],
                array_merge($exam, [
                    'es_publico' => true,
                    'status' => 1,
                    'tipo' => $exam['tipo'] ?? ExamModel::TIPO_NORMAL,
                    'total_preguntas' => count($questions),
                ])
            );

            $exam->replaceQuestions($questions);
        }

        $constitucional = ExamQuestionBank::forSlug('constitucional');

        $gratisQuestions = array_slice($constitucional, 0, 5);
        $gratis = ExamModel::updateOrCreate(
            ['slug' => 'constitucional-gratis'],
            [
                'titulo' => 'Constitucional (gratis)',
                'descripcion' => 'Un bloque corto para practicar sin suscripción.',
                'emoji' => '📗',
                'tone' => 'mint',
                'materias' => ['Principios'],
                'duracion_minutos' => 10,
                'order_index' => 7,
                'acceso' => 'gratis',
                'tipo' => ExamModel::TIPO_NORMAL,
                'es_publico' => true,
                'status' => 1,
                'total_preguntas' => count($gratisQuestions),
            ]
        );
        $gratis->replaceQuestions($gratisQuestions);

        $pruebaQuestions = array_slice($constitucional, 0, 10);
        $prueba = ExamModel::updateOrCreate(
            ['slug' => 'constitucional-prueba'],
            [
                'titulo' => 'Constitucional (prueba)',
                'descripcion' => '10 preguntas de muestra. Tres intentos si aún no eres premium.',
                'emoji' => '🧪',
                'tone' => 'sun',
                'materias' => ['Principios', 'Derechos'],
                'duracion_minutos' => 10,
                'order_index' => 8,
                'acceso' => 'prueba',
                'tipo' => ExamModel::TIPO_NORMAL,
                'es_publico' => true,
                'status' => 1,
                'total_preguntas' => count($pruebaQuestions),
            ]
        );
        $prueba->replaceQuestions($pruebaQuestions);

        $repasoQuestions = array_slice($constitucional, 0, 8);
        $repaso = ExamModel::updateOrCreate(
            ['slug' => 'constitucional-repaso'],
            [
                'titulo' => 'Constitucional (repaso)',
                'descripcion' => 'Al dar siguiente ves si acertaste. Con premium también te dice por qué.',
                'emoji' => '🔁',
                'tone' => 'sky',
                'materias' => ['Principios', 'Derechos'],
                'duracion_minutos' => 15,
                'order_index' => 9,
                'acceso' => 'gratis',
                'tipo' => ExamModel::TIPO_REPASO,
                'es_publico' => true,
                'status' => 1,
                'total_preguntas' => count($repasoQuestions),
            ]
        );
        $repaso->replaceQuestions($repasoQuestions);
    }
}
