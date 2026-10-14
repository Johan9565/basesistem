<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\Tenancy\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

            $accessibleCompanyIds = $user->getAccessibleCompanyIds();

            if (! empty($sessionCompanyId)) {
                // Verificar que el usuario tenga acceso legítimo a la empresa seleccionada en sesión
                if (in_array((string) $sessionCompanyId, $accessibleCompanyIds, true)) {
                    $activeCompany = Company::whereInMongoIds([(string) $sessionCompanyId])->where('status', 'active')->first();
                }
            }

            // Si no hay empresa válida en sesión, auto-asignar la primera empresa asignada al usuario
            if (! $activeCompany && ! empty($accessibleCompanyIds)) {
                $activeCompany = Company::whereInMongoIds($accessibleCompanyIds)
                    ->where('status', 'active')
                    ->first();

                if ($activeCompany) {
                    $request->session()->put('active_company_id', (string) $activeCompany->_id);
                } else {
                    $request->session()->forget('active_company_id');
                }
            } elseif (! $activeCompany) {
                $request->session()->forget('active_company_id');
            }

            if ($activeCompany) {
                $this->companyContext->setCompany($activeCompany);
            } else {
                $this->companyContext->clear();
            }
        }

        return $next($request);
    }
}
