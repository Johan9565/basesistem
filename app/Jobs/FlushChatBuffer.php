<?php

namespace App\Jobs;

use App\Services\Whatsapp\AppointmentOrchestrator;
use App\Services\Whatsapp\ChatStateStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Búfer anti-metralleta: espera a que la ventana de acumulación quede en
 * reposo (sin mensajes nuevos), consolida todos los textos acumulados en un
 * solo bloque y lo despacha una única vez al orquestador de IA.
 */
class FlushChatBuffer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function __construct(
        public string $instanceName,
        public string $phone,
    ) {
    }

    public function handle(ChatStateStore $store, AppointmentOrchestrator $orchestrator): void
    {
        $window = max(0, (int) config('services.whatsapp.buffer_window_seconds', 8));
        $lock = Cache::lock("wa:flush:{$this->instanceName}:{$this->phone}", 60);

        if (! $lock->get()) {
            return; // Otro flush está consolidando este chat.
        }

        try {
            $texts = $store->pullBufferIfIdle($this->instanceName, $this->phone, $window);

            if ($texts === null) {
                // La ventana sigue viva: cada mensaje nuevo la reinicia, así
                // que se reagenda el flush para el tiempo restante.
                $remaining = $store->bufferIdleIn($this->instanceName, $this->phone, $window);
                self::dispatch($this->instanceName, $this->phone)->delay(max(1, (int) ceil($remaining)));

                return;
            }

            if ($texts === []) {
                return; // Ya consolidado por otro flush.
            }

            $sessionId = $store->sessionId($this->instanceName, $this->phone);
            if ($sessionId === null) {
                Log::info('FlushChatBuffer: sesión expiró durante la ventana; se omite la respuesta', [
                    'instance' => $this->instanceName,
                    'phone' => $this->phone,
                ]);

                return;
            }

            // Consolidación de la ráfaga en un solo bloque para la IA.
            $orchestrator->respondToBuffered(
                $this->instanceName,
                $this->phone,
                implode("\n", $texts),
                $sessionId
            );
        } catch (Throwable $e) {
            Log::error('FlushChatBuffer failed', [
                'instance' => $this->instanceName,
                'phone' => $this->phone,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $lock->release();
        }
    }
}
