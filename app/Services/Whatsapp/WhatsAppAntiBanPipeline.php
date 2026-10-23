<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pipeline de transporte desacoplado para WhatsApp.
 * Implementa el protocolo anti-baneo de Meta, fragmentación dinámica
 * y simulación de comportamiento humano (typing, delays, read receipts).
 */
class WhatsAppAntiBanPipeline
{
    protected const DEFAULT_MAX_CHARS = 450;
    protected const DEFAULT_MAX_BUBBLES = 3;

    protected EvolutionApiService $evolution;

    public function __construct(EvolutionApiService $evolution)
    {
        $this->evolution = $evolution;
    }

    /**
     * Procesa y despacha un mensaje aplicando el pipeline anti-baneo completo:
     * 1. Simulación de lectura humana (read receipt con delay aleatorio de 800-1500 ms).
     * 2. Fragmentación dinámica según jerarquía natural (máx 450 chars, máx 3 burbujas).
     * 3. Simulación de estado "composing" (escribiendo...) antes de cada burbuja.
     * 4. Pausas naturales inter-burbujas.
     *
     * @param string $instance Nombre de la instancia en Evolution API
     * @param string $phone Número telefónico del destinatario
     * @param string $text Texto de respuesta generado por el asistente / sistema
     * @param array|null $messageKey Clave del mensaje entrante para marcar lectura
     * @return array<int, array<string, mixed>> Respuestas de Evolution API por cada burbuja
     */
    public function sendHumanizedResponse(
        string $instance,
        string $phone,
        string $text,
        ?array $messageKey = null
    ): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $cleanPhone = $this->evolution->normalizePhone($phone);
        $enabled = filter_var(env('WHATSAPP_ANTI_BAN_ENABLED', true), FILTER_VALIDATE_BOOLEAN);

        // 1. Simulación de Read Receipt si hay un messageKey
        if ($enabled && !empty($messageKey)) {
            $this->simulateReadReceipt($instance, $cleanPhone, $messageKey);
        }

        // 2. Fragmentación dinámica del mensaje (Directiva 1)
        $maxChars = (int) env('WHATSAPP_MAX_CHARS_PER_BUBBLE', self::DEFAULT_MAX_CHARS);
        $maxBubbles = (int) env('WHATSAPP_MAX_BURST_BUBBLES', self::DEFAULT_MAX_BUBBLES);

        $bubbles = $enabled
            ? $this->chunkMessage($text, $maxChars, $maxBubbles)
            : [$text];

        $results = [];
        $totalBubbles = count($bubbles);
        $humanTyping = filter_var(env('WHATSAPP_HUMAN_TYPING', true), FILTER_VALIDATE_BOOLEAN);
        $interBubbleDelayMs = (int) env('WHATSAPP_SPLIT_DELAY_MS', 1200);

        foreach ($bubbles as $index => $bubble) {
            $bubble = trim($bubble);
            if ($bubble === '') {
                continue;
            }

            // 3. Simulación de presencia "composing" (Directiva 2)
            if ($enabled && $humanTyping) {
                $typingDelayMs = $this->calculateTypingDelay($bubble);

                // Notificar a Evolution API el estado composing
                $this->evolution->sendPresence($instance, $cleanPhone, 'composing', $typingDelayMs);

                // Esperar la duración de escritura simulada
                usleep($typingDelayMs * 1000);
            }

            // 4. Enviar burbuja de texto
            try {
                $response = $this->evolution->sendTextMessage($instance, $cleanPhone, $bubble);
                $results[] = $response;
            } catch (Throwable $e) {
                Log::error('WhatsAppAntiBanPipeline: Error enviando burbuja', [
                    'instance' => $instance,
                    'phone'    => $cleanPhone,
                    'bubble'   => $index + 1,
                    'total'    => $totalBubbles,
                    'error'    => $e->getMessage(),
                ]);
            }

            // 5. Pausa natural entre burbujas si no es la última
            if ($index < $totalBubbles - 1 && $interBubbleDelayMs > 0) {
                // Pequeña variación estocástica (+- 20%) para mayor realismo humano
                $jitter = random_int(-150, 250);
                $actualDelay = max(400, $interBubbleDelayMs + $jitter);

                // Pausar presencia mientras 'piensa' el siguiente mensaje
                if ($enabled && $humanTyping) {
                    $this->evolution->sendPresence($instance, $cleanPhone, 'paused', $actualDelay);
                }

                usleep($actualDelay * 1000);
            }
        }

