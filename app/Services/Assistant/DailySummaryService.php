<?php

namespace App\Services\Assistant;

use App\Models\AssistantMessage;
use App\Models\DailySummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DailySummaryService
{
    protected ModelRouterService $modelRouter;
    protected string $timezone = 'America/Cancun';

    public function __construct(ModelRouterService $modelRouter)
    {
        $this->modelRouter = $modelRouter;
    }

    /**
     * Generates and stores daily summary for a specific user and date.
     *
     * @param User $user
     * @param string $date YYYY-MM-DD
     * @param callable|null $customLlmInvoker Optional for testing/mocking
     * @return DailySummary|null
     */
    public function generateSummaryForUserAndDate(User $user, string $date, ?callable $customLlmInvoker = null): ?DailySummary
    {
        $userId = (string) ($user->_id ?? $user->id);

        $startCarbon = Carbon::parse($date, $this->timezone)->startOfDay();
        $endCarbon   = Carbon::parse($date, $this->timezone)->endOfDay();

        $startUtc = new \MongoDB\BSON\UTCDateTime($startCarbon->getTimestampMs());
        $endUtc   = new \MongoDB\BSON\UTCDateTime($endCarbon->getTimestampMs());

        $messages = AssistantMessage::where(function ($q) use ($userId, $user) {
            $q->where('user_id', $userId)
              ->orWhere('user_id', (string) $user->id);
        })
        ->where('created_at', '>=', $startUtc)
        ->where('created_at', '<=', $endUtc)
        ->where('status', '!=', 'failed')
        ->orderBy('created_at', 'asc')
        ->get();

        if ($messages->isEmpty()) {
            return null;
        }

        // Build dialogue transcript
        $dialogueLines = [];
        foreach ($messages as $msg) {
            $role = $msg->role ?? (!empty($msg->prompt) ? 'user' : 'assistant');
            $roleLabel = ($role === 'user') ? 'Usuario' : 'Cami';
            $content = trim((string) ($msg->content ?: ($role === 'user' ? $msg->prompt : ($msg->display_text ?: $msg->reply))));

            if ($content !== '') {
                $dialogueLines[] = "{$roleLabel}: {$content}";
            }
        }

        if (empty($dialogueLines)) {
            return null;
        }

        $transcript = implode("\n", $dialogueLines);

        // Perform LLM extraction using lightweight model
        $extracted = null;
        if ($customLlmInvoker !== null) {
            $extracted = $customLlmInvoker($transcript, $date);
        } else {
            $extracted = $this->callLightweightModel($transcript, $date, $user);
        }

        if (empty($extracted) || empty($extracted['summary'])) {
            return null;
        }

        return DailySummary::updateOrCreate(
            [
                'user_id' => $userId,
                'date'    => $date,
            ],
            [
                'summary'       => $extracted['summary'],
                'key_decisions' => (array) ($extracted['key_decisions'] ?? []),
                'action_items'  => (array) ($extracted['action_items'] ?? $extracted['pending_tasks'] ?? []),
                'topics'        => (array) ($extracted['topics'] ?? []),
                'turns_count'   => count($dialogueLines),
                'metadata'      => [
                    'source_messages_count' => count($messages),
                    'generated_at'          => Carbon::now($this->timezone)->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Calls lightweight model with strict extraction instructions.
     */
    protected function callLightweightModel(string $transcript, string $date, User $user): ?array
    {
        $modelConfig = $this->modelRouter->getActiveModelConfig('read_intent');
        $endpoint = rtrim($modelConfig['base_url'], '/');
        if (!str_ends_with($endpoint, '/chat/completions')) {
            $endpoint .= '/chat/completions';
        }

        $systemPrompt = <<<SYS
Eres un motor de extracción estructurada y síntesis diaria para la asistente Cami y su usuario {$user->name}.
Fecha evaluada: {$date} (Zona horaria: {$this->timezone}).

INSTRUCCIONES ESTRICTAS:
1. Analiza el diálogo de la jornada y extrae:
   - "summary": Resumen conciso y claro de los temas principales tratados y acuerdos.
   - "key_decisions": Lista de decisiones clave tomadas o datos concretos acordados.
   - "action_items": Lista de tareas pendientes derivadas, compromisos o acciones a realizar.
   - "topics": Lista corta de 2 a 4 palabras clave o tópicos principales.
2. DESCARTA POR COMPLETO:
   - Saludos cotidianos ("hola", "buenos días", "¿cómo estás?").
   - Cortesías o charla trivial sin contenido ("gracias", "de nada", "aquí ando").
   - Errores de sistema, reintentos o respuestas fallidas.
3. Devuelve EXCLUSIVAMENTE un objeto JSON válido con este formato:
{
  "summary": "...",
  "key_decisions": ["..."],
  "action_items": ["..."],
  "topics": ["..."]
}
SYS;

        try {
            $response = Http::withToken($modelConfig['api_key'])
                ->withHeaders((array) ($modelConfig['headers'] ?? []))
                ->timeout(max(10, intval($modelConfig['timeout_ms'] / 1000)))
                ->post($endpoint, [
                    'model'       => $modelConfig['model'],
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => "Transcripción de las conversaciones del día {$date}:\n\n" . $transcript],
                    ],
                    'temperature' => 0.1,
                ]);

            if (!$response->successful()) {
                Log::warning('DailySummary LLM call failed: ' . $response->status() . ' - ' . $response->body());
                return null;
            }

            $rawContent = $response->json('choices.0.message.content');
            return $this->parseJsonExtraction($rawContent);
        } catch (\Throwable $e) {
            Log::error('DailySummaryService LLM exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Safely extracts JSON from markdown-wrapped or raw text.
     */
    protected function parseJsonExtraction(?string $text): ?array
    {
        if (empty($text)) {
            return null;
        }

        $cleaned = trim($text);
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $cleaned, $matches)) {
            $cleaned = trim($matches[1]);
        }

        $decoded = json_decode($cleaned, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
