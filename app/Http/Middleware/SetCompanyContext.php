<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Tenancy\CompanyContext;
use App\Models\Company;

class SetCompanyContext
{
    protected CompanyContext $companyContext;

    public function __construct(CompanyContext $companyContext)
    {
        $this->companyContext = $companyContext;
    }

    /**
     * Resuelve y establece la empresa activa en el ciclo de vida de la petición web.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $sessionCompanyId = $request->session()->get('active_company_id');
            $activeCompany = null;

            if (!empty($sessionCompanyId)) {
                // Verificar que el usuario tenga membresía activa en la empresa seleccionada
                $membership = $user->companyMemberships()
                    ->where('company_id', (string) $sessionCompanyId)
                    ->where('status', 'active')
                    ->first();

                if ($membership) {
                    $activeCompany = Company::find($sessionCompanyId);
                }
            }

            // Si no hay empresa válida en sesión, auto-asignar la primera empresa disponible del usuario
            if (!$activeCompany) {
                $firstMembership = $user->companyMemberships()
                    ->where('status', 'active')
                    ->first();

                if ($firstMembership) {
                    $activeCompany = Company::find($firstMembership->company_id);
                    if ($activeCompany) {
                        $request->session()->put('active_company_id', (string) $activeCompany->_id);
                    }
                } elseif ($user->hasPermission('administration')) {
                    // Fallback para administradores globales
                    $activeCompany = Company::where('status', 'active')->first();
                    if ($activeCompany) {
                        $request->session()->put('active_company_id', (string) $activeCompany->_id);
                    }
                }
            }

            if ($activeCompany) {
                $this->companyContext->setCompany($activeCompany);
            }
        }

        return $next($request);
    }
}
