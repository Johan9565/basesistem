<?php

namespace App\Services\Whatsapp;

use App\Services\Whatsapp\Contracts\MessengerGatewayInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class MessageDispatcher
{
    /** @var MessengerGatewayInterface|EvolutionApiClient */
    protected $gateway;

    public function __construct(MessengerGatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Despacha una respuesta al usuario, aplicando división por párrafos y simulación de tipeo si está configurado.
     *
     * @return list<array<string, mixed>> Respuestas del gateway para cada mensaje
     */
    public function dispatch(string $instance, string $phone, string $fullText): array
    {
        $cleanText = trim($fullText);
        if ($cleanText === '') {
            return [];
        }

        $shouldSplit = (bool) config('services.whatsapp.split_messages', true);
        $chunks = $shouldSplit ? $this->splitIntoParagraphs($cleanText) : [$cleanText];

        $results = [];
        $total = count($chunks);
        $humanTyping = (bool) config('services.whatsapp.human_typing', true);
        $interDelayMs = (int) config('services.whatsapp.split_delay_ms', 1000);

        foreach ($chunks as $index => $chunk) {
            $textChunk = trim($chunk);
            if ($textChunk === '') {
                continue;
            }

            if ($humanTyping) {
                $this->simulateTyping($instance, $phone, $textChunk);
            }

            try {
                $res = $this->gateway->sendText($instance, $phone, $textChunk);
                $results[] = $res;
            } catch (Throwable $e) {
                Log::error('MessageDispatcher sendText failed', [
                    'instance' => $instance,
                    'phone' => $phone,
                    'chunk_index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }

            // Si hay más burbujas pendientes, esperar un intervalo breve
            if ($index < $total - 1 && $interDelayMs > 0) {
                usleep($interDelayMs * 1000);
            }
        }

        return $results;
    }

    /**
     * Divide el texto en párrafos naturales (respetando saltos de línea dobles y listas).
     *
     * @return list<string>
     */
    public function splitIntoParagraphs(string $text): array
    {
        // Normalizar saltos de línea
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);

        // Dividir por 2 o más saltos de línea
        $rawParagraphs = preg_split('/\n{2,}/', $normalized);
        if (! is_array($rawParagraphs) || count($rawParagraphs) <= 1) {
            return [trim($text)];
        }

        $chunks = [];
        foreach ($rawParagraphs as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $chunks[] = $p;
        }

        return $chunks !== [] ? $chunks : [trim($text)];
    }

    /**
     * Simula el evento "Escribiendo..." con delay orgánico según los caracteres del párrafo.
     */
    protected function simulateTyping(string $instance, string $phone, string $chunk): void
    {
        try {
            if (method_exists($this->gateway, 'sendPresence')) {
                $this->gateway->sendPresence($instance, $phone, 'composing', 1500);
            } elseif (method_exists($this->gateway, 'sendTyping')) {
                $this->gateway->sendTyping($instance, $phone);
            }

            // Delay orgánico proporcional (mínimo 1.2s, máximo 2.8s)
            $charCount = mb_strlen($chunk);
            $delayMs = min(2800, max(1200, $charCount * 25));
            usleep($delayMs * 1000);
        } catch (Throwable $e) {
            Log::debug('simulateTyping failed (ignored)', [
                'instance' => $instance,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
