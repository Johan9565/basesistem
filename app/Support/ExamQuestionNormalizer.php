<?php

namespace App\Support;

class ExamQuestionNormalizer
{
    public const TYPE_SINGLE = 'opcion_unica';
    public const TYPE_MULTIPLE = 'opcion_multiple';
    public const TYPE_OPEN = 'abierta';

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    public static function normalize(array $question, int $index = 0): array
    {
        $options = self::normalizeOptions($question['opciones'] ?? $question['options'] ?? []);
        $type = self::resolveType((string) ($question['tipo'] ?? $question['type'] ?? ''), $options);
        $id = trim((string) ($question['id'] ?? $question['code'] ?? ''));

        if ($id === '') {
            $id = 'q-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
        }

        $correctIndexes = self::normalizeCorrectIndexes(
            $question['correctas'] ?? $question['correct_option_ids'] ?? $question['correct_option_id'] ?? [],
            $options
        );

        $respuestaCorrecta = self::asText(
            $question['respuesta_correcta'] ?? $question['correct_answer'] ?? ''
        );

        if ($correctIndexes === [] && $type !== self::TYPE_OPEN) {
            $correctIndexes = self::indexesFromRespuestaCorrecta($respuestaCorrecta, $options);
        }

        $respuestaModelo = trim((string) (
            $question['respuesta_modelo']
            ?? $question['explanation']
            ?? $question['explicacion']
            ?? ''
        ));

        if ($respuestaModelo === '') {
            $accepted = self::splitList($question['accepted_answers'] ?? $question['respuestas_aceptadas'] ?? []);
            $respuestaModelo = (string) ($accepted[0] ?? '');
        }

        $criterios = self::splitList(
            $question['criterios'] ?? $question['keywords'] ?? $question['palabras_clave'] ?? []
        );

        if ($type !== self::TYPE_OPEN) {
            $fromOptions = collect($correctIndexes)
                ->map(fn ($i) => trim((string) ($options[$i]['text'] ?? '')))
                ->filter(fn ($text) => $text !== '')
                ->values()
                ->all();

            if ($fromOptions !== []) {
                $respuestaCorrecta = implode(' | ', $fromOptions);
            }
        } elseif ($respuestaCorrecta === '') {
            $respuestaCorrecta = $respuestaModelo;
        }

        $acceptedAnswers = $respuestaCorrecta !== ''
            ? [$respuestaCorrecta]
            : ($respuestaModelo !== '' ? [$respuestaModelo] : []);

        $normalized = [
            'id' => $id,
            'type' => $type,
            'tipo' => $type,
            'prompt' => trim((string) ($question['pregunta'] ?? $question['prompt'] ?? '')),
            'pregunta' => trim((string) ($question['pregunta'] ?? $question['prompt'] ?? '')),
            'subject' => trim((string) ($question['materia'] ?? $question['subject'] ?? '')),
            'materia' => trim((string) ($question['materia'] ?? $question['subject'] ?? '')),
            'explanation' => $respuestaModelo,
            'respuesta_modelo' => $respuestaModelo,
            'respuesta_correcta' => $respuestaCorrecta,
            'options' => $options,
            'opciones' => collect($options)->pluck('text')->values()->all(),
            'correctas' => $correctIndexes,
            'correct_option_ids' => collect($correctIndexes)->map(fn ($i) => (string) $i)->all(),
            'correct_option_id' => isset($correctIndexes[0]) ? (string) $correctIndexes[0] : '',
            'accepted_answers' => $acceptedAnswers,
            'keywords' => $criterios,
            'criterios' => $criterios,
            'orden' => (int) ($question['orden'] ?? ($index + 1)),
        ];

        if ($type === self::TYPE_OPEN) {
            $normalized['options'] = [];
            $normalized['opciones'] = [];
            $normalized['correctas'] = [];
            $normalized['correct_option_ids'] = [];
            $normalized['correct_option_id'] = '';
        }

        return $normalized;
    }

