<?php

namespace Tests\Unit;

use App\Support\ExamQuestionImporter;
use App\Support\ExamQuestionNormalizer;
use Tests\TestCase;

class ExamQuestionImporterTest extends TestCase
{
    public function test_it_parses_single_multiple_and_open_questions(): void
    {
        $csv = ExamQuestionImporter::templateCsv();
        $parsed = (new ExamQuestionImporter)->parseString($csv);

        $this->assertCount(3, $parsed['questions']);
        $this->assertSame([], $parsed['errors']);

        $this->assertSame(ExamQuestionNormalizer::TYPE_SINGLE, $parsed['questions'][0]['type']);
        $this->assertSame(['0'], $parsed['questions'][0]['correct_option_ids']);
        $this->assertSame([0], $parsed['questions'][0]['correctas']);
        $this->assertSame('Normas o actos de autoridad que violen derechos', $parsed['questions'][0]['respuesta_correcta']);

        $this->assertSame(ExamQuestionNormalizer::TYPE_MULTIPLE, $parsed['questions'][1]['type']);
        $this->assertSame(['0', '1'], $parsed['questions'][1]['correct_option_ids']);
        $this->assertSame('Competencia de la autoridad | Fundamentación y motivación', $parsed['questions'][1]['respuesta_correcta']);

        $this->assertSame(ExamQuestionNormalizer::TYPE_OPEN, $parsed['questions'][2]['type']);
        $this->assertNotEmpty($parsed['questions'][2]['criterios']);
        $this->assertSame('Agotar los recursos ordinarios antes del amparo, salvo excepciones.', $parsed['questions'][2]['respuesta_correcta']);
    }

    public function test_open_question_grades_by_keywords(): void
    {
        $question = ExamQuestionNormalizer::normalize([
            'id' => 'q-open',
            'type' => 'abierta',
            'prompt' => 'Explica definitividad',
            'keywords' => 'definitividad|recursos ordinarios',
        ]);

        $ok = ExamQuestionNormalizer::grade($question, 'El principio de definitividad exige agotar los recursos ordinarios.');
        $fail = ExamQuestionNormalizer::grade($question, 'No aplica en amparo.');
        $partial = ExamQuestionNormalizer::grade($question, 'Hay que respetar la definitividad.');

        $this->assertTrue($ok['is_correct']);
        $this->assertSame(100, $ok['nivel_acierto']);
        $this->assertFalse($fail['is_correct']);
        $this->assertSame(0, $fail['nivel_acierto']);
        $this->assertFalse($partial['is_correct']);
        $this->assertSame(50, $partial['nivel_acierto']);
    }

    public function test_criterios_unwraps_broken_json_fragments(): void
    {
        $question = ExamQuestionNormalizer::normalize([
            'tipo' => 'abierta',
            'pregunta' => '¿Qué es el proceso penal?',
            'criterios' => [
                '["Define el proceso penal como un mecanismo."',
                '"Menciona la investigación de delitos."',
                '"Incluye la acusación y el juzgamiento de los presuntos responsables."]',
            ],
        ]);

        $this->assertSame([
            'Define el proceso penal como un mecanismo.',
            'Menciona la investigación de delitos.',
            'Incluye la acusación y el juzgamiento de los presuntos responsables.',
        ], $question['criterios']);
    }

    public function test_multiple_question_requires_the_full_set(): void
    {
        $question = ExamQuestionNormalizer::normalize([
            'tipo' => 'opcion_multiple',
            'pregunta' => 'Elige',
            'opciones' => ['A', 'B', 'C'],
            'correctas' => '0|2',
        ]);

        $this->assertTrue(ExamQuestionNormalizer::grade($question, ['2', '0'])['is_correct']);
        $this->assertFalse(ExamQuestionNormalizer::grade($question, ['0'])['is_correct']);
    }

    public function test_storage_shape_matches_spanish_schema(): void
    {
        $stored = ExamQuestionNormalizer::toStorage([
            'tipo' => 'opcion_unica',
            'pregunta' => '¿Qué protege, en esencia, el juicio de amparo?',
            'materia' => 'Amparo',
            'opciones' => [
                'Normas o actos de autoridad que violen derechos',
                'Contratos entre particulares',
                'Solo leyes federales fiscales',
                'La acción penal pública',
            ],
            'correctas' => [0],
            'respuesta_modelo' => 'El amparo tutela derechos humanos y fundamentales...',
            'criterios' => [],
        ], 1);

        $this->assertSame([
            'orden' => 1,
            'materia' => 'Amparo',
            'tipo' => 'opcion_unica',
            'pregunta' => '¿Qué protege, en esencia, el juicio de amparo?',
            'opciones' => [
                'Normas o actos de autoridad que violen derechos',
                'Contratos entre particulares',
                'Solo leyes federales fiscales',
                'La acción penal pública',
            ],
            'correctas' => [0],
            'respuesta_correcta' => 'Normas o actos de autoridad que violen derechos',
            'respuesta_modelo' => 'El amparo tutela derechos humanos y fundamentales...',
            'criterios' => [],
        ], $stored);
    }

    public function test_respuesta_correcta_fills_indexes_when_correctas_is_empty(): void
    {
        $question = ExamQuestionNormalizer::normalize([
            'tipo' => 'opcion_unica',
            'pregunta' => '¿Qué protege el amparo?',
            'opciones' => [
                'Normas o actos de autoridad que violen derechos',
                'Contratos entre particulares',
            ],
            'respuesta_correcta' => 'Normas o actos de autoridad que violen derechos',
        ]);

        $this->assertSame([0], $question['correctas']);
        $this->assertSame('Normas o actos de autoridad que violen derechos', $question['respuesta_correcta']);

        $stored = ExamQuestionNormalizer::toStorage($question, 1);
        $this->assertSame('Normas o actos de autoridad que violen derechos', $stored['respuesta_correcta']);
    }
}
