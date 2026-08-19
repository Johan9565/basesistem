<?php

namespace Database\Seeders;

use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use Illuminate\Database\Seeder;

class ExamImportModuleSeeder extends Seeder
{
    public function run(): void
    {
        $perm = PermissionsModel::firstOrCreate(
            ['name' => 'exams.import'],
            [
                'description' => 'Cargar exámenes desde CSV',
                'module' => 'exams.import',
                'status' => 1,
                'icon' => '',
            ]
        );

        if (($perm->module ?? '') !== 'exams.import' || (int) ($perm->status ?? 0) !== 1) {
            $perm->module = 'exams.import';
            $perm->status = 1;
            $perm->description = $perm->description ?: 'Cargar exámenes desde CSV';
            $perm->save();
        }

        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4" /></svg>';

        ModulesModel::firstOrCreate(
            ['route' => 'exams.import'],
            [
                'name' => 'Cargar examen',
                'route' => 'exams.import',
                'status' => 1,
                'relation' => '',
                'order_index' => 12,
                'icon' => $icon,
            ]
        );

        $permId = (string) $perm->id;
        $roles = RoleModel::where('status', 1)->get();

        foreach ($roles as $role) {
            $list = $role->getPermissionsList();
            $already = collect($list)->contains(
                fn ($p) => (string) ($p['id'] ?? '') === $permId
            );

            if ($already) {
                continue;
            }

            $list[] = [
                'id' => $perm->id,
                'name' => $perm->name,
            ];

            RoleModel::where('_id', $role->getKey())->update(['permissions' => $list]);
        }
    }
}
