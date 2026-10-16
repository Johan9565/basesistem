<?php

namespace Database\Seeders;

use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use App\Services\Tenancy\PermissionCacheService;
use Illuminate\Database\Seeder;

class OrdersModuleSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            [
                'name' => 'orders',
                'description' => 'Módulo de Pedidos y Ventas',
                'module' => 'orders',
                'status' => 1,
            ],
            [
                'name' => 'orders.view',
                'description' => 'Ver listado y detalles de pedidos',
                'module' => 'orders',
                'status' => 1,
            ],
            [
                'name' => 'orders.create',
                'description' => 'Crear pedidos y descontar inventario',
                'module' => 'orders',
                'status' => 1,
            ],
            [
                'name' => 'orders.edit',
                'description' => 'Modificar pedidos y estado',
                'module' => 'orders',
                'status' => 1,
            ],
            [
                'name' => 'orders.delete',
                'description' => 'Eliminar o cancelar pedidos y restaurar stock',
                'module' => 'orders',
                'status' => 1,
            ],
        ];

        $savedPermObjects = [];

        foreach ($perms as $p) {
            $perm = PermissionsModel::updateOrCreate(
                ['name' => $p['name']],
                $p
            );
            $savedPermObjects[$p['name']] = [
                'id' => (string) $perm->_id,
                'name' => $perm->name,
            ];
        }

        // Registrar el módulo en la tabla de módulos para la navegación
        ModulesModel::updateOrCreate(
            ['route' => 'orders'],
            [
                'name' => 'Pedidos',
                'route' => 'orders',
                'relation' => 1,
                'order_index' => 3,
                'status' => 1,
            ]
        );

        // Asignar los permisos a los roles existentes
        $roles = RoleModel::all();
        foreach ($roles as $role) {
            $existingPerms = $role->permissions ?? [];
            if (! is_array($existingPerms)) {
                $existingPerms = [];
            }

            $isSuperAdmin = in_array(strtolower((string) ($role->role ?? '')), ['superadmin', 'super_admin', 'super administrador'], true);
            $isAdmin = in_array(strtolower((string) ($role->role ?? '')), ['admin', 'administrador', 'company_admin'], true);
            $isSeller = in_array(strtolower((string) ($role->role ?? '')), ['vendedor', 'sales'], true);

            if ($isSuperAdmin || $isAdmin || $isSeller) {
                // Si los permisos en el rol son objetos con ['id', 'name']
                $isObjectArray = ! empty($existingPerms) && is_array($existingPerms[0] ?? null) && isset($existingPerms[0]['name']);

                if ($isObjectArray) {
                    $existingNames = collect($existingPerms)->pluck('name')->toArray();
                    $toAdd = [];
                    foreach ($savedPermObjects as $pName => $pObj) {
                        if (! in_array($pName, $existingNames, true)) {
                            // Vendedor solo recibe view, create, edit
                            if ($isSeller && $pName === 'orders.delete') {
                                continue;
                            }
                            $toAdd[] = $pObj;
                        }
                    }
                    if (! empty($toAdd)) {
                        $role->permissions = array_values(array_merge($existingPerms, $toAdd));
                        $role->save();
                    }
                } else {
                    $toAdd = array_keys($savedPermObjects);
                    if ($isSeller) {
                        $toAdd = array_filter($toAdd, fn ($k) => $k !== 'orders.delete');
                    }
                    $role->permissions = array_values(array_unique(array_merge($existingPerms, $toAdd)));
                    $role->save();
                }
            }
        }

        // Invalidar caché
        app(PermissionCacheService::class)->invalidateAll();
    }
}
