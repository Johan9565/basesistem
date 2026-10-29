<?php

namespace App\Services\Assistant;

class IntentRouterService
{
    /**
     * Heuristics and regex patterns for Zero-Latency classification with Unicode support.
     */
    protected array $readPatterns = [
        'agenda_or_pending' => '/(qu[eé]|cu[aá]les|tengo|hay|muestra|ver|mostrar|dime|listar|buscar|consultar|resumen|status|estado).*(pendientes|recordatorios|tareas|agenda|eventos|notas|reuniones|hoy|semana|d[ií]a|agendado|programado)/iu',
        'direct_retrieval'  => '/^(¿|\?|\s)*(busca|encuentra|consultar|mostrar|ver|qu[eé] tengo|cu[aá]les son|dame mis|revisa mis|lee mis|dime mis)/iu',
        'greetings'         => '/^(¿|\?|\s)*(hola|buen(as|os)|hey|saludos|que tal|iniciar|comenzar|buenas noches|buenas tardes)/iu',
        'status_overview'   => '/(tengo algo|qu[eé] hay de nuevo|c[oó]mo voy|c[oó]mo est[aá] mi agenda|resumen del d[ií]a)/iu',
    ];

    protected array $writePatterns = [
        'explicit_action'   => '/(recordar(me)?|acordar(me)?|recuerda|crear|agrega(r)?|anota(r)?|guarda(r)?|pon(er)?|nueva nota|nuevo recordatorio|nueva tarea|apunta(r)?)/iu',
        'obligation_action' => '/(tengo que|debo|hay que|comprar|pagar|enviar|llamar|hacer|terminar|entregar|programar)/iu',
        'prefix_action'     => '/^(nota:|recordatorio:|tarea:|todo:)/iu',
    ];

    protected array $updatePatterns = [
        'update_action' => '/\b(cambia(r)?|reprograma(r)?|mueve|mover|actualiza(r)?|edita(r)?|corrige|corregir|modifica(r)?|posp[oó]n(er)?|aplaza(r)?)\b/iu',
    ];

    /**
     * Classifies a user prompt into a designated pipeline route:
     * - 'read_intent' (Fast Model / Ultra-low TTFT)
     * - 'write_intent' (High Reasoning Model / Strict JSON Schema)
     * - 'reasoning_fallback' (Complex multi-turn / General logic)
     *
     * @param string $prompt
     * @return array ['route' => string, 'confidence' => float, 'matched_pattern' => ?string, 'inferred_intent' => string]
     */
    public function classify(string $prompt): array
    {
        $trimmed = trim($prompt);
        if (empty($trimmed)) {
            return [
                'route'           => 'read_intent',
                'confidence'      => 1.0,
                'matched_pattern' => 'empty_input',
                'inferred_intent' => 'greeting',
            ];
        }

        // Clean leading punctuation like "¿" or "¡" for regex matching
        $normalized = preg_replace('/^[¿¡\s]+/', '', $trimmed);

        // Check if message is a combined / ambiguous multi-intent prompt
        $isComplex = preg_match('/\b(y luego|y despu[eé]s|pero tambi[eé]n|adem[aá]s de esto|por qu[eé]|explica|analiza el c[oó]digo|refactor[ií]za)\b/iu', $normalized);
        if ($isComplex) {
            return [
                'route'           => 'reasoning_fallback',
                'confidence'      => 0.85,
                'matched_pattern' => 'multi_part_or_reasoning',
                'inferred_intent' => 'complex_reasoning',
            ];
        }

        // Check if message is an update / reschedule / edit intent
        foreach ($this->updatePatterns as $name => $pattern) {
            if (preg_match($pattern, $normalized)) {
                return [
                    'route'           => 'reasoning_fallback',
                    'confidence'      => 0.95,
                    'matched_pattern' => $name,
                    'inferred_intent' => 'update_entity',
                ];
            }
        }

        // Check if message is querying past conversation history / memory
        $isHistoryQuery = preg_match('/\b(qu[eé]|de qu[eé]|recuerdas|te acuerdas|qu[eé] dijimos|qu[eé] platicamos|qu[eé] hablamos|hablamos|platicamos)\b.*\b(ayer|antier|semana pasada|el lunes|el martes|el mi[eé]rcoles|el jueves|el viernes|el s[aá]bado|el domingo|sesi[oó]n|anterior|pasad[oa]|presupuesto|acordamos|conversaci[oó]n)\b/iu', $normalized)
            || preg_match('/^(¿|\?|\s)*(qu[eé] (hablamos|platicamos|dijimos|vimos|acordamos)|recuerdas qu[eé]|te acuerdas)/iu', $normalized);

        if ($isHistoryQuery) {
            return [
                'route'           => 'reasoning_fallback',
                'confidence'      => 0.95,
                'matched_pattern' => 'historical_memory',
                'inferred_intent' => 'search_conversation_history',
            ];
        }

        // 1. Evaluate Write Intent Patterns
        $writeMatches = [];
        foreach ($this->writePatterns as $name => $pattern) {
            if (preg_match($pattern, $normalized)) {
                $writeMatches[] = $name;
            }
        }

        // 2. Evaluate Read Intent Patterns
        $readMatches = [];
        foreach ($this->readPatterns as $name => $pattern) {
            if (preg_match($pattern, $normalized)) {
                $readMatches[] = $name;
            }
        }

        // Disambiguation
        // If it starts with greeting, or asks for pending items without instruction to create:
        if (!empty($readMatches) && empty($writeMatches)) {
            return [
                'route'           => 'read_intent',
                'confidence'      => 0.95,
                'matched_pattern' => implode(',', $readMatches),
                'inferred_intent' => 'data_query',
            ];
        }

        // If it explicitly asks "que pendientes tengo" or "¿cuáles son mis recordatorios para hoy?"
        if (preg_match('/(qu[eé]|cu[aá]les).*(pendientes|recordatorios|agenda|eventos|hoy)/iu', $normalized) && !preg_match('/(agrega|crea|anota|guarda)/iu', $normalized)) {
            return [
                'route'           => 'read_intent',
                'confidence'      => 0.98,
                'matched_pattern' => 'read_pending_override',
                'inferred_intent' => 'get_pending_reminders',
            ];
        }

        if (!empty($writeMatches)) {
            return [
                'route'           => 'write_intent',
                'confidence'      => 0.95,
                'matched_pattern' => implode(',', $writeMatches),
                'inferred_intent' => 'entity_creation',
            ];
        }

        if (!empty($readMatches)) {
            return [
                'route'           => 'read_intent',
                'confidence'      => 0.90,
                'matched_pattern' => implode(',', $readMatches),
                'inferred_intent' => 'data_query',
            ];
        }

        // Fallback for general conversation or reasoning
        return [
            'route'           => 'reasoning_fallback',
            'confidence'      => 0.70,
            'matched_pattern' => 'default_fallback',
            'inferred_intent' => 'conversation',
        ];
    }
}
