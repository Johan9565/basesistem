<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\RoleModel;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class WhatsappInstancePermissionsAndOptionalCalendarTest extends TestCase
{
    /**
     * Verifica que se pueda crear una instancia sin Google Calendar ID ni archivo JSON de credenciales.
     */
    public function test_can_create_instance_without_google_calendar_and_without_credentials_json(): void
    {
        $role = RoleModel::create([
            'name' => 'Admin WA ' . uniqid(),
            'role' => 'admin_wa_' . uniqid(),
            'status' => 1,
            'permissions' => ['whatsapp', 'whatsapp.instances', 'whatsapp.instances.view', 'whatsapp.instances.create'],
        ]);

        $user = User::create([
            'name' => 'Usuario WA Test',
            'email' => 'user_wa_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'user_id' => (string) $user->_id,
            'name' => 'Empresa WA ' . uniqid(),
            'slug' => 'empresa-wa-' . uniqid(),
            'status' => 'active',
            'modules' => ['whatsapp' => true],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        $instanceName = 'test_inst_' . uniqid();

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->post('/whatsapp/instances', [
                'instance_name' => $instanceName,
                'business_name' => 'Negocio Sin Calendar',
                'google_calendar_id' => '', // vacío / opcional
                'credentials_file' => null, // sin archivo JSON
                'ai_provider' => 'deepseek',
                'status' => 'active',
                'create_in_evolution' => false,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('whatsapp.instances'));

        $instance = WhatsappInstance::withoutGlobalScopes()->where('instance_name', $instanceName)->first();
        $this->assertNotNull($instance);
        $this->assertEquals((string) $company->_id, (string) $instance->company_id);
        $this->assertNull($instance->google_calendar_id);
        $this->assertNull($instance->google_credentials_path);
        $this->assertNull($instance->google_service_email);
    }

    /**
     * Verifica que se pueda actualizar una instancia sin requerir Google Calendar ID.
     */
    public function test_can_update_instance_without_google_calendar_id(): void
    {
        $role = RoleModel::create([
            'name' => 'Admin WA Edit ' . uniqid(),
            'role' => 'admin_wa_edit_' . uniqid(),
            'status' => 1,
            'permissions' => ['whatsapp', 'whatsapp.instances', 'whatsapp.instances.view', 'whatsapp.instances.edit'],
        ]);

        $user = User::create([
            'name' => 'Usuario WA Edit',
            'email' => 'user_wa_edit_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'user_id' => (string) $user->_id,
            'name' => 'Empresa Edit ' . uniqid(),
            'slug' => 'empresa-edit-' . uniqid(),
            'status' => 'active',
            'modules' => ['whatsapp' => true],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        $instance = WhatsappInstance::create([
            'company_id' => (string) $company->_id,
            'instance_name' => 'inst_to_edit_' . uniqid(),
            'evolution_instance_name' => 'evo_edit_' . uniqid(),
            'google_calendar_id' => 'prev_cal@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->patch('/whatsapp/instances/' . $instance->_id, [
                'business_name' => 'Nombre Comercial Actualizado',
                'google_calendar_id' => '', // ahora vacío
                'ai_provider' => 'mimo',
                'status' => 'active',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('whatsapp.instances'));

        $instance->refresh();
        $this->assertNull($instance->google_calendar_id);
        $this->assertEquals('mimo', $instance->ai_provider);
    }

    /**
     * Verifica que un usuario sin permiso companies.select_all / companies.manage_all
     * solo pueda ver y acceder a instancias de sus empresas asignadas.
     */
    public function test_user_without_permission_to_view_all_companies_is_scoped_to_assigned_companies(): void
    {
        $role = RoleModel::create([
            'name' => 'WA Scoped ' . uniqid(),
            'role' => 'wa_scoped_' . uniqid(),
            'status' => 1,
            'permissions' => ['whatsapp', 'whatsapp.instances', 'whatsapp.instances.view', 'whatsapp.instances.edit'],
        ]);

        $user = User::create([
            'name' => 'Usuario Restringido',
            'email' => 'scoped_wa_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        // Empresa propia del usuario
        $ownCompany = Company::create([
            'user_id' => (string) $user->_id,
            'name' => 'Mi Empresa ' . uniqid(),
            'slug' => 'mi-empresa-' . uniqid(),
            'status' => 'active',
            'modules' => ['whatsapp' => true],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $ownCompany->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        // Empresa ajena
        $otherCompany = Company::create([
            'user_id' => (string) new \MongoDB\BSON\ObjectId(),
            'name' => 'Empresa Ajena ' . uniqid(),
            'slug' => 'empresa-ajena-' . uniqid(),
            'status' => 'active',
            'modules' => ['whatsapp' => true],
        ]);

        // Instancia propia
        $ownInstance = WhatsappInstance::create([
            'company_id' => (string) $ownCompany->_id,
            'instance_name' => 'own_inst_' . uniqid(),
            'evolution_instance_name' => 'own_evo_' . uniqid(),
            'status' => 'active',
        ]);

        // Instancia ajena
        $otherInstance = WhatsappInstance::create([
            'company_id' => (string) $otherCompany->_id,
            'instance_name' => 'other_inst_' . uniqid(),
            'evolution_instance_name' => 'other_evo_' . uniqid(),
            'status' => 'active',
        ]);

        // 1. En el index solo debe ver la propia
        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $ownCompany->_id])
            ->get('/whatsapp/instances');

        $response->assertStatus(200);
        $instancesInPage = $response->viewData('page')['props']['instances'];
        $instanceNames = collect($instancesInPage)->pluck('instance_name')->all();

        $this->assertContains($ownInstance->instance_name, $instanceNames);
        $this->assertNotContains($otherInstance->instance_name, $instanceNames);

        // 2. Intentar actualizar la instancia ajena debe ser rechazado (403)
        $forbiddenResponse = $this->actingAs($user)
            ->withSession(['active_company_id' => (string) $ownCompany->_id])
            ->patch('/whatsapp/instances/' . $otherInstance->_id, [
                'business_name' => 'Hackeando Otra Empresa',
                'google_calendar_id' => '',
                'ai_provider' => 'deepseek',
                'status' => 'active',
            ]);

        $forbiddenResponse->assertStatus(403);
    }

    /**
     * Verifica que un usuario con permiso companies.select_all pueda ver y gestionar instancias de todas las empresas.
     */
    public function test_user_with_permission_can_see_and_manage_all_companies_instances(): void
    {
        $role = RoleModel::create([
            'name' => 'WA Super ' . uniqid(),
            'role' => 'wa_super_' . uniqid(),
            'status' => 1,
            'permissions' => ['whatsapp', 'whatsapp.instances', 'whatsapp.instances.view', 'whatsapp.instances.edit', 'companies.select_all'],
        ]);

        $user = User::create([
            'name' => 'Usuario Con Permiso All',
            'email' => 'super_wa_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $companyA = Company::create([
            'user_id' => (string) new \MongoDB\BSON\ObjectId(),
            'name' => 'Empresa Alfa ' . uniqid(),
            'slug' => 'empresa-alfa-' . uniqid(),
            'status' => 'active',
            'modules' => ['whatsapp' => true],
        ]);

        $companyB = Company::create([
            'user_id' => (string) new \MongoDB\BSON\ObjectId(),
            'name' => 'Empresa Beta ' . uniqid(),
            'slug' => 'empresa-beta-' . uniqid(),
            'status' => 'active',
            'modules' => ['whatsapp' => true],
        ]);

        $instanceA = WhatsappInstance::create([
            'company_id' => (string) $companyA->_id,
            'instance_name' => 'alfa_inst_' . uniqid(),
            'status' => 'active',
        ]);

        $instanceB = WhatsappInstance::create([
            'company_id' => (string) $companyB->_id,
            'instance_name' => 'beta_inst_' . uniqid(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->get('/whatsapp/instances');

        $response->assertStatus(200);
        $instancesInPage = $response->viewData('page')['props']['instances'];
        $instanceNames = collect($instancesInPage)->pluck('instance_name')->all();

        $this->assertContains($instanceA->instance_name, $instanceNames);
        $this->assertContains($instanceB->instance_name, $instanceNames);
    }
}
