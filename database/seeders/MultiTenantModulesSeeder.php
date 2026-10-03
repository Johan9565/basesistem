<?php

namespace Database\Seeders;

use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use Illuminate\Database\Seeder;

class MultiTenantModulesSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'permission' => [
                    'name' => 'empresas',
                    'module' => 'companies',
                    'description' => 'Gestión de empresas y módulos',
                ],
                'module' => [
                    'name' => 'Empresas',
                    'route' => 'companies',
                    'relation' => 'administration',
                    'order_index' => 0,
                    'status' => 1,
                ],
            ],
            [
                'permission' => [
                    'name' => 'inventario',
                    'module' => 'inventory',
                    'description' => 'Inventario de productos',
                ],
                'module' => [
                    'name' => 'Inventario',
                    'route' => 'products',
                    'relation' => 1, // Módulo directo en barra de navegación
                    'order_index' => 2,
                    'status' => 1,
                ],
            ],
            [
                'permission' => [
                    'name' => 'servicios',
                    'module' => 'services',
                    'description' => 'Catálogo de servicios',
                ],
                'module' => [
                    'name' => 'Servicios',
                    'route' => 'services',
                    'relation' => 1, // Módulo directo en barra de navegación
                    'order_index' => 3,
                    'status' => 1,
                ],
            ],
        ];

        $newPermissionIds = [];

        foreach ($definitions as $def) {
            $perm = PermissionsModel::firstOrCreate(
                ['module' => $def['permission']['module']],
                [
                    'name' => $def['permission']['name'],
                    'description' => $def['permission']['description'],
                    'status' => 1,
                ]
            );

            $newPermissionIds[] = [
                'id' => (string) $perm->_id,
                'name' => $perm->name,
            ];

            ModulesModel::firstOrCreate(
                ['route' => $def['module']['route']],
                $def['module']
            );
        }

        // Asignar los nuevos permisos a los roles de administrador
        $adminRoles = RoleModel::whereIn('role', ['admin', 'administrador', 'superadmin'])->get();

        foreach ($adminRoles as $role) {
            $existing = $role->getPermissionsList();
            $merged = collect($existing)
                ->concat($newPermissionIds)
                ->unique('id')
                ->values()
                ->toArray();

            RoleModel::where('_id', (string) $role->_id)->update(['permissions' => $merged]);
        }

        // Invalidar caché de permisos
        app(\App\Services\Tenancy\PermissionCacheService::class)->invalidateAll();

        if ($this->command) {
            $this->command->info('✅ Módulos y permisos multi-empresa sembrados exitosamente.');
        }
    }
}
