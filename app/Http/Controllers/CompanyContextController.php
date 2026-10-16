<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyContextController extends Controller
{
    /**
     * Cambia la empresa activa en la sesión del usuario.
     */
    public function switch(Request $request, Company $company): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $accessibleIds = $user->getAccessibleCompanyIds();

        if (! in_array((string) $company->_id, $accessibleIds, true)) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'No tienes acceso a la empresa seleccionada.',
            ]);
        }

        $request->session()->put('active_company_id', (string) $company->_id);

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Empresa activa cambiada a: {$company->name}",
        ]);
    }
}