    /**
     * Documento para la colección `questions`.
     *
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    public static function toStorage(array $question, int $orden): array
    {
        $normalized = self::normalize($question, max(0, $orden - 1));

        return [
            'orden' => $orden,
            'materia' => $normalized['materia'],
            'tipo' => $normalized['tipo'],
            'pregunta' => $normalized['pregunta'],
            'opciones' => $normalized['opciones'],
            'correctas' => $normalized['correctas'],
            'respuesta_correcta' => $normalized['respuesta_correcta'],
            'respuesta_modelo' => $normalized['respuesta_modelo'],
            'criterios' => $normalized['criterios'],
        ];
    }

    public static function isGradable(array $question): bool
    {
        $type = $question['type'] ?? $question['tipo'] ?? self::TYPE_SINGLE;

        if ($type === self::TYPE_OPEN) {
            return ($question['criterios'] ?? $question['keywords'] ?? []) !== []
                || trim((string) ($question['respuesta_correcta'] ?? '')) !== ''
                || trim((string) ($question['respuesta_modelo'] ?? $question['explanation'] ?? '')) !== '';
        }

        return ($question['correctas'] ?? $question['correct_option_ids'] ?? []) !== [];
    }

    /**
     * @return array{is_correct: bool|null, needs_review: bool, nivel_acierto: int}
     */
    public static function grade(array $question, mixed $answer): array
    {
        $question = self::normalize($question);
        $type = $question['type'];

        if ($type === self::TYPE_OPEN) {
            return self::gradeOpen($question, is_string($answer) ? $answer : '');
        }

        if ($type === self::TYPE_MULTIPLE) {
            $selected = is_array($answer) ? array_values(array_map('strval', $answer)) : [];
            $expected = array_values(array_map('strval', $question['correct_option_ids'] ?? []));
            sort($selected);
            sort($expected);

            if ($expected === []) {
                return ['is_correct' => null, 'needs_review' => true, 'nivel_acierto' => 0];
            }

            $correct = $selected !== [] && $selected === $expected;

            return [
                'is_correct' => $correct,
                'needs_review' => false,
                'nivel_acierto' => $correct ? 100 : 0,
            ];
        }

        $selected = is_string($answer) ? $answer : (is_numeric($answer) ? (string) $answer : '');
        $expected = (string) ($question['correct_option_id'] ?? '');

        if ($expected === '') {
            return ['is_correct' => null, 'needs_review' => true, 'nivel_acierto' => 0];
        }

        $correct = $selected !== '' && $selected === $expected;

        return [
            'is_correct' => $correct,
            'needs_review' => false,
            'nivel_acierto' => $correct ? 100 : 0,
        ];
    }

    public static function resolveType(string $raw, array $options): string
    {
        $value = self::normalizeKey($raw);

        return match (true) {
            in_array($value, ['abierta', 'abierto', 'open', 'texto', 'abiertas'], true) => self::TYPE_OPEN,
            in_array($value, ['opcion_multiple', 'multiple', 'multiples', 'varias', 'multiple_respuesta', 'multirrespuesta'], true) => self::TYPE_MULTIPLE,
            in_array($value, ['opcion_unica', 'unica', 'unico', 'single', 'opcionmultiple'], true) => self::TYPE_SINGLE,
            $options === [] => self::TYPE_OPEN,
            default => self::TYPE_SINGLE,
        };
    }

    public static function asText(mixed $value): string
    {
        if (is_array($value)) {
            return implode(' | ', self::splitList($value));
        }

        return trim((string) $value);
    }

