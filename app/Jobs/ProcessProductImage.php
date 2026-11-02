<?php

namespace App\Jobs;

use App\Models\ProductImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Procesa una imagen de producto para optimizarla para distribución vía WhatsApp:
 * - Redimensiona proporcionalmente (lado más largo ≤ 1280px).
 * - Limpia metadatos EXIF innecesarios.
 * - Convierte a WebP si GD lo soporta, o guarda como JPEG progresivo (calidad 82).
 */
class ProcessProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Máximo de reintentos antes de marcar como fallido. */
    public int $tries = 3;

    /** Timeout en segundos para el procesamiento. */
    public int $timeout = 120;

    /** Lado más largo permitido en px. */
    private const MAX_DIMENSION = 1280;

    /** Calidad de compresión (82 = buen balance entre calidad y peso). */
    private const QUALITY = 82;

    /** ID de la imagen a procesar. */
    public string $productImageId;

    public function __construct(string $productImageId)
    {
        $this->productImageId = $productImageId;
    }

    public function handle(): void
    {
        $productImage = ProductImage::find($this->productImageId);

        if (! $productImage) {
            Log::warning("[ProcessProductImage] ProductImage {$this->productImageId} no encontrada, se omite.");

            return;
        }

        if ($productImage->is_processed) {
            return;
        }

        $disk = Storage::disk('public');
        $originalPath = $productImage->path;

        if (! $disk->exists($originalPath)) {
            Log::warning("[ProcessProductImage] Archivo no encontrado en storage: {$originalPath}");
            $productImage->delete();

            return;
        }

        $absolutePath = $disk->path($originalPath);

        try {
            $this->processImage($absolutePath, $productImage);
        } catch (\Throwable $e) {
            Log::error("[ProcessProductImage] Error procesando {$originalPath}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Procesa la imagen: redimensiona, limpia EXIF y convierte a formato optimizado.
     */
    private function processImage(string $absolutePath, ProductImage $productImage): void
    {
        if (! extension_loaded('gd')) {
            Log::warning('[ProcessProductImage] Extensión GD no disponible. Marcando como procesada sin optimizar.');
            $productImage->update(['is_processed' => true]);

            return;
        }

        $imageInfo = @getimagesize($absolutePath);
        if ($imageInfo === false) {
            Log::warning("[ProcessProductImage] No se pudo leer la imagen: {$absolutePath}");
            $productImage->delete();
            Storage::disk('public')->delete($productImage->path);

            return;
        }

        [$origWidth, $origHeight, $type] = $imageInfo;

        // Crear recurso GD según tipo de imagen
        $source = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = @imagecreatefromjpeg($absolutePath);
                break;
            case IMAGETYPE_PNG:
                $source = @imagecreatefrompng($absolutePath);
                break;
            case IMAGETYPE_WEBP:
                $source = @imagecreatefromwebp($absolutePath);
                break;
        }

        if ($source === false) {
            Log::warning("[ProcessProductImage] Formato no soportado o imagen corrupta: {$absolutePath}");
            $productImage->delete();
            Storage::disk('public')->delete($productImage->path);

            return;
        }

        // Redimensionar si excede el límite
        $maxSide = max($origWidth, $origHeight);
        if ($maxSide > self::MAX_DIMENSION) {
            $ratio = self::MAX_DIMENSION / $maxSide;
            $newWidth = (int) round($origWidth * $ratio);
            $newHeight = (int) round($origHeight * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);

            // Preservar transparencia para PNG
            if ($type === IMAGETYPE_PNG) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
            }

            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagedestroy($source);
            $source = $resized;
        }

        // Determinar formato de salida y nueva extensión
        $supportsWebp = function_exists('imagewebp');
        $directory = pathinfo($productImage->path, PATHINFO_DIRNAME);
        $baseName = pathinfo($productImage->path, PATHINFO_FILENAME);

        if ($supportsWebp) {
            $newExtension = 'webp';
            $newPath = $directory.'/'.$baseName.'.webp';
            $newAbsolutePath = Storage::disk('public')->path($newPath);

            imagewebp($source, $newAbsolutePath, self::QUALITY);
            $mimeType = 'image/webp';
        } else {
            // Fallback: JPEG progresivo
            $newExtension = 'jpg';
            $newPath = $directory.'/'.$baseName.'.jpg';
            $newAbsolutePath = Storage::disk('public')->path($newPath);

            imageinterlace($source, true); // JPEG progresivo
            imagejpeg($source, $newAbsolutePath, self::QUALITY);
            $mimeType = 'image/jpeg';
        }

        imagedestroy($source);

        // Eliminar el archivo original si cambió de nombre
        if ($newPath !== $productImage->path) {
            Storage::disk('public')->delete($productImage->path);
        }

        // Actualizar el registro en BD
        $productImage->update([
            'path' => $newPath,
            'mime_type' => $mimeType,
            'size' => filesize($newAbsolutePath),
            'is_processed' => true,
        ]);

        Log::info("[ProcessProductImage] Imagen procesada: {$newPath} ({$mimeType}, ".round(filesize($newAbsolutePath) / 1024).'KB)');
    }
}

