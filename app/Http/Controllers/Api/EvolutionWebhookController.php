<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsappMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvolutionWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = strtoupper((string) ($payload['event'] ?? $payload['type'] ?? ''));

        if ($event !== '' && $event !== 'MESSAGES_UPSERT' && $event !== 'MESSAGES.UPSERT') {
            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'event_filtered']);
        }

        $data = $payload['data'] ?? $payload;
        $messages = $this->normalizeMessages($data);

        foreach ($messages as $message) {
            if ($this->shouldSkip($message)) {
                continue;
            }

            $instance = (string) (
                $payload['instance']
                ?? $payload['instanceName']
                ?? data_get($message, 'instance')
                ?? ''
            );

            $phone = $this->extractPhone($message);
            if ($instance === '' || $phone === '') {
                continue;
            }

            // HTTP 200 de inmediato: el pipeline completo (pausa, sesión,
            // búfer y IA) corre en cola para evitar reintentos de Evolution.
            ProcessWhatsappMessage::dispatch($message, $instance, $phone);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function normalizeMessages(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (isset($data['key']) || isset($data['message'])) {
            return [$data];
        }

        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        if (isset($data['messages']) && is_array($data['messages'])) {
            return array_values(array_filter($data['messages'], 'is_array'));
        }

        return [$data];
    }

    /**
     * Descarte inmediato de grupos, canales y estados. Los mensajes fromMe
     * NO se descartan aquí: el job evalúa si son envíos del bot o del operador.
     *
     * @param  array<string, mixed>  $message
     */
    protected function shouldSkip(array $message): bool
    {
        $remoteJid = $this->extractRemoteJid($message);

        if (
            str_ends_with($remoteJid, '@g.us')
            || str_ends_with($remoteJid, '@broadcast')
            || str_ends_with($remoteJid, '@newsletter')
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function extractRemoteJid(array $message): string
    {
        $remoteJid = (string) data_get($message, 'key.remoteJid', '');

        if ($remoteJid === '') {
            $remoteJid = (string) data_get($message, 'remoteJid', '');
        }

        return $remoteJid;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function extractPhone(array $message): string
    {
        $remoteJid = $this->extractRemoteJid($message);

        return preg_replace('/@.+$/', '', $remoteJid) ?? $remoteJid;
    }
}
