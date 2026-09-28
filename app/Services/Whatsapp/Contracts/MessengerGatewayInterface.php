<?php

namespace App\Services\Whatsapp\Contracts;

/**
 * Adaptador de mensajería saliente.
 * Hoy: Evolution (Baileys). Futuro: WhatsApp Cloud API (Meta) u otro proveedor.
 */
interface MessengerGatewayInterface
{
    /**
     * @return array<string, mixed>
     */
    public function sendText(string $instance, string $phone, string $text): array;

    /**
     * Indica "escribiendo..." al cliente (best-effort; no debe tumbar el flujo).
     */
    public function sendTyping(string $instance, string $phone): void;

    public function normalizePhone(string $phone): string;
}
