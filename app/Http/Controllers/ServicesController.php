<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServicesController extends Controller
{
    /**
     * Catálogo de servicios de la empresa activa.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $branchId = trim((string) $request->input('branch_id', ''));

        $companyContext = app(\App\Services\Tenancy\CompanyContext::class);
        $activeCompanyId = $companyContext->getCompanyId();

        $branches = [];
        if ($activeCompanyId) {
            $branches = \App\Models\Branch::where('company_id', $activeCompanyId)
                ->where('is_active', true)
                ->orderByDesc('is_main')
                ->orderBy('name', 'asc')
                ->get()
                ->map(fn ($b) => [
                    'id' => (string) $b->_id,
                    'name' => $b->name,
                    'code' => $b->code ?? '',
                    'is_main' => (bool) $b->is_main,
                ])
                ->values()
                ->toArray();
        }

        $query = Service::with('branch')->orderBy('name', 'asc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($branchId !== '') {
            $query->where('branch_id', $branchId);
        }

        $services = $query->paginate(15)->through(function ($s) {
            return [
                'id' => (string) $s->_id,
                'branch_id' => (string) ($s->branch_id ?? ''),
                'branch_name' => $s->branch?->name ?? 'Sucursal Principal',
                'branch_code' => $s->branch?->code ?? '',
                'name' => $s->name,
                'description' => $s->description,
                'duration_minutes' => $s->duration_minutes,
                'price' => $s->price,
                'assigned_user_ids' => $s->assigned_user_ids ?? [],
                'is_active' => (bool) $s->is_active,
            ];
        })->withQueryString();

        $staffUsers = User::where('status', 1)
            ->get(['_id', 'name', 'ape_pat', 'email'])
            ->map(fn ($u) => [
                'id' => (string) $u->_id,
                'name' => trim("{$u->name} {$u->ape_pat}"),
                'email' => $u->email,
            ]);

        return Inertia::render('Services/Index', [
            'services' => $services,
            'branches' => $branches,
            'staff' => $staffUsers,
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
            ],
        ]);
    }

    /**
     * Guarda un nuevo servicio.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|string',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'price' => 'required|numeric|min:0',
            'assigned_user_ids' => 'nullable|array',
            'assigned_user_ids.*' => 'string',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['branch_id'])) {
            $companyContext = app(\App\Services\Tenancy\CompanyContext::class);
            $mainBranch = \App\Models\Branch::where('company_id', $companyContext->getCompanyId())
                ->where('is_main', true)
                ->first();
            $validated['branch_id'] = $mainBranch ? (string) $mainBranch->_id : null;
        }

        Service::create($validated);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Servicio creado exitosamente en el catálogo.',
        ]);
    }

    /**
     * Actualiza un servicio existente.
     */
    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|string',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'price' => 'required|numeric|min:0',
            'assigned_user_ids' => 'nullable|array',
            'assigned_user_ids.*' => 'string',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['branch_id'])) {
            $companyContext = app(\App\Services\Tenancy\CompanyContext::class);
            $mainBranch = \App\Models\Branch::where('company_id', $companyContext->getCompanyId())
                ->where('is_main', true)
                ->first();
            $validated['branch_id'] = $mainBranch ? (string) $mainBranch->_id : null;
        }

        $service->update($validated);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Servicio actualizado correctamente.',
        ]);
    }

    /**
     * Elimina un servicio.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Servicio eliminado del catálogo.',
        ]);
    }
}
