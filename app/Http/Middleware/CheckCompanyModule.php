<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class CheckCompanyModule
{
    protected CompanyContext $companyContext;

    public function __construct(CompanyContext $companyContext)
    {
        $this->companyContext = $companyContext;
    }

    /**
     * Valida que el módulo requerido esté habilitado en la empresa activa.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $company = $this->companyContext->getCompany();

        if (! $company || ! $company->isModuleEnabled($module)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => "El módulo '{$module}' no está habilitado para esta empresa.",
                ], 403);
            }

            if ($request->routeIs('dashboard')) {
                abort(403, "El módulo '{$module}' no está habilitado para esta empresa.");
            }

            return Redirect::route('dashboard')->with('flash', [
                'type' => 'error',
                'message' => "El módulo '{$module}' no está habilitado para la empresa activa.",
            ]);
        }

        return $next($request);
    }
}
