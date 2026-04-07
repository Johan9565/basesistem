<?php

namespace Database\Seeders;

use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use Illuminate\Database\Seeder;

/**
 * Crea el permiso y el ítem de menú para el módulo MIR / programas presupuestarios.
 *
 * El middleware usa permissions.module === 'programs'.
 * HandleInertiaRequests filtra modules donde route está en la lista de module del rol.
 * AuthenticatedLayout hace route(modules.route) → el nombre de ruta Laravel debe ser exactamente "programs".
 *
 * Tras el seeder: asigna el permiso al rol correspondiente (pantalla de roles o MongoDB).
 */
class ProgramsModuleSeeder extends Seeder
{
    public function run(): void
    {
        PermissionsModel::firstOrCreate(
            ['name' => 'programs'],
            [
                'description' => 'Programas presupuestarios y matriz MIR',
                'module' => 'programs',
                'status' => 1,
            ]
        );

        ModulesModel::firstOrCreate(
            ['route' => 'programs'],
            [
                'name' => 'Programas MIR',
                'route' => 'programs',
                'status' => 1,
                'relation' => '',
                'order_index' => 35,
            ]
        );
    }
}