    /**
     * @param  list<array{id: string, text: string}>  $options
     * @return list<int>
     */
    private static function indexesFromRespuestaCorrecta(string $raw, array $options): array
    {
        if ($raw === '' || $options === []) {
            return [];
        }

        $max = count($options) - 1;

        return collect(self::splitList($raw))
            ->map(function ($value) use ($options, $max) {
                $trimmed = strtolower(trim((string) $value));
                if ($trimmed !== '' && ctype_alpha($trimmed) && strlen($trimmed) === 1) {
                    return ord($trimmed) - ord('a');
                }

                if ($trimmed !== '' && ctype_digit($trimmed)) {
                    return (int) $trimmed;
                }

                $normalized = self::normalizeText((string) $value);
                foreach ($options as $index => $option) {
                    if (self::normalizeText((string) ($option['text'] ?? '')) === $normalized) {
                        return $index;
                    }
                }

                return -1;
            })
            ->filter(fn ($index) => is_numeric($index) && (int) $index >= 0 && (int) $index <= $max)
            ->map(fn ($index) => (int) $index)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function splitList(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            $joined = implode(',', array_map(fn ($item) => is_scalar($item) ? (string) $item : '', $value));
            if (self::looksLikeJsonArrayFragments($value)) {
                $decoded = json_decode($joined, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }

            $items = [];
            foreach (array_values($value) as $item) {
                if (is_array($item)) {
                    $items = array_merge($items, self::splitList($item));
                    continue;
                }

                $text = trim((string) $item);
                $text = trim($text, " \t\n\r\0\x0B\"'");
                if ($text === '') {
                    continue;
                }

                if ((str_starts_with($text, '[') && str_ends_with($text, ']'))
                    || (str_starts_with($text, '{') && str_ends_with($text, '}'))) {
                    $nested = json_decode($text, true);
                    if (is_array($nested)) {
                        $items = array_merge($items, self::splitList($nested));
                        continue;
                    }
                }

                $items[] = $text;
            }

            return array_values(array_unique($items));
        }

        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return self::splitList($decoded);
        }

        return collect(preg_split('/[|,;]+/', $text) ?: [])
            ->map(fn ($item) => trim((string) $item, " \t\n\r\0\x0B\"'"))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<mixed>  $items
     */
    private static function looksLikeJsonArrayFragments(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        $first = ltrim((string) ($items[0] ?? ''));
        $last = rtrim((string) ($items[array_key_last($items)] ?? ''));

        return str_starts_with($first, '[') || str_ends_with($last, ']');
    }

    public static function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    /**
     * @return list<array{id: string, text: string}>
     */
    private static function normalizeOptions(mixed $options): array
    {
        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }

        return collect(is_array($options) ? $options : [])
            ->values()
            ->map(function ($option, $index) {
                if (is_string($option) || is_numeric($option)) {
                    $text = trim((string) $option);
                } else {
                    $option = is_array($option) ? $option : [];
                    $text = trim((string) ($option['text'] ?? $option['texto'] ?? ''));
                }

                return [
                    'id' => (string) $index,
                    'text' => $text,
                ];
            })
            ->filter(fn ($option) => $option['text'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: string, text: string}>  $options
     * @return list<int>
     */
    private static function normalizeCorrectIndexes(mixed $raw, array $options): array
    {
        $max = count($options) - 1;
        $values = is_array($raw) && ! self::isListOfScalars($raw)
            ? $raw
            : self::splitList($raw);

        return collect($values)
            ->map(function ($value) {
                $value = strtolower(trim((string) $value));
                if ($value !== '' && ctype_alpha($value) && strlen($value) === 1) {
                    return ord($value) - ord('a');
                }

                return (int) $value;
            })
            ->filter(fn ($index) => is_numeric($index) && (int) $index >= 0 && (int) $index <= $max)
            ->map(fn ($index) => (int) $index)
            ->unique()
            ->values()
            ->all();
    }

    private static function isListOfScalars(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_is_list($value) && ! is_array($value[0] ?? null);
    }

    /**
     * @return array{is_correct: bool|null, needs_review: bool, nivel_acierto: int}
     */
    private static function gradeOpen(array $question, string $answer): array
    {
        $normalized = self::normalizeText($answer);
        $accepted = collect($question['accepted_answers'] ?? [])
            ->map(fn ($item) => self::normalizeText((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values();
        $keywords = collect($question['criterios'] ?? $question['keywords'] ?? [])
            ->map(fn ($item) => self::normalizeText((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values();

        if ($normalized === '') {
            return [
                'is_correct' => $accepted->isEmpty() && $keywords->isEmpty() ? null : false,
                'needs_review' => $accepted->isEmpty() && $keywords->isEmpty(),
                'nivel_acierto' => 0,
            ];
        }

        if ($accepted->contains($normalized)) {
            return ['is_correct' => true, 'needs_review' => false, 'nivel_acierto' => 100];
        }

        if ($keywords->isNotEmpty()) {
            $matched = $keywords->filter(fn ($keyword) => str_contains($normalized, $keyword))->count();
            $nivel = (int) round(($matched / $keywords->count()) * 100);

            return [
                'is_correct' => $matched === $keywords->count(),
                'needs_review' => false,
                'nivel_acierto' => $nivel,
            ];
        }

        if ($accepted->isNotEmpty()) {
            return ['is_correct' => false, 'needs_review' => false, 'nivel_acierto' => 0];
        }

        return ['is_correct' => null, 'needs_review' => true, 'nivel_acierto' => 0];
    }

    private static function normalizeKey(string $value): string
    {
        $value = self::normalizeText($value);

        return str_replace([' ', '-'], '_', $value);
    }
}
