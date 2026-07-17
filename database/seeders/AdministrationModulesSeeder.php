<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PermissionsModel;
use App\Models\ModulesModel;
use App\Models\RoleModel;
use App\Models\ComponentThemeModel;
use App\Support\LandingPalette;

class AdministrationModulesSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'permission' => [
                    'name' => 'administracion',
                    'module' => 'administration',
                    'description' => 'Sección de administración',
                ],
                'module' => [
                    'name' => 'Administración',
                    'route' => 'administration',
                    'relation' => 0,
                    'order_index' => 5,
                ],
            ],
            [
                'permission' => [
                    'name' => 'usuarios',
                    'module' => 'users',
                    'description' => 'Gestión de usuarios',
                ],
                'module' => [
                    'name' => 'Usuarios',
                    'route' => 'users',
                    'relation' => 'administration',
                    'order_index' => 1,
                ],
            ],
            [
                'permission' => [
                    'name' => 'roles',
                    'module' => 'roles',
                    'description' => 'Gestión de roles',
                ],
                'module' => [
                    'name' => 'Roles',
                    'route' => 'roles',
                    'relation' => 'administration',
                    'order_index' => 2,
                ],
            ],
            [
                'permission' => [
                    'name' => 'components',
                    'module' => 'components',
                    'description' => 'Componentes y estilos',
                ],
                'module' => [
                    'name' => 'Componentes del sistema',
                    'route' => 'components',
                    'relation' => 'administration',
                    'order_index' => 3,
                ],
            ],
        ];

        $permissionDocs = [];

        foreach ($definitions as $item) {
            $perm = PermissionsModel::firstOrCreate(
                ['module' => $item['permission']['module']],
                [
                    'name' => $item['permission']['name'],
                    'description' => $item['permission']['description'],
                    'module' => $item['permission']['module'],
                    'status' => 1,
                ]
            );

            if ((int) $perm->status !== 1) {
                $perm->update(['status' => 1]);
            }

            ModulesModel::updateOrCreate(
                ['route' => $item['module']['route']],
                [
                    'name' => $item['module']['name'],
                    'route' => $item['module']['route'],
                    'relation' => $item['module']['relation'],
                    'order_index' => $item['module']['order_index'],
                    'status' => 1,
                ]
            );

            $permissionDocs[] = [
                'name' => $perm->name,
                'id' => (string) $perm->getKey(),
            ];
        }

        $role = RoleModel::where('name', 'super-admin')->first();
        if ($role) {
            $current = collect($role->getPermissionsList());
            foreach ($permissionDocs as $permission) {
                $exists = $current->contains(
                    fn ($item) => (string) ($item['id'] ?? '') === $permission['id']
                );
                if (! $exists) {
                    $current->push($permission);
                }
            }
            $role->permissions = $current->values()->all();
            $role->save();
        }

        $theme = ComponentThemeModel::first();
        $payload = [
            'landing_palette' => LandingPalette::defaults(),
            'landing_palette_preset' => 'azul',
        ];

        if (! $theme) {
            ComponentThemeModel::create([
                'styles' => [],
                'active_theme' => 'dark',
                ...$payload,
            ]);
        } elseif (empty($theme->landing_palette)) {
            $theme->update($payload);
        }
    }
}
