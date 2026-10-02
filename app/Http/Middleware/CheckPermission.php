<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use App\Models\User;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\PermissionCacheService;

class CheckPermission
{
    protected CompanyContext $companyContext;
    protected PermissionCacheService $permissionCache;

    public function __construct(CompanyContext $companyContext, PermissionCacheService $permissionCache)
    {
        $this->companyContext = $companyContext;
        $this->permissionCache = $permissionCache;
    }

    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user) {
            return Redirect::route('login');
        }

        $companyId = $this->companyContext->getCompanyId();
        $hasPermission = $this->permissionCache->hasPermission($user, $permission, $companyId);

        if (!$hasPermission) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No tiene permiso de acceder a esta página en la empresa actual.',
                ], 403);
            }

            // Si ya estamos en dashboard evitamos el loop
            if ($request->routeIs('dashboard')) {
                abort(403, 'No tiene permiso de acceder a esta página en la empresa actual.');
            }

            return Redirect::route('dashboard')
                ->with('flash', [
                    'type'    => 'error',
                    'message' => 'No tiene permiso de acceder a esta página en la empresa actual.',
                ]);
        }

        return $next($request);
    }
}
