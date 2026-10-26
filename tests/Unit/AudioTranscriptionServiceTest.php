<?php

namespace Tests\Unit;

use App\Services\Assistant\AssistantOrchestratorService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AudioTranscriptionServiceTest extends TestCase
{
    protected AssistantOrchestratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssistantOrchestratorService::class);
    }

    public function test_transcribe_audio_throws_exception_on_empty_or_missing_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->transcribeAudio('/tmp/non_existent_audio_file.ogg');
    }

    public function test_transcribe_audio_throws_exception_on_zero_byte_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_empty_') . '.ogg';
        file_put_contents($tmp, '');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->service->transcribeAudio($tmp);
        } finally {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function test_transcribe_audio_uses_groq_whisper_as_primary(): void
    {
        Config::set('services.transcription.driver', 'groq');
        Config::set('services.groq.api_key', 'test_groq_key');
        Config::set('services.deepgram.key', 'test_deepgram_key');

        Http::fake([
            'https://api.groq.com/openai/v1/audio/transcriptions' => Http::response([
                'text' => 'Transcripción exitosa con Groq Whisper.',
            ], 200),
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'test_audio_') . '.wav';
        file_put_contents($tmp, 'RIFF' . str_repeat('A', 50));

        try {
            $result = $this->service->transcribeAudio($tmp);
            $this->assertEquals('Transcripción exitosa con Groq Whisper.', $result);

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'api.groq.com');
            });
        } finally {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function test_transcribe_audio_falls_back_to_deepgram_when_groq_fails(): void
    {
        Config::set('services.transcription.driver', 'groq');
        Config::set('services.groq.api_key', 'test_groq_key');
        Config::set('services.deepgram.key', 'test_deepgram_key');

        Http::fake([
            'https://api.groq.com/openai/v1/audio/transcriptions' => Http::response([
                'error' => 'Rate limit or failure',
            ], 500),
            'https://api.deepgram.com/v1/listen*' => Http::response([
                'results' => [
                    'channels' => [
                        [
                            'alternatives' => [
                                ['transcript' => 'Transcripción exitosa con Deepgram fallback.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'test_audio_') . '.wav';
        file_put_contents($tmp, 'RIFF' . str_repeat('A', 50));

        try {
            $result = $this->service->transcribeAudio($tmp);
            $this->assertEquals('Transcripción exitosa con Deepgram fallback.', $result);

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'api.deepgram.com');
            });
        } finally {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function test_transcribe_audio_prioritizes_deepgram_when_driver_is_deepgram(): void
    {
        Config::set('services.transcription.driver', 'deepgram');
        Config::set('services.groq.api_key', 'test_groq_key');
        Config::set('services.deepgram.key', 'test_deepgram_key');

        Http::fake([
            'https://api.deepgram.com/v1/listen*' => Http::response([
                'results' => [
                    'channels' => [
                        [
                            'alternatives' => [
                                ['transcript' => 'Transcripción directa con Deepgram.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'test_audio_') . '.wav';
        file_put_contents($tmp, 'RIFF' . str_repeat('A', 50));

        try {
            $result = $this->service->transcribeAudio($tmp);
            $this->assertEquals('Transcripción directa con Deepgram.', $result);

            // Groq should NOT be called when Deepgram succeeds first
            Http::assertNotSent(function ($request) {
                return str_contains($request->url(), 'api.groq.com');
            });
        } finally {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }
}
