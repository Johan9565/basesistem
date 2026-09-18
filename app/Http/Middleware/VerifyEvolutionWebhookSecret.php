<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyEvolutionWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.evolution.webhook_secret');

        if ($expected === '') {
            abort(500, 'EVOLUTION_WEBHOOK_SECRET no está configurado.');
        }

        $provided = (string) (
            $request->header('X-Evolution-Secret')
            ?? $request->header('x-evolution-secret')
            ?? ''
        );

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Webhook no autorizado.');
        }

        return $next($request);
    }
}
