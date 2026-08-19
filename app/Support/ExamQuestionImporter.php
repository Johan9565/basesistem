<?php

namespace App\Support;

use InvalidArgumentException;

class ExamQuestionImporter
{
    /**
     * @return array{questions: list<array<string, mixed>>, errors: list<string>}
     */
    public function parseFile(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            throw new InvalidArgumentException('El archivo está vacío.');
        }

        return $this->parseString($contents);
    }

    /**
     * @return array{questions: list<array<string, mixed>>, errors: list<string>}
     */
    public function parseString(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
        $lines = array_values(array_filter($lines, fn ($line) => trim($line) !== ''));

        if ($lines === []) {
            throw new InvalidArgumentException('El archivo no tiene filas.');
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $header = $this->parseCsvLine($lines[0], $delimiter);
        $map = $this->headerMap($header);

        if (! isset($map['pregunta']) && ! isset($map['prompt'])) {
            throw new InvalidArgumentException('Falta la columna "pregunta" en el template.');
        }

        $questions = [];
        $errors = [];
        $seenIds = [];

        foreach (array_slice($lines, 1) as $offset => $line) {
            $rowNumber = $offset + 2;
            $cols = $this->parseCsvLine($line, $delimiter);
            $row = $this->rowAssoc($map, $cols);

            $prompt = trim((string) ($row['pregunta'] ?? $row['prompt'] ?? ''));
            if ($prompt === '') {
                continue;
            }

            $options = $this->optionsFromRow($row);
            $question = ExamQuestionNormalizer::normalize([
                'id' => $row['id'] ?? '',
                'tipo' => $row['tipo'] ?? $row['type'] ?? '',
                'pregunta' => $prompt,
                'materia' => $row['materia'] ?? $row['subject'] ?? '',
                'respuesta_modelo' => $row['respuesta_modelo'] ?? $row['explicacion'] ?? $row['explanation'] ?? '',
                'respuesta_correcta' => $row['respuesta_correcta'] ?? $row['correct_answer'] ?? '',
                'opciones' => $options,
                'correctas' => $row['correctas'] ?? $row['correcta'] ?? '',
                'criterios' => $row['criterios'] ?? $row['palabras_clave'] ?? '',
            ], $offset);

            if ($question['type'] !== ExamQuestionNormalizer::TYPE_OPEN && $question['options'] === []) {
                $errors[] = "Fila {$rowNumber}: las preguntas de opción múltiple necesitan al menos dos opciones.";
                continue;
            }

            if ($question['type'] === ExamQuestionNormalizer::TYPE_SINGLE && count($question['correct_option_ids']) !== 1) {
                $errors[] = "Fila {$rowNumber}: indica una sola respuesta correcta en \"correctas\" (0, 1, 2…) o el texto en \"respuesta_correcta\".";
                continue;
            }

            if ($question['type'] === ExamQuestionNormalizer::TYPE_MULTIPLE && count($question['correct_option_ids']) < 2) {
                $errors[] = "Fila {$rowNumber}: en tipo opcion_multiple indica al menos dos correctas en \"correctas\" (ejemplo: 0|2) o en \"respuesta_correcta\".";
                continue;
            }

            if (isset($seenIds[$question['id']])) {
                $question['id'] = $question['id'].'-'.$rowNumber;
            }
            $seenIds[$question['id']] = true;

            $questions[] = $question;
        }

        if ($questions === []) {
            throw new InvalidArgumentException('No se encontraron preguntas válidas en el archivo.');
        }

        return [
            'questions' => $questions,
            'errors' => $errors,
        ];
    }

    public static function templateCsv(): string
    {
        $header = [
            'tipo',
            'pregunta',
            'materia',
            'opcion_a',
            'opcion_b',
            'opcion_c',
            'opcion_d',
            'opcion_e',
            'opcion_f',
            'correctas',
            'respuesta_correcta',
            'respuesta_modelo',
            'criterios',
        ];

        $rows = [
            [
                'opcion_unica',
                '¿Qué protege, en esencia, el juicio de amparo?',
                'Amparo',
                'Normas o actos de autoridad que violen derechos',
                'Contratos entre particulares',
                'Solo leyes federales fiscales',
                'La acción penal pública',
                '',
                '',
                '0',
                'Normas o actos de autoridad que violen derechos',
                'El amparo tutela derechos humanos y fundamentales frente a normas o actos de autoridad.',
                '',
            ],
            [
                'opcion_multiple',
                'Son elementos frecuentes de validez del acto administrativo:',
                'Administrativo',
                'Competencia de la autoridad',
                'Fundamentación y motivación',
                'Firma de un particular',
                'Publicación en redes sociales',
                '',
                '',
                '0|1',
                'Competencia de la autoridad | Fundamentación y motivación',
                'Competencia, fundamentación y motivación son exigencias básicas.',
                '',
            ],
            [
                'abierta',
                'Explica el principio de definitividad en el juicio de amparo.',
                'Amparo',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'Agotar los recursos ordinarios antes del amparo, salvo excepciones.',
                'Como regla, deben agotarse los recursos ordinarios antes de acudir al amparo.',
                'definitividad|recursos ordinarios',
            ],
        ];

        $lines = [self::csvLine($header)];
        foreach ($rows as $row) {
            $lines[] = self::csvLine($row);
        }

        return implode("\n", $lines)."\n";
    }

    private function detectDelimiter(string $headerLine): string
    {
        $commas = substr_count($headerLine, ',');
        $semicolons = substr_count($headerLine, ';');

        return $semicolons > $commas ? ';' : ',';
    }

    /**
     * @return list<string>
     */
    private function parseCsvLine(string $line, string $delimiter): array
    {
        $parsed = str_getcsv($line, $delimiter);

        return array_map(fn ($value) => trim((string) $value), $parsed);
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    private function headerMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $name) {
            $key = ExamQuestionNormalizer::normalizeText($name);
            $key = str_replace([' ', '-'], '_', $key);
            if ($key !== '') {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $cols
     * @return array<string, string>
     */
    private function rowAssoc(array $map, array $cols): array
    {
        $row = [];
        foreach ($map as $key => $index) {
            $row[$key] = $cols[$index] ?? '';
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function optionsFromRow(array $row): array
    {
        $letters = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        $options = [];

        foreach ($letters as $letter) {
            $text = trim((string) ($row['opcion_'.$letter] ?? $row['opcion'.$letter] ?? ''));
            if ($text === '') {
                continue;
            }
            $options[] = $text;
        }

        return $options;
    }

    /**
     * @param  list<string>  $fields
     */
    private static function csvLine(array $fields): string
    {
        $escaped = array_map(function ($field) {
            $field = (string) $field;
            if (str_contains($field, '"') || str_contains($field, ',') || str_contains($field, ';') || str_contains($field, "\n")) {
                return '"'.str_replace('"', '""', $field).'"';
            }

            return $field;
        }, $fields);

        return implode(',', $escaped);
    }
}
