<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserAccountState
{
    /**
     * Rutas permitidas si el usuario tiene status inactivo (0).
     *
     * @var list<string>
     */
    private const DEACTIVATED_ALLOWED = [
        'account.deactivated',
        'logout',
    ];

    /**
     * Rutas permitidas si debe cambiar contraseña (active === false).
     *
     * @var list<string>
     */
    private const PASSWORD_REQUIRED_ALLOWED = [
        'account.password-required',
        'account.password-required.store',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $name = $request->route()?->getName();

        if ((int) ($user->status ?? 1) === 0) {
            if ($name !== null && in_array($name, self::DEACTIVATED_ALLOWED, true)) {
                return $next($request);
            }

            return redirect()->route('account.deactivated');
        }

        if ($this->mustChangePassword($user)) {
            if ($name !== null && in_array($name, self::PASSWORD_REQUIRED_ALLOWED, true)) {
                return $next($request);
            }

            return redirect()->route('account.password-required');
        }

        return $next($request);
    }

    /**
     * @param  \App\Models\User  $user
     */
    private function mustChangePassword($user): bool
    {
        $active = $user->getAttribute('active');
        if ($active === null) {
            return false;
        }

        return $active === false || $active === 0 || $active === '0';
    }
}
