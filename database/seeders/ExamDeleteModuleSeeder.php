<?php

namespace Database\Seeders;

use App\Models\PermissionsModel;
use App\Models\RoleModel;
use Illuminate\Database\Seeder;

class ExamDeleteModuleSeeder extends Seeder
{
    public function run(): void
    {
        $perm = PermissionsModel::firstOrCreate(
            ['name' => 'exams.delete'],
            [
                'description' => 'Borrar exámenes (incluye preguntas e intentos)',
                'module' => 'exams.delete',
                'status' => 1,
                'icon' => '',
            ]
        );

        if (($perm->module ?? '') !== 'exams.delete' || (int) ($perm->status ?? 0) !== 1) {
            $perm->module = 'exams.delete';
            $perm->status = 1;
            $perm->description = $perm->description ?: 'Borrar exámenes (incluye preguntas e intentos)';
            $perm->save();
        }

        $importPerm = PermissionsModel::where('module', 'exams.import')->first();
        $importPermId = $importPerm ? (string) $importPerm->id : null;
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

            // Solo a roles que ya pueden cargar exámenes (gestión de contenido).
            $hasImport = $importPermId
                && collect($list)->contains(
                    fn ($p) => (string) ($p['id'] ?? '') === $importPermId
                );

            if (! $hasImport) {
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
