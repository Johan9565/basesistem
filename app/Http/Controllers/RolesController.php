<?php

namespace App\Http\Controllers;

use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RolesController extends Controller
{
    private function resolvePermissionGroup(string $module, string $name, $modulesMap): array
    {
        $modSlug = strtolower(trim($module));
        $permSlug = strtolower(trim($name));

        // 1. WhatsApp CRM
        if (
            str_starts_with($modSlug, 'whatsapp') ||
            str_starts_with($permSlug, 'whatsapp') ||
            $modSlug === 'calendar' ||
            $permSlug === 'calendario'
        ) {
            return [
                'group_key' => 'whatsapp',
                'group_name' => 'WhatsApp CRM',
                'group_order' => 2,
            ];
        }

        // 2. Administración
        if (
            in_array($modSlug, ['administration', 'administracion', 'users', 'usuarios', 'employees', 'empleados', 'roles', 'companies', 'empresas', 'components', 'componentes']) ||
            in_array($permSlug, ['administracion', 'users', 'usuarios', 'employees', 'empleados', 'roles', 'companies', 'empresas', 'components', 'componentes'])
        ) {
            return [
                'group_key' => 'administration',
                'group_name' => 'Administración',
                'group_order' => 1,
            ];
        }

        // 3. Inventario y Servicios
        if (
            in_array($modSlug, ['products', 'inventario', 'inventory', 'services', 'servicios']) ||
            in_array($permSlug, ['products', 'inventario', 'inventory', 'services', 'servicios'])
        ) {
            return [
                'group_key' => 'operations',
                'group_name' => 'Inventario y Servicios',
                'group_order' => 3,
            ];
        }

        // 4. Chequear relación en ModulesModel
        if (isset($modulesMap[$modSlug])) {
            $relation = $modulesMap[$modSlug]->relation;
            if ($relation === 'administration') {
                return ['group_key' => 'administration', 'group_name' => 'Administración', 'group_order' => 1];
            }
            if ($relation === 'whatsapp') {
                return ['group_key' => 'whatsapp', 'group_name' => 'WhatsApp CRM', 'group_order' => 2];
            }
        }

        return [
            'group_key' => 'general',
            'group_name' => 'General',
            'group_order' => 99,
        ];
    }

    public function index()
    {
        $allModules = ModulesModel::where('status', 1)->get()->keyBy(fn ($m) => strtolower(trim($m->route ?? '')));

        $allPermissions = PermissionsModel::where('status', 1)
            ->get()
            ->map(function ($p) use ($allModules) {
                $group = $this->resolvePermissionGroup($p->module ?? '', $p->name ?? '', $allModules);

                return [
                    'id' => (string) $p->id,
                    'name' => $p->name,
                    'description' => $p->description ?? '',
                    'module' => $p->module ?: 'general',
                    'icon' => $p->icon ?? '',
                    'group_key' => $group['group_key'],
                    'group_name' => $group['group_name'],
                    'group_order' => $group['group_order'],
                ];
            })
            ->sortBy([
                ['group_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->toArray();

        $roles = RoleModel::where('status', 1)
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                $permissionIds = collect($role->getPermissionsList())
                    ->map(fn ($p) => (string) ($p['id'] ?? ''))
                    ->filter()
                    ->values()
                    ->toArray();

                $permissions = PermissionsModel::whereIn('_id', $permissionIds)
                    ->where('status', 1)
                    ->get()
                    ->map(fn ($p) => [
                        'id' => (string) $p->id,
                        'name' => $p->name,
                        'description' => $p->description ?? '',
                        'module' => $p->module ?: 'general',
                        'icon' => $p->icon ?? '',
                    ])
                    ->values()
                    ->toArray();

                return [
                    'id' => (string) $role->id,
                    'name' => $role->name,
                    'role' => $role->role,
                    'status' => $role->status ?? 1,
                    'is_system' => (bool) ($role->is_system ?? false),
                    'permission_ids' => $permissionIds,
                    'permissions' => $permissions,
                ];
            });

        $modules = ModulesModel::where('status', 1)
            ->orderBy('order_index')
            ->get()
            ->map(fn ($m) => [
                'id' => (string) $m->id,
                'name' => $m->name,
                'route' => $m->route,
                'relation' => $m->relation,
                'order_index' => $m->order_index,
            ])
            ->toArray();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'allpermissions' => $allPermissions,
            'modules' => $modules,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'role' => 'required|string|max:100',
            'name' => 'nullable|string|max:100',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'string',
        ]);

        $permissionIds = $request->input('permission_ids', []);
        $permissions = [];
        if (! empty($permissionIds)) {
            $permissions = PermissionsModel::whereIn('_id', $permissionIds)
                ->where('status', 1)
                ->get()
                ->map(fn ($p) => [
                    'id' => (string) $p->id,
                    'name' => $p->name,
                ])
                ->values()
                ->toArray();
        }

        $slug = $request->filled('name')
            ? strtolower(trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', $request->name)))
            : strtolower(trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', $request->role)));

        RoleModel::create([
            'name' => $slug,
            'role' => trim($request->role),
            'status' => 1,
            'is_system' => false,
            'permissions' => $permissions,
        ]);

        app(\App\Services\Tenancy\PermissionCacheService::class)->invalidateAll();

        return back()->with('success', 'Rol creado exitosamente');
    }

    public function update(Request $request, string $roleId)
    {
        $role = RoleModel::findOrFail($roleId);

        $request->validate([
            'role' => 'required|string|max:100',
            'name' => 'nullable|string|max:100',
            'status' => 'sometimes|integer|in:0,1',
        ]);

        $data = [
            'role' => trim($request->role),
        ];

        if ($request->filled('name') && ! $role->is_system) {
            $data['name'] = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', $request->name)));
        }

        if ($request->has('status')) {
            $data['status'] = (int) $request->status;
        }

        $role->update($data);

        app(\App\Services\Tenancy\PermissionCacheService::class)->broadcastRoleUpdate($roleId, 'Se ha actualizado la información de tu rol.');

        return back()->with('success', 'Rol actualizado exitosamente');
    }

    public function destroy(string $roleId)
    {
        $role = RoleModel::findOrFail($roleId);

        if ($role->is_system) {
            return back()->with('error', 'No se puede eliminar un rol del sistema');
        }

        $role->delete();

        app(\App\Services\Tenancy\PermissionCacheService::class)->broadcastRoleUpdate($roleId, 'Un rol asignado ha sido eliminado.');

        return back()->with('success', 'Rol eliminado exitosamente');
    }

    public function updatePermissions(Request $request, string $roleId)
    {
        $request->validate([
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'string',
        ]);

        RoleModel::findOrFail($roleId);

        $permissionIds = $request->input('permission_ids', []);

        if (! empty($permissionIds)) {
            $permissions = PermissionsModel::whereIn('_id', $permissionIds)
                ->where('status', 1)
                ->get();

            $newPermissions = $permissions->map(fn ($p) => [
                'id' => (string) $p->id,
                'name' => $p->name,
            ])->values()->toArray();
        } else {
            $newPermissions = [];
        }

        RoleModel::where('_id', $roleId)->update(['permissions' => $newPermissions]);

        app(\App\Services\Tenancy\PermissionCacheService::class)->broadcastRoleUpdate($roleId, 'Los permisos de tu rol han sido actualizados.');

        return back()->with('success', 'Permisos actualizados exitosamente');
    }
}
