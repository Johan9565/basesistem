<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PermissionsModel;
use App\Models\ModulesModel;
use App\Models\RoleModel;

class ModulesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Eliminar módulos y permisos obsoletos que no pertenecen a este proyecto
        $obsoleteModules = ['components', 'businesscard', 'userpage', 'dependencies'];
        ModulesModel::whereIn('route', $obsoleteModules)->delete();
        PermissionsModel::whereIn('module', $obsoleteModules)->delete();
        PermissionsModel::whereIn('name', ['components', 'dependencias', 'userpage', 'businesscard'])->delete();

        // 2. Definir permisos requeridos por el proyecto
        $permissionsData = [
            [
                'name'        => 'dashboard',
                'description' => 'Apartado Dashboard',
                'status'      => 1,
                'icon'        => '<i class="fas fa-home"></i>',
                'module'      => 'dashboard',
            ],
            [
                'name'        => 'asistente',
                'description' => 'Apartado Asistente Virtual',
                'status'      => 1,
                'icon'        => '<i class="fas fa-robot"></i>',
                'module'      => 'assistant.workspace',
            ],
            [
                'name'        => 'administracion',
                'description' => 'Apartado administración',
                'status'      => 1,
                'icon'        => '<i class="fas fa-cog"></i>',
                'module'      => 'administration',
            ],
            [
                'name'        => 'usuarios',
                'description' => 'Apartado usuarios del sistema',
                'status'      => 1,
                'icon'        => '<i class="fas fa-users"></i>',
                'module'      => 'users',
            ],
            [
                'name'        => 'roles',
                'description' => 'Apartado roles del sistema',
                'status'      => 1,
                'icon'        => '<i class="fas fa-user-shield"></i>',
                'module'      => 'roles',
            ],
        ];

        $savedPermissions = [];
        foreach ($permissionsData as $pData) {
            $perm = PermissionsModel::updateOrCreate(
                ['module' => $pData['module']],
                $pData
            );
            $savedPermissions[$pData['module']] = $perm;
        }

        // 3. Definir módulos del navbar requeridos por el proyecto
        $modulesData = [
            [
                'name'        => 'Inicio',
                'route'       => 'dashboard',
                'relation'    => 'NULL',
                'order_index' => 1,
                'status'      => 1,
                'icon'        => '<i class="fas fa-home"></i>',
            ],
            [
                'name'        => 'Asistente',
                'route'       => 'assistant.workspace',
                'relation'    => 'NULL',
                'order_index' => 2,
                'status'      => 1,
                'icon'        => '<svg class="h-4 w-4 text-primary inline-block mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>',
            ],
            [
                'name'        => 'Administración',
                'route'       => 'administration',
                'relation'    => 0,
                'order_index' => 5,
                'status'      => 1,
                'icon'        => '<i class="fas fa-cog"></i>',
            ],
            [
                'name'        => 'Usuarios',
                'route'       => 'users',
                'relation'    => 'administration',
                'order_index' => 1,
                'status'      => 1,
                'icon'        => '<i class="fas fa-users"></i>',
            ],
            [
                'name'        => 'Roles',
                'route'       => 'roles',
                'relation'    => 'administration',
                'order_index' => 2,
                'status'      => 1,
                'icon'        => '<i class="fas fa-user-shield"></i>',
            ],
        ];

        foreach ($modulesData as $mData) {
            ModulesModel::updateOrCreate(
                ['route' => $mData['route']],
                $mData
            );
        }

        // 4. Actualizar o crear rol super-admin con todos los permisos del proyecto
        $allPermList = collect($savedPermissions)->map(fn($p) => [
            'id'   => (string) $p->id,
            'name' => $p->name,
        ])->values()->toArray();

        $superAdminRole = RoleModel::firstOrCreate(
            ['name' => 'super-admin'],
            [
                'role'        => 'Super Administrador',
                'status'      => 1,
                'permissions' => $allPermList,
            ]
        );
        $superAdminRole->update([
            'permissions' => $allPermList,
            'status'      => 1,
        ]);

        // 5. Actualizar o crear rol usuario con permisos básicos (dashboard y asistente)
        $userPermList = collect([
            $savedPermissions['dashboard'],
            $savedPermissions['assistant.workspace'],
        ])->map(fn($p) => [
            'id'   => (string) $p->id,
            'name' => $p->name,
        ])->values()->toArray();

        $userRole = RoleModel::firstOrCreate(
            ['name' => 'usuario'],
            [
                'role'        => 'usuario',
                'status'      => 1,
                'permissions' => $userPermList,
            ]
        );
        $userRole->update([
            'permissions' => $userPermList,
            'status'      => 1,
        ]);
    }
}
