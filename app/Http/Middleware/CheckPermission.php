<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use App\Models\User;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user) {
            return Redirect::route('login');
        }

        if (!$user->hasPermission($permission)) {
            // Si ya estamos en dashboard evitamos el loop
            if ($request->routeIs('dashboard')) {
                abort(403, 'No tiene permiso de acceder a esta página.');
            }

            return Redirect::route('dashboard')
                ->with('flash', [
                    'type'    => 'error',
                    'message' => 'No tiene permiso de acceder a esta página.',
                ]);
        }

        return $next($request);
    }
}
