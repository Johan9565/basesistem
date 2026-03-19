<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PermissionsModel;
use App\Models\ModulesModel;

class ComponentsModuleSeeder extends Seeder
{
    public function run(): void
    {
        $perm = PermissionsModel::firstOrCreate(
            ['name' => 'components'],
            [
                'description' => 'Módulo de componentes y estilos',
                'module'      => 'components',
                'status'      => 1,
            ]
        );

        ModulesModel::firstOrCreate(
            ['route' => 'components'],
            [
                'name'         => 'Componentes',
                'route'       => 'components',
                'status'      => 1,
                'relation'    => '',
                'order_index' => 50,
            ]
        );
    }
}
