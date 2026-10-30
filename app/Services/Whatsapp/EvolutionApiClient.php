<?php

namespace App\Services\Whatsapp;

use App\Services\Whatsapp\Contracts\MessengerGatewayInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class EvolutionApiClient implements MessengerGatewayInterface
{
    public function sendText(string $instance, string $phone, string $text): array
    {
        $number = $this->normalizePhone($phone);

        try {
            $response = $this->http(30)
                ->post("/message/sendText/{$instance}", [
                    'number' => $number,
                    'text' => $text,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('Evolution sendText failed', [
                'instance' => $instance,
                'phone' => $number,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al enviar mensaje por Evolution API: '.$e->getMessage(), 0, $e);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * Envía una imagen o archivo multimedia al destinatario vía Evolution API v2.
     *
     * Cumple estrictamente con la especificación oficial de Evolution API:
     * - 'number': dígitos numéricos limpios (sin '+', sin espacios) o JID de grupo (@g.us).
     * - 'mediatype': estrictamente 'image'.
     * - 'mimetype': acorde al formato real ('image/jpeg', 'image/png', 'image/webp', etc.).
     * - 'media': soporta URL directa, Base64 (Data URI 'data:image/...;base64,...' o puro), o rutas locales.
     * - Fallback a Base64: si la API falla al descargar la URL (por DNS, CORS, firewall de red o 400/500),
     *   se descarga el buffer en el backend y se reintenta inmediatamente como Base64.
     *
     * @return array<string, mixed>
     */
    public function sendImage(
        string $instance,
        string $phone,
        string $imageUrlOrPath,
        ?string $caption = null,
        ?string $fileName = null
    ): array {
        $number = $this->normalizePhone($phone);
        $captionText = trim((string) ($caption ?? ''));

        // 1. Resolver y preparar la imagen según su origen
        $resolved = $this->prepareMediaPayload($imageUrlOrPath, $fileName);
        $media = $resolved['media'];
        $mimeType = $resolved['mimeType'];
        $safeFileName = $resolved['fileName'];
        $isUrl = $resolved['is_url'];

        // Garantizar que si el media no es una URL pública, sea Base64 puro sin encabezado Data URI
        // ya que el validador oficial de Evolution API (@IsUrlOrBase64) falla si contiene 'data:...;base64,'
        if (is_string($media) && preg_match('/^data:[^;]+;base64,(.+)$/is', $media, $cleanMatches)) {
            $media = trim($cleanMatches[1]);
        }

        $payload = [
            'number' => $number,
            'mediatype' => 'image',
            'mimetype' => $mimeType,
            'caption' => $captionText,
            'media' => $media,
            'fileName' => $safeFileName,
        ];

        // 2. Ejecutar petición a Evolution API
        try {
            $response = $this->http(35)
                ->post("/message/sendMedia/{$instance}", $payload)
                ->throw()
                ->json();

            return is_array($response) ? $response : [];
        } catch (RequestException $e) {
            $status = $e->response?->status();
            $errorBody = $e->response?->json() ?? $e->response?->body();

            Log::error('Evolution sendImage failed', [
                'instance' => $instance,
                'phone' => $number,
                'http_status' => $status,
                'api_error' => $errorBody,
                'mediatype' => 'image',
                'mimetype' => $mimeType,
                'fileName' => $safeFileName,
                'is_url' => $isUrl,
                'media_preview' => is_string($media) ? substr($media, 0, 100) : null,
                'error' => $e->getMessage(),
            ]);

            // 3. Fallback inteligente: si se envió como URL y Evolution API no la pudo procesar
            // (e.g. host inaccesible desde contenedor Docker, 400/500, o timeout),
            // descargar el buffer en el backend, convertir a Base64 puro y reintentar.
            if ($isUrl && ! empty($imageUrlOrPath)) {
                $base64Fallback = $this->downloadUrlToBase64($imageUrlOrPath);
                if ($base64Fallback) {
                    Log::info('Evolution sendImage: Reintentando envío mediante fallback de Base64 buffer', [
                        'instance' => $instance,
                        'phone' => $number,
                        'original_url' => $imageUrlOrPath,
                    ]);

                    $fallbackPayload = $payload;
                    $fallbackPayload['media'] = $base64Fallback['media'];
                    $fallbackPayload['mimetype'] = $base64Fallback['mimeType'];
                    if (empty($fileName)) {
                        $fallbackPayload['fileName'] = $base64Fallback['fileName'];
                    }

                    try {
                        $fallbackResponse = $this->http(45)
                            ->post("/message/sendMedia/{$instance}", $fallbackPayload)
                            ->throw()
                            ->json();

                        return is_array($fallbackResponse) ? $fallbackResponse : [];
                    } catch (RequestException $retryException) {
                        Log::error('Evolution sendImage Base64 fallback also failed', [
                            'instance' => $instance,
                            'phone' => $number,
                            'http_status' => $retryException->response?->status(),
                            'api_error' => $retryException->response?->json() ?? $retryException->response?->body(),
                            'error' => $retryException->getMessage(),
                        ]);

                        throw new RuntimeException('Error al enviar imagen por Evolution API (incluso con fallback Base64): '.$retryException->getMessage(), 0, $retryException);
                    }
                }
            }

            throw new RuntimeException('Error al enviar imagen por Evolution API: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Prepara y normaliza el payload multimedia identificando Base64, URLs o rutas de Storage.
     *
     * @return array{media: string, mimeType: string, fileName: string, is_url: bool}
     */
    protected function prepareMediaPayload(string $imageUrlOrPath, ?string $fileName = null): array
    {
        $raw = trim($imageUrlOrPath);

        // Caso 1: Cadena Data URI Base64 directa (data:image/...;base64,...)
        if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/is', $raw, $matches)) {
            $mimeType = strtolower($matches[1]);
            $ext = $this->mimeToExtension($mimeType);
            $safeName = $fileName ?: ('image.'.$ext);

            return [
                'media' => trim($matches[2]),
                'mimeType' => $mimeType,
                'fileName' => $safeName,
                'is_url' => false,
            ];
        }

        // Caso 2: Base64 puro sin encabezado Data URI
        if (! str_contains($raw, ' ') && strlen($raw) > 120 && preg_match('/^[a-zA-Z0-9\/\r\n+]+=*$/', $raw)) {
            $binary = @base64_decode($raw, true);
            if ($binary !== false && strlen($binary) > 20) {
                $mimeType = $this->detectMimeType($binary);
                $ext = $this->mimeToExtension($mimeType);
                $safeName = $fileName ?: ('image.'.$ext);

                return [
                    'media' => $raw,
                    'mimeType' => $mimeType,
                    'fileName' => $safeName,
                    'is_url' => false,
                ];
            }
        }

        // Caso 3: URL HTTP o HTTPS
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $parsed = parse_url($raw);
            $pathPart = $parsed['path'] ?? '';
            $host = strtolower($parsed['host'] ?? '');
            $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1', 'laravel.test', 'basesistem.test'], true);

            // Si apunta a /storage/ resolver localmente para no depender de la resolución de red de Docker
            if (str_contains($pathPart, '/storage/')) {
                $storageRelative = ltrim(substr($pathPart, strpos($pathPart, '/storage/') + 9), '/');

                if (Storage::disk('public')->exists($storageRelative)) {
                    $fileContent = Storage::disk('public')->get($storageRelative);
                    $mimeType = Storage::disk('public')->mimeType($storageRelative) ?: $this->detectMimeType($fileContent, $storageRelative);

                    return [
                        'media' => base64_encode($fileContent),
                        'mimeType' => $mimeType,
                        'fileName' => $fileName ?: basename($storageRelative),
                        'is_url' => false,
                    ];
                }

                $publicDirect = public_path('storage/'.$storageRelative);
                if (file_exists($publicDirect)) {
                    $fileContent = (string) file_get_contents($publicDirect);
                    $mimeType = $this->detectMimeType($fileContent, $publicDirect);

                    return [
                        'media' => base64_encode($fileContent),
                        'mimeType' => $mimeType,
                        'fileName' => $fileName ?: basename($storageRelative),
                        'is_url' => false,
                    ];
                }
            }

            // Si es un host local pero no está en storage, descargarlo internamente y pasarlo como Base64
            if ($isLocalHost) {
                $downloaded = $this->downloadUrlToBase64($raw);
                if ($downloaded) {
                    return [
                        'media' => $downloaded['media'],
                        'mimeType' => $downloaded['mimeType'],
                        'fileName' => $fileName ?: $downloaded['fileName'],
                        'is_url' => false,
                    ];
                }
            }

            // URL externa o pública (ej. Unsplash, CDN, S3)
            $urlPath = $parsed['path'] ?? '';
            $ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
            $mimeType = match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                default => 'image/jpeg',
            };

            $baseName = basename($urlPath);
            $safeName = $fileName ?: ($baseName !== '' && str_contains($baseName, '.') ? $baseName : 'image.'.$ext);

            return [
                'media' => $raw,
                'mimeType' => $mimeType,
                'fileName' => $safeName,
                'is_url' => true,
            ];
        }

        // Caso 4: Ruta local en Storage o Filesystem
        if (Storage::disk('public')->exists($raw)) {
            $fileContent = Storage::disk('public')->get($raw);
            $mimeType = Storage::disk('public')->mimeType($raw) ?: $this->detectMimeType($fileContent, $raw);

            return [
                'media' => base64_encode($fileContent),
                'mimeType' => $mimeType,
                'fileName' => $fileName ?: basename($raw),
                'is_url' => false,
            ];
        }

        $localCandidates = [
            $raw,
            public_path($raw),
            public_path('storage/'.$raw),
            storage_path('app/public/'.$raw),
        ];

        foreach ($localCandidates as $candidate) {
            if (is_file($candidate)) {
                $fileContent = (string) file_get_contents($candidate);
                $mimeType = $this->detectMimeType($fileContent, $candidate);

                return [
                    'media' => base64_encode($fileContent),
                    'mimeType' => $mimeType,
                    'fileName' => $fileName ?: basename($candidate),
                    'is_url' => false,
                ];
            }
        }

        // Caso por defecto
        return [
            'media' => $raw,
            'mimeType' => 'image/jpeg',
            'fileName' => $fileName ?: 'image.jpeg',
            'is_url' => false,
        ];
    }

    /**
     * Descarga una imagen remota y la convierte en un buffer Base64 puro.
     *
     * @return array{media: string, mimeType: string, fileName: string}|null
     */
    protected function downloadUrlToBase64(string $url): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; EvolutionApiBot/1.0)',
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Evolution sendImage downloadUrlToBase64: status no exitoso al descargar imagen', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $binary = $response->body();
            if (empty($binary)) {
                return null;
            }

            $headerType = (string) $response->header('Content-Type');
            $cleanHeader = strtolower(trim(explode(';', $headerType)[0]));

            if (str_starts_with($cleanHeader, 'image/')) {
                $mimeType = $cleanHeader;
            } else {
                $mimeType = $this->detectMimeType($binary, parse_url($url, PHP_URL_PATH) ?? '');
            }

            $ext = $this->mimeToExtension($mimeType);
            $fileName = (basename(parse_url($url, PHP_URL_PATH) ?? '') ?: 'image').'.'.$ext;

            return [
                'media' => base64_encode($binary),
                'mimeType' => $mimeType,
                'fileName' => $fileName,
            ];
        } catch (Throwable $e) {
            Log::warning('Evolution sendImage downloadUrlToBase64 falló por excepción', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Detecta el tipo MIME de un binario o extensión.
     */
    protected function detectMimeType(string $binary, string $filename = ''): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mapped = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => null,
        };

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = @finfo_buffer($finfo, $binary);
                @finfo_close($finfo);
                if ($detected && str_starts_with($detected, 'image/')) {
                    return $detected;
                }
            }
        }

        return $mapped ?: 'image/jpeg';
    }

    /**
     * Mapea un MIME type a su extensión recomendada.
     */
    protected function mimeToExtension(string $mimeType): string
    {
        return match (strtolower(trim(explode(';', $mimeType)[0]))) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => 'jpeg',
        };
    }

    public function sendTyping(string $instance, string $phone): void
    {
        $number = $this->normalizePhone($phone);

        try {
            // Evolution API v2 — presencia "composing" (best-effort).
            $this->http(8)
                ->post("/chat/sendPresence/{$instance}", [
                    'number' => $number,
                    'presence' => 'composing',
                    'delay' => 0,
                ]);
        } catch (Throwable $e) {
            Log::debug('Evolution sendTyping failed (ignored)', [
                'instance' => $instance,
                'phone' => $number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function createInstance(string $name, ?string $webhookUrl = null): array
    {
        $secret = (string) config('services.evolution.webhook_secret');
        $webhookUrl = $webhookUrl ?: (string) config('services.evolution.webhook_url');

        $payload = [
            'instanceName' => $name,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ];

        if ($webhookUrl !== '') {
            $payload['webhook'] = [
                'url' => $webhookUrl,
                'byEvents' => false,
                'base64' => false,
                'events' => ['MESSAGES_UPSERT'],
                'headers' => [
                    'X-Evolution-Secret' => $secret,
                ],
            ];
        }

        try {
            $response = $this->http(60)
                ->post('/instance/create', $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('Evolution createInstance failed', [
                'instance' => $name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al crear instancia en Evolution API: '.$e->getMessage(), 0, $e);
        }

        try {
            $this->setGroupsIgnore($name, true);
        } catch (Throwable $e) {
            Log::warning('Evolution groupsIgnore no se pudo aplicar al crear', [
                'instance' => $name,
                'error' => $e->getMessage(),
            ]);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * Evita que Evolution procese mensajes de grupos.
     *
     * @return array<string, mixed>
     */
    public function setGroupsIgnore(string $name, bool $ignore = true): array
    {
        try {
            $response = $this->http(30)
                ->post("/settings/set/{$name}", [
                    'rejectCall' => false,
                    'msgCall' => '',
                    'groupsIgnore' => $ignore,
                    'alwaysOnline' => false,
                    'readMessages' => false,
                    'readStatus' => false,
                    'syncFullHistory' => false,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('Evolution setGroupsIgnore failed', [
                'instance' => $name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al configurar groupsIgnore en Evolution: '.$e->getMessage(), 0, $e);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function connect(string $name): array
    {
        try {
            $response = $this->http(60)
                ->get("/instance/connect/{$name}")
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('Evolution connect failed', [
                'instance' => $name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al conectar instancia en Evolution API: '.$e->getMessage(), 0, $e);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionState(string $name): array
    {
        try {
            $response = $this->http(30)
                ->get("/instance/connectionState/{$name}")
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::error('Evolution connectionState failed', [
                'instance' => $name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Error al consultar estado en Evolution API: '.$e->getMessage(), 0, $e);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * Comprueba si la instancia existe en Evolution API sin lanzar excepción.
     */
    public function instanceExists(string $name): bool
    {
        try {
            $http = $this->http(15);
            $response = $http->get("/instance/connectionState/{$name}");

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function sendPresence(string $instance, string $phone, string $presence = 'composing', int $delay = 1500): void
    {
        $number = $this->normalizePhone($phone);

        try {
            $this->http(8)
                ->post("/chat/sendPresence/{$instance}", [
                    'number' => $number,
                    'presence' => $presence,
                    'delay' => $delay,
                ]);
        } catch (Throwable $e) {
            Log::debug('Evolution sendPresence failed (ignored)', [
                'instance' => $instance,
                'phone' => $number,
                'presence' => $presence,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Descarga el Base64 de un mensaje de medios (audio, imagen, documento) desde Evolution API v2.
     *
     * @param  array<string, mixed>  $message
     */
    public function downloadMediaBase64(string $instance, array $message): ?string
    {
        // 1. Si el webhook ya incluye base64 directo
        if (! empty($message['base64'])) {
            return (string) $message['base64'];
        }

        // 2. Consultar el endpoint de Evolution v2 getBase64FromMediaMessage
        try {
            $payload = [
                'message' => $message,
                'convertToMp4' => false,
            ];

            $response = $this->http(45)
                ->post("/chat/getBase64FromMediaMessage/{$instance}", $payload)
                ->throw()
                ->json();

            if (is_array($response) && ! empty($response['base64'])) {
                return (string) $response['base64'];
            }
        } catch (Throwable $e) {
            Log::warning('Evolution downloadMediaBase64 failed', [
                'instance' => $instance,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        // Si es un JID de grupo de WhatsApp (@g.us), conservar el identificador de grupo limpio
        if (str_ends_with($phone, '@g.us')) {
            return preg_replace('/[^\d\-@.a-zA-Z]/', '', $phone) ?? $phone;
        }

        // Para números individuales, remover sufijos de WhatsApp como @s.whatsapp.net o @c.us
        $phone = preg_replace('/@.+$/', '', $phone) ?? $phone;

        // Remover '+' inicial, espacios, paréntesis, guiones; solo dígitos puros
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }

    protected function http(int $timeout): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.evolution.base_url'), '/');
        $apiKey = (string) config('services.evolution.api_key');

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('EVOLUTION_BASE_URL o EVOLUTION_API_KEY no están configuradas.');
        }

        return Http::baseUrl($baseUrl)
            ->withHeaders(['apikey' => $apiKey])
            ->acceptJson()
            ->timeout($timeout);
    }
}
