<?php

namespace Tests\Feature;

use App\Models\ModulesModel;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use App\Models\User;
use Database\Seeders\ModulesAndPermissionsSeeder;
use Tests\TestCase;

class PermissionsAndModulesTest extends TestCase
{
    public function test_seeder_configures_valid_permissions_and_modules(): void
    {
        $this->seed(ModulesAndPermissionsSeeder::class);

        // Verify valid modules exist
        $this->assertTrue(ModulesModel::where('route', 'dashboard')->exists());
        $this->assertTrue(ModulesModel::where('route', 'assistant.workspace')->exists());
        $this->assertTrue(ModulesModel::where('route', 'administration')->exists());
        $this->assertTrue(ModulesModel::where('route', 'users')->exists());
        $this->assertTrue(ModulesModel::where('route', 'roles')->exists());

        // Verify obsolete modules are removed
        $this->assertFalse(ModulesModel::whereIn('route', ['components', 'businesscard', 'userpage', 'dependencies'])->exists());

        // Verify valid permissions exist
        $this->assertTrue(PermissionsModel::where('module', 'assistant.workspace')->exists());
        $this->assertTrue(PermissionsModel::where('module', 'dashboard')->exists());
        $this->assertTrue(PermissionsModel::where('module', 'administration')->exists());
        $this->assertTrue(PermissionsModel::where('module', 'users')->exists());
        $this->assertTrue(PermissionsModel::where('module', 'roles')->exists());

        $this->assertTrue(ModulesModel::where('route', 'whatsapp.assistant')->exists());
        $this->assertTrue(PermissionsModel::where('module', 'whatsapp.assistant')->exists());

        // Verify obsolete permissions are removed
        $this->assertFalse(PermissionsModel::whereIn('module', ['components', 'dependencies', 'userpage', 'businesscard'])->exists());
    }

    public function test_user_with_permission_can_access_assistant(): void
    {
        $this->seed(ModulesAndPermissionsSeeder::class);

        $role = RoleModel::where('name', 'super-admin')->first();
        $user = User::factory()->create([
            'role_id' => (string) $role->id,
        ]);

        $response = $this->actingAs($user)->get('/assistant');
        $response->assertOk();
    }

    public function test_user_with_permission_can_access_whatsapp(): void
    {
        $this->seed(ModulesAndPermissionsSeeder::class);

        $role = RoleModel::where('name', 'super-admin')->first();
        $user = User::factory()->create([
            'role_id' => (string) $role->id,
        ]);

        $response = $this->actingAs($user)->get('/whatsapp');
        $response->assertOk();
    }

    public function test_user_without_permission_is_redirected_from_assistant(): void
    {
        $roleWithoutAssistant = RoleModel::create([
            'name' => 'restricted-role',
            'role' => 'Restringido',
            'status' => 1,
            'permissions' => [],
        ]);

        $user = User::factory()->create([
            'role_id' => (string) $roleWithoutAssistant->id,
        ]);

        $response = $this->actingAs($user)->get('/assistant');
        $response->assertRedirect('/dashboard');

        $responseWa = $this->actingAs($user)->get('/whatsapp');
        $responseWa->assertRedirect('/dashboard');
    }
}
