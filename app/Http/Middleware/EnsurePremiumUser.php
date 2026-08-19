<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremiumUser
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user || ! $user->esUsuarioPremium()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'code' => 'REQUIERE_PREMIUM',
                    'message' => 'Esta función está disponible solo para usuarios premium.',
                ], Response::HTTP_FORBIDDEN);
            }

            abort(Response::HTTP_FORBIDDEN, 'Esta función está disponible solo para usuarios premium.');
        }

        return $next($request);
    }
}
