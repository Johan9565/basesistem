<?php

namespace App\Http\Middleware;

use App\Models\WhatsappInstance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $instanceName = (string) $request->route('instance');
        $row = WhatsappInstance::query()
            ->where('instance_name', $instanceName)
            ->first();

        if (! $row) {
            abort(404, 'Instancia no encontrada.');
        }

        $expected = trim((string) ($row->telegram_webhook_secret ?? ''));
        if ($expected === '') {
            abort(500, 'Webhook de Telegram no configurado para esta instancia.');
        }

        $provided = (string) (
            $request->header('X-Telegram-Bot-Api-Secret-Token')
            ?? ''
        );

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Webhook Telegram no autorizado.');
        }

        $request->attributes->set('whatsapp_instance', $row);

        return $next($request);
    }
}
