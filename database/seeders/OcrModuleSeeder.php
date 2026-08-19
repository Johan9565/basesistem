<?php

namespace Database\Seeders;

use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use Illuminate\Database\Seeder;

class OcrModuleSeeder extends Seeder
{
    public function run(): void
    {
        $perm = PermissionsModel::firstOrCreate(
            ['name' => 'ocr'],
            [
                'description' => 'OCR: extraer texto de PDFs',
                'module' => 'ocr',
                'status' => 1,
                'icon' => '',
            ]
        );

        if (($perm->module ?? '') !== 'ocr' || (int) ($perm->status ?? 0) !== 1) {
            $perm->module = 'ocr';
            $perm->status = 1;
            $perm->description = $perm->description ?: 'OCR: extraer texto de PDFs';
            $perm->save();
        }

        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 10.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z" /></svg>';

        ModulesModel::firstOrCreate(
            ['route' => 'ocr'],
            [
                'name' => 'OCR',
                'route' => 'ocr',
                'status' => 1,
                'relation' => '',
                'order_index' => 25,
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
