<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Acceso restringido: El módulo de roles es exclusivo para el superadministrador.');
        }

        return $next($request);
    }
}
