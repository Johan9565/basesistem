<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\RoleModel;
use App\Models\PermissionsModel;

class RolesController extends Controller
{
    public function index()
    {
        $allPermissions = PermissionsModel::where('status', 1)
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'          => (string) $p->id,
                'name'        => $p->name,
                'description' => $p->description ?? '',
            ])
            ->toArray();

        $roles = RoleModel::where('status', 1)
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                $permissionIds = collect($role->getPermissionsList())
                    ->map(fn($p) => (string) ($p['id'] ?? ''))
                    ->filter()
                    ->values()
                    ->toArray();

                $permissions = PermissionsModel::whereIn('_id', $permissionIds)
                    ->where('status', 1)
                    ->get()
                    ->map(fn($p) => [
                        'id'          => (string) $p->id,
                        'name'        => $p->name,
                        'description' => $p->description ?? '',
                        'module'      => $p->module ?? '',
                        'icon'        => $p->icon ?? '',
                    ])
                    ->values()
                    ->toArray();

                return [
                    'id'          => (string) $role->id,
                    'name'        => $role->name,
                    'role'        => $role->role,
                    'permissions' => $permissions,
                ];
            });
        return Inertia::render('Roles/Index', [
            'roles'       => $roles,
            'allpermissions' => $allPermissions,
        ]);
    }

    public function updatePermissions(Request $request, string $roleId)
    {
        $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'string',
        ]);

        RoleModel::findOrFail($roleId);

        $permissionIds = $request->permission_ids;
        $permissions   = PermissionsModel::whereIn('_id', $permissionIds)->where('status', 1)->get();

        $newPermissions = $permissions->map(fn($p) => [
            'id'   => $p->id,
            'name' => $p->name,
        ])->values()->toArray();

        RoleModel::where('_id', $roleId)->update(['permissions' => $newPermissions]);

        return back();
    }
}
