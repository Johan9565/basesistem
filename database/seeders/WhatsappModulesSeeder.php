<?php

namespace Database\Seeders;

use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use Illuminate\Database\Seeder;

class WhatsappModulesSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'permission' => [
                    'name' => 'whatsapp',
                    'module' => 'whatsapp',
                    'description' => 'Sección WhatsApp',
                ],
                'module' => [
                    'name' => 'WhatsApp',
                    'route' => 'whatsapp',
                    'relation' => 0,
                    'order_index' => 10,
                ],
            ],
            [
                'permission' => [
                    'name' => 'whatsapp-instancias',
                    'module' => 'whatsapp.instances',
                    'description' => 'Gestión de instancias WhatsApp',
                ],
                'module' => [
                    'name' => 'Instancias',
                    'route' => 'whatsapp.instances',
                    'relation' => 'whatsapp',
                    'order_index' => 1,
                ],
            ],
            [
                'permission' => [
                    'name' => 'whatsapp-conversaciones',
                    'module' => 'whatsapp.conversations',
                    'description' => 'Conversaciones WhatsApp',
                ],
                'module' => [
                    'name' => 'Conversaciones',
                    'route' => 'whatsapp.conversations',
                    'relation' => 'whatsapp',
                    'order_index' => 2,
                ],
            ],
            [
                'permission' => [
                    'name' => 'whatsapp-calendario',
                    'module' => 'whatsapp.calendar',
                    'description' => 'Calendario de citas WhatsApp',
                ],
                'module' => [
                    'name' => 'Calendario',
                    'route' => 'whatsapp.calendar',
                    'relation' => 'whatsapp',
                    'order_index' => 3,
                ],
            ],
            [
                'permission' => [
                    'name' => 'whatsapp-enviar',
                    'module' => 'whatsapp.send',
                    'description' => 'Enviar mensaje de prueba WhatsApp',
                ],
                'module' => [
                    'name' => 'Enviar mensaje',
                    'route' => 'whatsapp.send',
                    'relation' => 'whatsapp',
                    'order_index' => 4,
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

        $role = RoleModel::where('name', 'super-admin')->first()
            ?? RoleModel::where('name', 'admin')->first()
            ?? RoleModel::query()->first();

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
    }
}
