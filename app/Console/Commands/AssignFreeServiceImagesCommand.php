<?php

namespace App\Console\Commands;

use App\Models\Service;
use Illuminate\Console\Command;

class AssignFreeServiceImagesCommand extends Command
{
    protected $signature = 'services:assign-free-images {--force : Sobrescribir imágenes existentes}';

    protected $description = 'Asigna imágenes libres de alta resolución a todos los servicios existentes del catálogo';

    public function handle(): int
    {
        $this->info('Consultando servicios en la base de datos...');

        $force = (bool) $this->option('force');
        $query = Service::withoutGlobalScopes();

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('image_url')
                    ->orWhere('image_url', '')
                    ->whereNull('image_path');
            });
        }

        $services = $query->get();
        $total = $services->count();

        if ($total === 0) {
            $this->info('Todos los servicios ya cuentan con una imagen asignada.');
            return self::SUCCESS;
        }

        $this->info("Asignando imágenes libres a {$total} servicios...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        foreach ($services as $service) {
            $freeImage = Service::resolveFallbackFreeImage(
                $service->name,
                $service->description
            );

            $service->update([
                'image_url' => $freeImage,
            ]);

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("¡Éxito! Se actualizaron {$updated} servicios con imágenes libres de alta resolución.");

        return self::SUCCESS;
    }
}
