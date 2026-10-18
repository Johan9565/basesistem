<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class AssignFreeProductImagesCommand extends Command
{
    protected $signature = 'products:assign-free-images {--force : Sobrescribir imágenes existentes}';

    protected $description = 'Asigna imágenes libres de alta resolución a todos los productos existentes del catálogo';

    public function handle(): int
    {
        $this->info('Consultando productos en la base de datos...');

        $force = (bool) $this->option('force');
        $query = Product::withoutGlobalScopes();

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('image_url')
                    ->orWhere('image_url', '')
                    ->whereNull('image_path');
            });
        }

        $products = $query->get();
        $total = $products->count();

        if ($total === 0) {
            $this->info('Todos los productos ya cuentan con una imagen asignada.');
            return self::SUCCESS;
        }

        $this->info("Asignando imágenes libres a {$total} productos...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        foreach ($products as $product) {
            $freeImage = Product::resolveFallbackFreeImage(
                $product->name,
                $product->category,
                $product->sku
            );

            $product->update([
                'image_url' => $freeImage,
            ]);

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("¡Éxito! Se actualizaron {$updated} productos con imágenes libres de alta resolución.");

        return self::SUCCESS;
    }
}
