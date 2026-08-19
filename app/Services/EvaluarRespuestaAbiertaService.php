<?php

namespace App\Services;

use App\Support\ExamQuestionNormalizer;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class EvaluarRespuestaAbiertaService
{
    public const SYSTEM_PROMPT = <<<'PROMPT'
Eres un evaluador pedagógico experto en Derecho. Tu función es calificar la respuesta abierta de un estudiante comparándola contra la respuesta correcta, la respuesta modelo y los criterios de evaluación.
Instrucciones:
1. Evalúa el fondo conceptual, no la redacción literal.
2. Identifica cuáles de los criterios dados fueron cubiertos conceptualmente por el estudiante y cuáles omitió.
3. Asigna 'nivel_acierto': un entero de 0 a 100 según qué tanto cubrió los criterios y qué tan cerca está de la respuesta correcta/modelo.
   - 100: cubre todos los criterios con precisión.
   - 50-79: cubre algunos criterios o de forma incompleta.
   - 1-49: toca el tema pero omite lo esencial.
   - 0: vacía, ajena o contradictoria.
4. Determina el 'estado': 'correcto' (nivel 80-100 o todos los criterios), 'parcial' (nivel 1-79), 'incorrecto' (nivel 0).
5. Genera una 'retroalimentacion' concisa (máximo 2 oraciones) explicando por qué tiene ese nivel.
6. Responde estrictamente con la estructura JSON solicitada.
PROMPT;

    /**
     * @param  array<string, mixed>  $preguntaDoc
     * @return array{
     *     estado: 'correcto'|'parcial'|'incorrecto',
     *     criterios_cumplidos: list<string>,
     *     criterios_omitidos: list<string>,
     *     retroalimentacion: string
     * }
     */
    public function evaluar(array $preguntaDoc, string $respuestaEstudiante): array
    {
        $respuestaEstudiante = trim($respuestaEstudiante);
        if ($respuestaEstudiante === '') {
            return [
                'estado' => 'incorrecto',
                'nivel_acierto' => 0,
                'criterios_cumplidos' => [],
                'criterios_omitidos' => $this->criterios($preguntaDoc),
                'retroalimentacion' => 'No se recibió una respuesta para evaluar.',
            ];
        }

        $raw = $this->llamarDeepSeek($this->userPrompt($preguntaDoc, $respuestaEstudiante));

        return $this->normalizar($raw, $this->criterios($preguntaDoc));
    }

    /**
     * @param  array<string, mixed>  $preguntaDoc
     */
    private function userPrompt(array $preguntaDoc, string $respuestaEstudiante): string
    {
        $criterios = $this->criterios($preguntaDoc);

        return <<<PROMPT
Evalúa esta respuesta abierta. Devuelve únicamente JSON con las claves estado, nivel_acierto, criterios_cumplidos, criterios_omitidos y retroalimentacion.

Pregunta:
{$this->texto($preguntaDoc, ['pregunta', 'prompt'])}

Respuesta correcta:
{$this->texto($preguntaDoc, ['respuesta_correcta', 'correct_answer'])}

Respuesta modelo:
{$this->texto($preguntaDoc, ['respuesta_modelo', 'explanation'])}

Criterios obligatorios:
{$this->lista($criterios)}

Respuesta del estudiante:
{$respuestaEstudiante}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function llamarDeepSeek(string $userPrompt): array
    {
        $apiKey = (string) config('ia.deepseek.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Falta DEEPSEEK_API_KEY.');
        }

        $base = rtrim((string) config('ia.deepseek.base_url'), '/');
        $timeout = max(5, (int) config('ia.timeout', 20));
        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->connectTimeout(8)
            ->acceptJson()
            ->post($base.'/chat/completions', [
                'model' => config('ia.deepseek.model', 'deepseek-chat'),
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        $response->throw();
        $content = (string) data_get($response->json(), 'choices.0.message.content', '');

        return $this->decodeJson($content);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('La IA no devolvió JSON válido.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $criterios
     * @return array{
     *     estado: 'correcto'|'parcial'|'incorrecto',
     *     criterios_cumplidos: list<string>,
     *     criterios_omitidos: list<string>,
     *     retroalimentacion: string
     * }
     */
    private function normalizar(array $raw, array $criterios): array
    {
        $cumplidos = $this->stringList($raw['criterios_cumplidos'] ?? []);
        $omitidos = $this->stringList($raw['criterios_omitidos'] ?? []);
        $estado = strtolower(trim((string) ($raw['estado'] ?? '')));
        $nivel = $this->nivelDesdeRaw($raw, $estado, $cumplidos, $omitidos, $criterios);

        if (! in_array($estado, ['correcto', 'parcial', 'incorrecto'], true)) {
            $estado = match (true) {
                $nivel >= 80 => 'correcto',
                $nivel >= 1 => 'parcial',
                default => 'incorrecto',
            };
        }

        if ($cumplidos === [] && $omitidos === [] && $criterios !== []) {
            if ($estado === 'correcto') {
                $cumplidos = $criterios;
            } elseif ($estado === 'incorrecto') {
                $omitidos = $criterios;
            }
        }

        $retro = trim((string) ($raw['retroalimentacion'] ?? $raw['retroalimentación'] ?? ''));
        if ($retro === '') {
            $retro = match ($estado) {
                'correcto' => 'La respuesta cubre los criterios obligatorios.',
                'parcial' => 'La respuesta cubre algunos criterios, pero omite otros relevantes.',
                default => 'La respuesta no cubre los criterios obligatorios.',
            };
        }

        return [
            'estado' => $estado,
            'nivel_acierto' => $nivel,
            'criterios_cumplidos' => $cumplidos,
            'criterios_omitidos' => $omitidos,
            'retroalimentacion' => mb_substr($retro, 0, 600),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $cumplidos
     * @param  list<string>  $omitidos
     * @param  list<string>  $criterios
     */
    private function nivelDesdeRaw(array $raw, string $estado, array $cumplidos, array $omitidos, array $criterios): int
    {
        $rawNivel = $raw['nivel_acierto'] ?? $raw['nivel'] ?? $raw['acierto'] ?? null;
        if (is_numeric($rawNivel)) {
            return max(0, min(100, (int) round((float) $rawNivel)));
        }

        $denominador = count($cumplidos) + count($omitidos);
        if ($denominador > 0) {
            return (int) round((count($cumplidos) / $denominador) * 100);
        }

        return match ($estado) {
            'correcto' => 100,
            'parcial' => 50,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $preguntaDoc
     * @return list<string>
     */
    private function criterios(array $preguntaDoc): array
    {
        return ExamQuestionNormalizer::splitList($preguntaDoc['criterios'] ?? $preguntaDoc['keywords'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $preguntaDoc
     * @param  list<string>  $keys
     */
    private function texto(array $preguntaDoc, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($preguntaDoc[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : (preg_split('/[|,;]+/', $value) ?: []);
        }

        return collect(is_array($value) ? $value : [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $items
     */
    private function lista(array $items): string
    {
        if ($items === []) {
            return '(sin criterios explícitos; usa la respuesta modelo)';
        }

        return collect($items)->map(fn ($item, $i) => ($i + 1).'. '.$item)->implode("\n");
    }
}