        return $results;
    }

    /**
     * Simula el tiempo de lectura humana y marca el mensaje como leído.
     */
    public function simulateReadReceipt(string $instance, string $phone, array $messageKey): void
    {
        $minDelay = (int) env('WHATSAPP_MIN_READ_DELAY_MS', 800);
        $maxDelay = (int) env('WHATSAPP_MAX_READ_DELAY_MS', 1500);

        if ($maxDelay < $minDelay) {
            $maxDelay = $minDelay + 500;
        }

        $readDelayMs = random_int($minDelay, $maxDelay);
        usleep($readDelayMs * 1000);

        // Notificar presencia disponible y marcar leído
        $this->evolution->sendPresence($instance, $phone, 'available', 500);
        $this->evolution->markMessageAsRead($instance, $messageKey);
    }

    /**
     * Calcula el delay de escritura simulado basado en la longitud del texto.
     * Tasa de escritura humana en móvil: aprox. 25 a 35 ms por carácter.
     * Retorna entre 1,000 ms y 3,000 ms.
     */
    public function calculateTypingDelay(string $bubble): int
    {
        $length = mb_strlen($bubble, 'UTF-8');
        // Factor de ~22 ms por caracter con jitter aleatorio
        $baseDelay = (int) ($length * 22) + random_int(300, 700);

        return max(1000, min($baseDelay, 3000));
    }

    /**
     * DIRECTIVA 1: Fragmentación dinámica de mensajes por jerarquía estricta.
     *
     * Jerarquía:
     * 1. Doble salto de línea (\n\n) -> Párrafos independientes.
     * 2. Salto simple (\n) -> Elementos de lista o quiebres estructurados.
     * 3. Signos de puntuación final (. ! ?) seguidos de espacio.
     * 4. En caso extremo sin puntuación, último espacio antes del límite (400-450).
     *
     * Límite de ráfaga: Máximo $maxBubbles (3) burbujas consecutivas.
     *
     * @param string $text
     * @param int $maxChars
     * @param int $maxBubbles
     * @return array<int, string>
     */
    public function chunkMessage(
        string $text,
        int $maxChars = self::DEFAULT_MAX_CHARS,
        int $maxBubbles = self::DEFAULT_MAX_BUBBLES
    ): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        if (mb_strlen($text, 'UTF-8') <= $maxChars) {
            return [$text];
        }

        // 1. Descomponer el texto por párrafos (doble salto de línea)
        $paragraphs = preg_split('/\n{2,}/u', $text);
        $bubbles = [];
        $currentBubble = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }

            // Si el párrafo por sí mismo supera $maxChars, descomponer por saltos simples
            if (mb_strlen($para, 'UTF-8') > $maxChars) {
                if ($currentBubble !== '') {
                    $bubbles[] = $currentBubble;
                    $currentBubble = '';
                }

                $subChunks = $this->splitParagraphByLines($para, $maxChars);
                foreach ($subChunks as $subChunk) {
                    $bubbles[] = $subChunk;
                }
                continue;
            }

            $candidate = $currentBubble === '' ? $para : $currentBubble . "\n\n" . $para;
            if (mb_strlen($candidate, 'UTF-8') <= $maxChars) {
                $currentBubble = $candidate;
            } else {
                if ($currentBubble !== '') {
                    $bubbles[] = $currentBubble;
                }
                $currentBubble = $para;
            }
        }

        if ($currentBubble !== '') {
            $bubbles[] = $currentBubble;
        }

        // 2. Limitar ráfaga a máximo $maxBubbles (3)
        if (count($bubbles) > $maxBubbles) {
            $bubbles = $this->consolidateBurst($bubbles, $maxBubbles, $maxChars);
        }

        return $bubbles;
    }

    /**
     * Nivel 2: Descompone un párrafo largo por saltos simples (\n).
     */
    protected function splitParagraphByLines(string $para, int $maxChars): array
    {
        $lines = preg_split('/\n+/u', $para);
        $chunks = [];
        $current = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (mb_strlen($line, 'UTF-8') > $maxChars) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                $subChunks = $this->splitLineBySentences($line, $maxChars);
                foreach ($subChunks as $sc) {
                    $chunks[] = $sc;
                }
                continue;
            }

            $candidate = $current === '' ? $line : $current . "\n" . $line;
            if (mb_strlen($candidate, 'UTF-8') <= $maxChars) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                $current = $line;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Nivel 3: Descompone una línea larga por signos de puntuación (. ! ?).
     */
    protected function splitLineBySentences(string $line, int $maxChars): array
    {
        // Separación por signos de puntuación final seguidos de espacio
        $sentences = preg_split('/(?<=[.!?])\s+/u', $line);
        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            if (mb_strlen($sentence, 'UTF-8') > $maxChars) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                $subChunks = $this->splitSentenceByWords($sentence, $maxChars);
                foreach ($subChunks as $sc) {
                    $chunks[] = $sc;
                }
                continue;
            }

            $candidate = $current === '' ? $sentence : $current . ' ' . $sentence;
            if (mb_strlen($candidate, 'UTF-8') <= $maxChars) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                $current = $sentence;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Nivel 4: Descompone oraciones continuas cortando en el último espacio antes de 400-450 chars.
     * Nunca corta palabras por la mitad a menos que sea un token continuo individual.
     */
    protected function splitSentenceByWords(string $sentence, int $maxChars): array
    {
        $words = preg_split('/\s+/u', $sentence);
        $chunks = [];
        $current = '';

        foreach ($words as $word) {
            if (mb_strlen($word, 'UTF-8') > $maxChars) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                $wordChunks = mb_str_split($word, $maxChars, 'UTF-8');
                foreach ($wordChunks as $wc) {
                    $chunks[] = $wc;
                }
                continue;
            }

            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate, 'UTF-8') <= $maxChars) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                $current = $word;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Consolida burbujas para respetar el límite de ráfaga estricto de máximo 3.
     * Si la respuesta requiere más de 3 fragmentos, sintetiza/agrupa los puntos restantes.
     */
    protected function consolidateBurst(array $bubbles, int $maxBubbles, int $maxChars): array
    {
        $result = array_slice($bubbles, 0, $maxBubbles - 1);
        $remaining = array_slice($bubbles, $maxBubbles - 1);
        $lastBubble = implode("\n", $remaining);

        if (mb_strlen($lastBubble, 'UTF-8') > $maxChars) {
            $cutCandidate = mb_substr($lastBubble, 0, $maxChars - 4, 'UTF-8');
            $lastBreak = max(
                (int) mb_strrpos($cutCandidate, "\n", 0, 'UTF-8'),
                (int) mb_strrpos($cutCandidate, ". ", 0, 'UTF-8'),
                (int) mb_strrpos($cutCandidate, " ", 0, 'UTF-8')
            );

            if ($lastBreak > 80) {
                $lastBubble = mb_substr($cutCandidate, 0, $lastBreak, 'UTF-8') . '...';
            } else {
                $lastBubble = $cutCandidate . '...';
            }
        }

        $result[] = $lastBubble;
        return $result;
    }
}
