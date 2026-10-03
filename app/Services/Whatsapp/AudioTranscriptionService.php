<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AudioTranscriptionService
{
    /**
     * Transcribe un audio codificado en Base64 o binario usando Deepgram, Groq o OpenAI.
     */
    public function transcribe(string $audioData, string $format = 'ogg', ?string $driver = null): string
    {
        $driver = $driver ?? (string) config('services.whatsapp.transcription_driver', 'deepgram');

        // Si viene en base64 puro o con prefijo data:audio/..., decodificar
        if (str_starts_with($audioData, 'data:')) {
            $parts = explode(',', $audioData, 2);
            $binary = base64_decode($parts[1] ?? '', true);
        } elseif (base64_encode(base64_decode($audioData, true) ?: '') === $audioData) {
            $binary = base64_decode($audioData, true);
        } else {
            $binary = $audioData;
        }

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('El contenido de audio recibido está vacío o es inválido.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'wh_audio_').'.'.$format;
        file_put_contents($tempFile, $binary);

        try {
            if ($driver === 'deepgram') {
                return $this->transcribeWithDeepgram($tempFile, $format);
            }

            if ($driver === 'openai') {
                return $this->transcribeWithOpenAi($tempFile);
            }

            return $this->transcribeWithGroq($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Transcripción de ultra-baja latencia con Deepgram Nova (~150-250ms).
     */
    protected function transcribeWithDeepgram(string $filePath, string $format = 'ogg'): string
    {
        $apiKey = (string) config('services.deepgram.key');
        $baseUrl = rtrim((string) config('services.deepgram.base_url', 'https://api.deepgram.com/v1'), '/');
        $model = (string) config('services.deepgram.model', 'nova-3');
        $language = (string) config('services.deepgram.language', 'es');

        if ($apiKey === '') {
            // Fallback transparente a Groq u OpenAI si Deepgram no está configurado
            if ((string) config('services.groq.key') !== '') {
                return $this->transcribeWithGroq($filePath);
            }
            if ((string) config('services.openai.key') !== '') {
                return $this->transcribeWithOpenAi($filePath);
            }
            throw new RuntimeException('DEEPGRAM_API_KEY no está configurada.');
        }

        $mimeType = 'audio/ogg';
        if ($format === 'mp3') {
            $mimeType = 'audio/mp3';
        } elseif ($format === 'wav') {
            $mimeType = 'audio/wav';
        } elseif ($format === 'm4a' || $format === 'mp4') {
            $mimeType = 'audio/mp4';
        }

        $audioContent = file_get_contents($filePath);
        if ($audioContent === false) {
            throw new RuntimeException("No se pudo leer el archivo de audio: {$filePath}");
        }

        try {
            $url = "{$baseUrl}/listen?".http_build_query([
                'model' => $model,
                'language' => $language,
                'smart_format' => 'true',
                'punctuate' => 'true',
            ]);

            $response = Http::withHeaders([
                'Authorization' => "Token {$apiKey}",
                'Content-Type' => $mimeType,
            ])
                ->timeout(30)
                ->withBody($audioContent, $mimeType)
                ->post($url)
                ->throw()
                ->json();

            $transcript = (string) data_get(
                $response,
                'results.channels.0.alternatives.0.transcript',
                ''
            );

            return trim($transcript);
        } catch (RequestException $e) {
            Log::error('Deepgram transcription failed', [
                'error' => $e->getMessage(),
                'response' => $e->response ? $e->response->body() : null,
            ]);
            throw new RuntimeException('Error al transcribir audio con Deepgram: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Transcripción ultrarrápida con Groq Whisper (~300ms).
     */
    protected function transcribeWithGroq(string $filePath): string
    {
        $apiKey = (string) config('services.groq.key');
        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');
        $model = (string) config('services.groq.whisper_model', 'whisper-large-v3-turbo');

        if ($apiKey === '') {
            // Fallback a OpenAI si no hay Groq key
            if ((string) config('services.openai.key') !== '') {
                return $this->transcribeWithOpenAi($filePath);
            }
            throw new RuntimeException('GROQ_API_KEY o OPENAI_API_KEY no están configuradas para transcripción de audio.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->timeout(45)
                ->attach('file', fopen($filePath, 'r'), basename($filePath))
                ->post('/audio/transcriptions', [
                    'model' => $model,
                    'language' => 'es',
                    'response_format' => 'json',
                    'temperature' => 0.0,
                ])
                ->throw()
                ->json();

            return trim((string) ($response['text'] ?? ''));
        } catch (RequestException $e) {
            Log::error('Groq Whisper transcription failed', [
                'error' => $e->getMessage(),
                'response' => $e->response ? $e->response->body() : null,
            ]);
            throw new RuntimeException('Error al transcribir audio con Groq: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Transcripción con OpenAI Whisper.
     */
    protected function transcribeWithOpenAi(string $filePath): string
    {
        $apiKey = (string) config('services.openai.key');
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada para transcripción de audio.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->timeout(60)
                ->attach('file', fopen($filePath, 'r'), basename($filePath))
                ->post('/audio/transcriptions', [
                    'model' => 'whisper-1',
                    'language' => 'es',
                    'response_format' => 'json',
                ])
                ->throw()
                ->json();

            return trim((string) ($response['text'] ?? ''));
        } catch (RequestException $e) {
            Log::error('OpenAI Whisper transcription failed', [
                'error' => $e->getMessage(),
                'response' => $e->response ? $e->response->body() : null,
            ]);
            throw new RuntimeException('Error al transcribir audio con OpenAI: '.$e->getMessage(), 0, $e);
        }
    }
}
