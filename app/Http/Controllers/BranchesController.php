<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchesController extends Controller
{
    /**
     * Valida si el usuario tiene acceso a modificar la empresa especificada.
     */
    protected function authorizeCompanyAccess(Request $request, Company $company): void
    {
        $user = $request->user();

        if (! $user->canManageAllCompanies()) {
            $assignedIds = $user->getAssignedCompanyIds();
            if (! in_array((string) $company->_id, $assignedIds, true)) {
                abort(403, 'No tienes permiso para gestionar sucursales en esta empresa.');
            }
        }
    }

    /**
     * Registra una nueva sucursal para la empresa.
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeCompanyAccess($request, $company);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'is_main' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $companyIdStr = (string) $company->_id;
        $validated['company_id'] = $companyIdStr;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $hasBranches = Branch::where('company_id', $companyIdStr)->exists();
        if (! $hasBranches) {
            $validated['is_main'] = true;
        } else {
            $validated['is_main'] = (bool) ($validated['is_main'] ?? false);
        }

        if ($validated['is_main']) {
            Branch::where('company_id', $companyIdStr)->update(['is_main' => false]);
        }

        $branch = Branch::create($validated);

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Sucursal '{$branch->name}' creada exitosamente.",
        ]);
    }

    /**
     * Actualiza la información de una sucursal.
     */
    public function update(Request $request, Company $company, Branch $branch): RedirectResponse
    {
        $this->authorizeCompanyAccess($request, $company);

        if ((string) $branch->company_id !== (string) $company->_id) {
            abort(404, 'La sucursal no pertenece a esta empresa.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'is_main' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $companyIdStr = (string) $company->_id;
        $isMain = (bool) ($validated['is_main'] ?? false);

        if ($isMain) {
            Branch::where('company_id', $companyIdStr)
                ->where('_id', '!=', $branch->_id)
                ->update(['is_main' => false]);
            $validated['is_main'] = true;
        } else {
            // Si ya era la sucursal principal y se intenta desmarcar, verificar que haya otra sucursal principal
            if ($branch->is_main) {
                $otherMainExists = Branch::where('company_id', $companyIdStr)
                    ->where('_id', '!=', $branch->_id)
                    ->where('is_main', true)
                    ->exists();

                if (! $otherMainExists) {
                    $validated['is_main'] = true;
                }
            }
        }

        $branch->update($validated);

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Sucursal '{$branch->name}' actualizada correctamente.",
        ]);
    }

    /**
     * Elimina una sucursal de la empresa.
     */
    public function destroy(Request $request, Company $company, Branch $branch): RedirectResponse
    {
        $this->authorizeCompanyAccess($request, $company);

        if ((string) $branch->company_id !== (string) $company->_id) {
            abort(404, 'La sucursal no pertenece a esta empresa.');
        }

        $companyIdStr = (string) $company->_id;
        $totalBranches = Branch::where('company_id', $companyIdStr)->count();

        if ($totalBranches <= 1) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'No es posible eliminar la única sucursal de la empresa.',
            ]);
        }

        if ($branch->is_main) {
            // Asignar otra sucursal como principal antes de eliminarla
            $anotherBranch = Branch::where('company_id', $companyIdStr)
                ->where('_id', '!=', $branch->_id)
                ->first();

            if ($anotherBranch) {
                $anotherBranch->update(['is_main' => true]);
            }
        }

        $name = $branch->name;
        $branch->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Sucursal '{$name}' eliminada correctamente.",
        ]);
    }
}
