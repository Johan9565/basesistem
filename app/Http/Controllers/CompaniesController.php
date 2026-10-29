<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompaniesController extends Controller
{
    /**
     * Listado de empresas para administración y configuración modular.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = Company::with(['client', 'owner', 'branches'])->orderBy('name', 'asc');

        if (! $user->canManageAllCompanies()) {
            $assignedIds = $user->getAssignedCompanyIds();
            $query->whereInMongoIds($assignedIds);
        }

        $canManageOwner = $user->hasPermission('companies.manage_owner');

        $companies = $query->get()->map(function ($c) {
            $ownerUser = $c->owner ?: ($c->user_id ? \App\Models\User::find($c->user_id) : null);

            $branchesList = $c->branches ?: collect();

            $branches = $branchesList->sortByDesc('is_main')->values()->map(fn ($b) => [
                'id' => (string) $b->_id,
                'name' => $b->name,
                'code' => $b->code ?? '',
                'phone' => $b->phone ?? '',
                'email' => $b->email ?? '',
                'address' => $b->address ?? '',
                'is_main' => (bool) $b->is_main,
                'is_active' => (bool) ($b->is_active ?? true),
            ])->toArray();

            return [
                'id' => (string) $c->_id,
                'name' => $c->name,
                'slug' => $c->slug,
                'user_id' => (string) ($c->user_id ?? ''),
                'owner_name' => $ownerUser?->name ?? 'Sin asignar',
                'owner_email' => $ownerUser?->email ?? '',
                'document_number' => $c->document_number ?? '',
                'email' => $c->email ?? '',
                'phone' => $c->phone ?? '',
                'status' => $c->status,
                'modules' => $c->modules ?? [
                    'inventory' => false,
                    'orders' => false,
                    'services' => false,
                    'appointments' => false,
                    'whatsapp' => false,
                ],
                'client_name' => $c->client?->name ?? 'Cliente Principal',
                'branches' => $branches,
                'branches_count' => count($branches),
            ];
        });

        $clients = Client::orderBy('name', 'asc')->get()->map(fn ($cl) => [
            'id' => (string) $cl->_id,
            'name' => $cl->name,
            'document_number' => $cl->document_number ?? '',
            'billing_email' => $cl->billing_email ?? '',
            'phone' => $cl->phone ?? '',
            'plan' => $cl->plan ?? 'pro',
            'max_companies' => (int) ($cl->max_companies ?? 10),
            'companies_count' => $cl->companies()->count(),
            'status' => $cl->status ?? 'active',
        ]);

        $usersQuery = \App\Models\User::where('status', 1)->orderBy('name', 'asc');
        if (! $canManageOwner) {
            $usersQuery->where('_id', (string) $user->_id);
        }
        $users = $usersQuery->get(['_id', 'name', 'email'])->map(fn ($u) => [
            'id' => (string) $u->_id,
            'name' => $u->name,
            'email' => $u->email,
        ]);

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'clients' => $clients,
            'users' => $users,
            'can' => [
                'create' => $user->hasPermission('companies.create'),
                'edit' => $user->hasPermission('companies.edit'),
                'toggle_module' => $user->hasPermission('companies.toggle_module'),
                'manage_owner' => $canManageOwner,
                'manage_all' => $user->canManageAllCompanies(),
                'select_all' => $user->canSelectAllCompanies(),
                'branches_view' => $user->hasPermission('branches.view'),
                'branches_create' => $user->hasPermission('branches.create'),
                'branches_edit' => $user->hasPermission('branches.edit'),
                'branches_delete' => $user->hasPermission('branches.delete'),
            ],
        ]);
    }

    /**
     * Crea una nueva empresa.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $canManageOwner = $user->hasPermission('companies.manage_owner');

        $rules = [
            'user_id' => 'required|string',
            'client_id' => 'nullable|string',
            'name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'modules' => 'nullable|array',
        ];

        if (! $canManageOwner) {
            $request->merge(['user_id' => (string) $user->_id]);
        }

        $validated = $request->validate($rules);

        if (empty($validated['client_id'])) {
            $defaultClient = Client::first();
            $validated['client_id'] = $defaultClient ? (string) $defaultClient->_id : null;
        }

        $validated['slug'] = Str::slug($validated['name']);
        $validated['status'] = 'active';
        $validated['modules'] = array_merge([
            'inventory' => false,
            'orders' => false,
            'services' => false,
            'appointments' => false,
            'whatsapp' => false,
        ], $validated['modules'] ?? []);

        $company = Company::create($validated);

        // Vincular al usuario propietario en CompanyUser
        $ownerUser = \App\Models\User::find($validated['user_id']);
        \App\Models\CompanyUser::updateOrCreate([
            'user_id' => (string) $validated['user_id'],
            'company_id' => (string) $company->_id,
        ], [
            'role_id' => $ownerUser?->role_id ? (string) $ownerUser->role_id : null,
            'status' => 'active',
            'is_owner' => true,
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Empresa '{$company->name}' creada exitosamente.",
        ]);
    }

    /**
     * Actualiza los datos de la empresa.
     */
    public function update(Request $request, Company $company): RedirectResponse
    {
        $user = $request->user();

        if (! $user->canManageAllCompanies()) {
            $assignedIds = $user->getAssignedCompanyIds();
            if (! in_array((string) $company->_id, $assignedIds, true)) {
                abort(403, 'No tienes permiso para modificar esta empresa.');
            }
        }

        $validated = $request->validate([
            'user_id' => 'required|string',
            'name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'status' => 'required|string|in:active,inactive',
        ]);

        $canManageOwner = $user->hasPermission('companies.manage_owner');
        $isCurrentOwner = (string) $company->user_id === (string) $user->_id;

        if (! $canManageOwner && ! $isCurrentOwner) {
            $validated['user_id'] = (string) $company->user_id;
            $validated['status'] = $company->status ?? 'active';
        }

        $oldOwnerId = $company->user_id ? (string) $company->user_id : null;
        $newOwnerId = (string) $validated['user_id'];
        $companyIdStr = (string) $company->_id;

        $companyObjId = null;
        try {
            $companyObjId = new \MongoDB\BSON\ObjectId($companyIdStr);
        } catch (\Throwable $e) {
        }

        $company->update($validated);

        if ($oldOwnerId && $oldOwnerId !== $newOwnerId) {
            $oldOwnerObjId = null;
            try {
                $oldOwnerObjId = new \MongoDB\BSON\ObjectId($oldOwnerId);
            } catch (\Throwable $e) {
            }

            // Eliminar membresía del dueño anterior en esta empresa
            \App\Models\CompanyUser::where(function ($q) use ($companyIdStr, $companyObjId) {
                $q->where('company_id', $companyIdStr);
                if ($companyObjId) {
                    $q->orWhere('company_id', $companyObjId);
                }
            })
            ->where(function ($q) use ($oldOwnerId, $oldOwnerObjId) {
                $q->where('user_id', $oldOwnerId);
                if ($oldOwnerObjId) {
                    $q->orWhere('user_id', $oldOwnerObjId);
                }
            })
            ->delete();

            app(\App\Services\Tenancy\PermissionCacheService::class)->invalidateUser($oldOwnerId, $companyIdStr);
            app(\App\Services\Tenancy\PermissionCacheService::class)->broadcastPermissionUpdate([$oldOwnerId], "La empresa '{$company->name}' ha sido transferida a otro usuario.");
        }

        // Eliminar cualquier otro registro que figurara como dueño previo para esta empresa
        \App\Models\CompanyUser::where(function ($q) use ($companyIdStr, $companyObjId) {
            $q->where('company_id', $companyIdStr);
            if ($companyObjId) {
                $q->orWhere('company_id', $companyObjId);
            }
        })
        ->where('user_id', '!=', $newOwnerId)
        ->where('is_owner', true)
        ->delete();

        $ownerUser = \App\Models\User::find($newOwnerId);
        \App\Models\CompanyUser::updateOrCreate([
            'user_id' => $newOwnerId,
            'company_id' => $companyIdStr,
        ], [
            'role_id' => $ownerUser?->role_id ? (string) $ownerUser->role_id : null,
            'status' => 'active',
            'is_owner' => true,
        ]);

        app(\App\Services\Tenancy\PermissionCacheService::class)->invalidateUser($newOwnerId, $companyIdStr);
        app(\App\Services\Tenancy\PermissionCacheService::class)->broadcastPermissionUpdate([$newOwnerId], "Has sido asignado como propietario de la empresa '{$company->name}'.");
        app(\App\Services\Tenancy\PermissionCacheService::class)->broadcastCompanyUpdate($companyIdStr, "La empresa '{$company->name}' ha sido actualizada.");

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Empresa actualizada correctamente.',
        ]);
    }

    /**
     * Activa o desactiva un módulo individual en la empresa.
     */
    public function toggleModule(Request $request, Company $company): RedirectResponse
    {
        $user = $request->user();

        if (! $user->canManageAllCompanies()) {
            $assignedIds = $user->getAssignedCompanyIds();
            if (! in_array((string) $company->_id, $assignedIds, true)) {
                abort(403, 'No tienes permiso para modificar esta empresa.');
            }
        }

        $validated = $request->validate([
            'module' => 'required|string|in:inventory,orders,services,appointments,whatsapp,chat-test',
            'enabled' => 'required|boolean',
        ]);

        $module = $validated['module'];
        $enabled = (bool) $validated['enabled'];

        if ($module === 'orders' && $enabled) {
            // El módulo de pedidos es inherente a inventarios: requiere inventario activo
            if (! $company->isModuleEnabled('inventory')) {
                $company->enableModule('inventory');
            }
            $company->enableModule('orders');
        } elseif ($module === 'inventory' && ! $enabled) {
            // Si se desactiva inventario, también se desactiva pedidos
            $company->disableModule('orders');
            $company->disableModule('inventory');
        } elseif ($enabled) {
            $company->enableModule($module);
        } else {
            $company->disableModule($module);
        }

        app(\App\Services\Tenancy\PermissionCacheService::class)->broadcastCompanyUpdate((string) $company->_id, "Se ha actualizado la disponibilidad del módulo '{$module}'.");

        $statusStr = $enabled ? 'habilitado' : 'deshabilitado';

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Módulo '{$module}' {$statusStr} en la empresa {$company->name}.",
        ]);
    }
}
