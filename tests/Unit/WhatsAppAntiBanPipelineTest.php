<?php

namespace Tests\Unit;

use App\Services\Whatsapp\EvolutionApiService;
use App\Services\Whatsapp\WhatsAppAntiBanPipeline;
use PHPUnit\Framework\TestCase;

class WhatsAppAntiBanPipelineTest extends TestCase
{
    protected WhatsAppAntiBanPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();
        $evolutionMock = $this->createMock(EvolutionApiService::class);
        $this->pipeline = new WhatsAppAntiBanPipeline($evolutionMock);
    }

    public function test_short_message_stays_single_bubble(): void
    {
        $text = "Hola, ¿cómo estás? He agendado tu cita para mañana a las 10:00 AM.";
        $chunks = $this->pipeline->chunkMessage($text, 450, 3);

        $this->assertCount(1, $chunks);
        $this->assertEquals($text, $chunks[0]);
    }

    public function test_chunks_split_by_double_newline_paragraphs(): void
    {
        $para1 = str_repeat("Texto del primer párrafo informativo. ", 7); // ~266 chars
        $para2 = str_repeat("Texto del segundo párrafo de respuesta. ", 7); // ~273 chars
        $text = "{$para1}\n\n{$para2}";

        $chunks = $this->pipeline->chunkMessage($text, 450, 3);

        $this->assertCount(2, $chunks);
        $this->assertLessThanOrEqual(450, mb_strlen($chunks[0]));
        $this->assertLessThanOrEqual(450, mb_strlen($chunks[1]));
        $this->assertEquals(trim($para1), $chunks[0]);
        $this->assertEquals(trim($para2), $chunks[1]);
    }

    public function test_chunks_split_by_single_newline_for_lists(): void
    {
        $item1 = "- Elemento 1: " . str_repeat("detalles importantes ", 10);
        $item2 = "- Elemento 2: " . str_repeat("detalles adicionales ", 10);
        $item3 = "- Elemento 3: " . str_repeat("más información útil ", 10);
        $text = "{$item1}\n{$item2}\n{$item3}";

        $chunks = $this->pipeline->chunkMessage($text, 350, 3);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(350, mb_strlen($chunk));
        }
    }

    public function test_chunks_split_by_sentence_punctuation(): void
    {
        $sent1 = "Primera oración completa con suficiente texto para abarcar espacio.";
        $sent2 = "Segunda oración que complementa la idea con argumentos adicionales.";
        $sent3 = "Tercera oración que concluye con un mensaje relevante.";
        $text = "{$sent1} {$sent2} {$sent3}";

        $chunks = $this->pipeline->chunkMessage($text, 120, 3);

        $this->assertGreaterThanOrEqual(2, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(120, mb_strlen($chunk));
        }
    }

    public function test_burst_limit_caps_at_maximum_three_bubbles(): void
    {
        // 6 párrafos largos
        $paras = [];
        for ($i = 1; $i <= 6; $i++) {
            $paras[] = "Párrafo {$i}: " . str_repeat("información detallada para probar el límite de ráfaga ", 6);
        }
        $longText = implode("\n\n", $paras);

        $chunks = $this->pipeline->chunkMessage($longText, 450, 3);

        $this->assertLessThanOrEqual(3, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(450, mb_strlen($chunk));
        }
    }

    public function test_continuous_sentence_cuts_at_last_space_before_limit_without_breaking_words(): void
    {
        $words = [];
        for ($i = 1; $i <= 100; $i++) {
            $words[] = "palabra{$i}";
        }
        $textWithoutPunctuation = implode(" ", $words); // ~950 chars

        $chunks = $this->pipeline->chunkMessage($textWithoutPunctuation, 450, 3);

        $this->assertLessThanOrEqual(3, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(450, mb_strlen($chunk));
            // Asegurarse de que no corte a la mitad de una palabra (e.g. "palab")
            $lastWord = substr($chunk, strrpos($chunk, ' ') + 1);
            $this->assertTrue(
                str_starts_with($lastWord, 'palabra') || str_ends_with($lastWord, '...'),
                "La última palabra '{$lastWord}' no debe estar cortada a la mitad."
            );
        }
    }

    public function test_typing_delay_stays_within_human_bounds(): void
    {
        $shortDelay = $this->pipeline->calculateTypingDelay("Ok");
        $mediumDelay = $this->pipeline->calculateTypingDelay(str_repeat("Prueba de delay ", 8));
        $longDelay = $this->pipeline->calculateTypingDelay(str_repeat("Texto largo simulando respuesta ", 15));

        $this->assertGreaterThanOrEqual(1000, $shortDelay);
        $this->assertLessThanOrEqual(3000, $shortDelay);

        $this->assertGreaterThanOrEqual(1000, $mediumDelay);
        $this->assertLessThanOrEqual(3000, $mediumDelay);

        $this->assertGreaterThanOrEqual(1000, $longDelay);
        $this->assertLessThanOrEqual(3000, $longDelay);
    }
}
