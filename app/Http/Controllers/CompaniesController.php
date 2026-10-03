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
    public function index(): Response
    {
        $companies = Company::with('client')->orderBy('name', 'asc')->get()->map(function ($c) {
            return [
                'id' => (string) $c->_id,
                'name' => $c->name,
                'slug' => $c->slug,
                'document_number' => $c->document_number ?? '',
                'email' => $c->email ?? '',
                'phone' => $c->phone ?? '',
                'status' => $c->status,
                'modules' => $c->modules ?? [
                    'inventory' => false,
                    'services' => false,
                    'appointments' => false,
                    'whatsapp' => false,
                ],
                'client_name' => $c->client?->name ?? 'Cliente Principal',
            ];
        });

        $clients = Client::where('status', 'active')->get(['_id', 'name'])->map(fn ($cl) => [
            'id' => (string) $cl->_id,
            'name' => $cl->name,
        ]);

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'clients' => $clients,
        ]);
    }

    /**
     * Crea una nueva empresa.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => 'nullable|string',
            'name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'modules' => 'nullable|array',
        ]);

        if (empty($validated['client_id'])) {
            $defaultClient = Client::first();
            $validated['client_id'] = $defaultClient ? (string) $defaultClient->_id : null;
        }

        $validated['slug'] = Str::slug($validated['name']);
        $validated['status'] = 'active';
        $validated['modules'] = array_merge([
            'inventory' => false,
            'services' => false,
            'appointments' => false,
            'whatsapp' => false,
        ], $validated['modules'] ?? []);

        $company = Company::create($validated);

        // Vincular al usuario creador
        if ($request->user()) {
            \App\Models\CompanyUser::firstOrCreate([
                'user_id' => (string) $request->user()->_id,
                'company_id' => (string) $company->_id,
            ], [
                'role_id' => $request->user()->role_id ? (string) $request->user()->role_id : null,
                'status' => 'active',
                'is_owner' => true,
            ]);
        }

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'status' => 'required|string|in:active,inactive',
        ]);

        $company->update($validated);

        app(\App\Services\Tenancy\PermissionCacheService::class)->broadcastCompanyUpdate((string) $company->_id, "La empresa '{$company->name}' ha sido actualizada.");

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
        $validated = $request->validate([
            'module' => 'required|string|in:inventory,services,appointments,whatsapp',
            'enabled' => 'required|boolean',
        ]);

        $module = $validated['module'];
        $enabled = (bool) $validated['enabled'];

        if ($enabled) {
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
