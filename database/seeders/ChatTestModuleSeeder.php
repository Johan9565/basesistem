<?php

namespace Database\Seeders;

use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use Illuminate\Database\Seeder;

class ChatTestModuleSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            [
                'name' => 'whatsapp.chat-test',
                'description' => 'Módulo Chat Test (Simulador de respuestas del bot)',
                'module' => 'whatsapp.chat-test',
                'status' => 1,
            ],
            [
                'name' => 'whatsapp.chat-test.view',
                'description' => 'Ver y acceder a la interfaz de Chat Test',
                'module' => 'whatsapp.chat-test',
                'status' => 1,
            ],
            [
                'name' => 'whatsapp.chat-test.send',
                'description' => 'Enviar mensajes de prueba en el simulador de bot',
                'module' => 'whatsapp.chat-test',
                'status' => 1,
            ],
            [
                'name' => 'whatsapp.chat-test.reset',
                'description' => 'Reiniciar historial de pruebas y estado de reserva',
                'module' => 'whatsapp.chat-test',
                'status' => 1,
            ],
        ];

        foreach ($perms as $p) {
            PermissionsModel::updateOrCreate(
                ['name' => $p['name']],
                $p
            );
        }

        // Registrar el módulo en la tabla de módulos para la navegación
        ModulesModel::updateOrCreate(
            ['route' => 'whatsapp.chat-test'],
            [
                'name' => 'Chat Test',
                'route' => 'whatsapp.chat-test',
                'relation' => 'whatsapp',
                'order_index' => 4,
                'status' => 1,
            ]
        );

        // Asignar los permisos a los roles existentes que ya posean permisos de whatsapp
        $allTestPermNames = ['whatsapp.chat-test', 'whatsapp.chat-test.view', 'whatsapp.chat-test.send', 'whatsapp.chat-test.reset'];
        
        $roles = RoleModel::all();
        foreach ($roles as $role) {
            $existingPerms = $role->permissions ?? [];
            if (! is_array($existingPerms)) {
                $existingPerms = [];
            }

            // Si es superadmin o si tiene algún permiso de whatsapp o es admin, le agregamos chat-test
            $hasAnyWa = in_array('whatsapp', $existingPerms, true)
                || in_array('whatsapp.instances.view', $existingPerms, true)
                || in_array('whatsapp.conversations.view', $existingPerms, true)
                || strtolower((string) ($role->role ?? '')) === 'super administrador'
                || strtolower((string) ($role->role ?? '')) === 'administrador';

            if ($hasAnyWa) {
                $merged = array_values(array_unique(array_merge($existingPerms, $allTestPermNames)));
                $role->permissions = $merged;
                $role->save();
            }
        }
    }
}
