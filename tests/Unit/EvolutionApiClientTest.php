<?php

namespace Tests\Unit;

use App\Services\Whatsapp\EvolutionApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvolutionApiClientTest extends TestCase
{
    protected EvolutionApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.evolution.base_url', 'http://evolution-test:8080');
        Config::set('services.evolution.api_key', 'test-api-key');

        $this->client = new EvolutionApiClient();
    }

    public function test_normalize_phone_cleans_plus_spaces_and_suffixes(): void
    {
        // Limpieza de signo +, espacios, guiones y paréntesis
        $this->assertEquals('5215512345678', $this->client->normalizePhone('+52 1 (55) 1234-5678'));

        // Limpieza de sufijo individual WhatsApp
        $this->assertEquals('5219991234567', $this->client->normalizePhone('5219991234567@s.whatsapp.net'));
        $this->assertEquals('5219991234567', $this->client->normalizePhone('5219991234567@c.us'));

        // Conserva JID de grupo de WhatsApp
        $this->assertEquals('120363024893123456@g.us', $this->client->normalizePhone('120363024893123456@g.us'));
    }

    public function test_send_image_with_base64_data_uri(): void
    {
        Http::fake([
            'http://evolution-test:8080/message/sendMedia/test-instance' => Http::response([
                'key' => ['id' => 'MSG123'],
                'status' => 'PENDING',
            ], 200),
        ]);

        $pureBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $base64Data = 'data:image/png;base64,' . $pureBase64;
        $result = $this->client->sendImage('test-instance', '+52 1 999 123 4567', $base64Data, 'Mi Imagen Base64');

        $this->assertEquals('PENDING', $result['status'] ?? null);

        Http::assertSent(function (Request $request) use ($pureBase64) {
            $data = $request->data();

            return $request->url() === 'http://evolution-test:8080/message/sendMedia/test-instance'
                && $data['number'] === '5219991234567'
                && $data['mediatype'] === 'image'
                && $data['mimetype'] === 'image/png'
                && $data['caption'] === 'Mi Imagen Base64'
                && $data['media'] === $pureBase64
                && str_ends_with($data['fileName'], '.png');
        });
    }

    public function test_send_image_with_public_url_successful(): void
    {
        Http::fake([
            'http://evolution-test:8080/message/sendMedia/test-instance' => Http::response([
                'key' => ['id' => 'MSG456'],
                'status' => 'PENDING',
            ], 200),
        ]);

        $imageUrl = 'https://example.com/catalog/zapato.jpg';
        $result = $this->client->sendImage('test-instance', '5219998887777', $imageUrl, 'Zapato de piel', 'zapato.jpg');

        $this->assertEquals('PENDING', $result['status'] ?? null);

        Http::assertSent(function (Request $request) use ($imageUrl) {
            $data = $request->data();

            return $data['number'] === '5219998887777'
                && $data['mediatype'] === 'image'
                && $data['mimetype'] === 'image/jpeg'
                && $data['media'] === $imageUrl
                && $data['fileName'] === 'zapato.jpg';
        });
    }

    public function test_send_image_falls_back_to_base64_buffer_when_url_fails_in_api(): void
    {
        $imageUrl = 'https://example.com/catalog/oferta.png';
        $pureBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $pngBinary = base64_decode($pureBase64);

        $callCount = 0;
        Http::fake([
            // Primer envío a Evolution API falla simulando que Evolution API no pudo descargar la URL
            'http://evolution-test:8080/message/sendMedia/test-instance' => function (Request $request) use (&$callCount, $pureBase64) {
                $callCount++;
                if ($callCount === 1) {
                    return Http::response([
                        'status' => 400,
                        'message' => 'Cannot download media from url',
                    ], 400);
                }

                // Segundo envío (fallback con Base64 puro) tiene éxito
                $this->assertEquals($pureBase64, $request->data()['media']);

                return Http::response([
                    'key' => ['id' => 'MSG-FALLBACK-OK'],
                    'status' => 'PENDING',
                ], 200);
            },
            // Descarga de la imagen remota por parte de nuestro backend
            'https://example.com/catalog/oferta.png' => Http::response($pngBinary, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $result = $this->client->sendImage('test-instance', '5215500001111', $imageUrl, 'Gran Oferta');

        $this->assertEquals(2, $callCount, 'Debe haber reintentado el envío tras la falla inicial de la URL');
        $this->assertEquals('PENDING', $result['status'] ?? null);
    }

    public function test_send_image_with_storage_disk_file_automatically_converts_to_base64(): void
    {
        Storage::fake('public');
        $pureBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $dummyImage = base64_decode($pureBase64);
        Storage::disk('public')->put('products/camisa.png', $dummyImage);

        Http::fake([
            'http://evolution-test:8080/message/sendMedia/test-instance' => Http::response([
                'key' => ['id' => 'MSG-LOCAL-OK'],
                'status' => 'PENDING',
            ], 200),
        ]);

        $result = $this->client->sendImage('test-instance', '5219991112222', 'products/camisa.png', 'Camisa elegante');

        $this->assertEquals('PENDING', $result['status'] ?? null);

        Http::assertSent(function (Request $request) use ($pureBase64) {
            $data = $request->data();

            return $data['media'] === $pureBase64
                && $data['mediatype'] === 'image'
                && $data['fileName'] === 'camisa.png';
        });
    }

    public function test_send_image_with_localhost_storage_url_resolves_directly_to_pure_base64(): void
    {
        Storage::fake('public');
        $pureBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $dummyImage = base64_decode($pureBase64);
        Storage::disk('public')->put('products/caja.jpg', $dummyImage);

        Http::fake([
            'http://evolution-test:8080/message/sendMedia/test-instance' => Http::response([
                'key' => ['id' => 'MSG-CAJA-OK'],
                'status' => 'PENDING',
            ], 200),
        ]);

        $localUrl = 'http://localhost:8000/storage/products/caja.jpg';
        $result = $this->client->sendImage('test-instance', '5219991112222', $localUrl, 'Caja de Aniversario');

        $this->assertEquals('PENDING', $result['status'] ?? null);

        Http::assertSent(function (Request $request) use ($pureBase64) {
            $data = $request->data();

            return $data['media'] === $pureBase64
                && $data['mediatype'] === 'image'
                && str_contains($data['fileName'], 'caja.jpg');
        });
    }

    public function test_send_image_throws_exception_with_api_details_on_persistent_failure(): void
    {
        Http::fake([
            'http://evolution-test:8080/message/sendMedia/test-instance' => Http::response([
                'status' => 500,
                'error' => 'Internal Server Error',
                'message' => 'Baileys session disconnected',
            ], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error al enviar imagen por Evolution API');

        $this->client->sendImage('test-instance', '5219991112222', 'data:image/jpeg;base64,xxxx');
    }
}
