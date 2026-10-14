<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use App\Models\User;
use App\Services\Tenancy\PermissionCacheService;
use Tests\TestCase;

class CompanyOwnershipAndScopingTest extends TestCase
{
    public function test_user_without_company_has_no_active_company_assigned(): void
    {
        $role = RoleModel::firstOrCreate(['name' => 'Test User', 'role' => 'test_user'], ['status' => 1]);

        $unassignedUser = User::create([
            'name' => 'Usuario Sin Empresa',
            'email' => 'sinempresa_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $this->assertCount(0, $unassignedUser->getAssignedCompanyIds());

        $response = $this->actingAs($unassignedUser)->get('/dashboard');
        $response->assertStatus(200);

        // En props auth, active_company y user_companies deben estar vacíos
        $auth = $response->viewData('page')['props']['auth'] ?? [];
        $this->assertNull($auth['active_company']);
        $this->assertCount(0, $auth['user_companies']);

        $unassignedUser->delete();
    }

    public function test_user_with_assigned_company_sees_only_their_company(): void
    {
        $adminPerm = PermissionsModel::firstOrCreate(['module' => 'administration'], [
            'name' => 'Administración',
            'status' => 1,
        ]);

        $role = RoleModel::create([
            'name' => 'Owner Role ' . uniqid(),
            'role' => 'owner_role_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $adminPerm->_id, 'name' => 'Administración'],
            ],
        ]);

        $ownerUser = User::create([
            'name' => 'Dueño Empresa Real',
            'email' => 'dueno_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $otherUser = User::create([
            'name' => 'Otro Usuario',
            'email' => 'otro_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        // Crear empresa asignada a ownerUser
        $companyOwner = Company::create([
            'name' => 'Empresa de Owner ' . uniqid(),
            'slug' => 'empresa-owner-' . uniqid(),
            'user_id' => (string) $ownerUser->_id,
            'status' => 'active',
            'modules' => ['inventory' => true, 'services' => true],
        ]);

        // Crear empresa asignada a otherUser
        $companyOther = Company::create([
            'name' => 'Empresa de Other ' . uniqid(),
            'slug' => 'empresa-other-' . uniqid(),
            'user_id' => (string) $otherUser->_id,
            'status' => 'active',
            'modules' => ['inventory' => true, 'services' => true],
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        // Verificar getAssignedCompanyIds
        $assignedIds = $ownerUser->getAssignedCompanyIds();
        $this->assertContains((string) $companyOwner->_id, $assignedIds);
        $this->assertNotContains((string) $companyOther->_id, $assignedIds);

        // Simular visita a /dashboard
        $responseDashboard = $this->actingAs($ownerUser)->get('/dashboard');
        $responseDashboard->assertStatus(200);
        $authDashboard = $responseDashboard->viewData('page')['props']['auth'] ?? [];
        $this->assertEquals((string) $companyOwner->_id, $authDashboard['active_company']['id']);
        $this->assertCount(1, $authDashboard['user_companies']);
        $this->assertEquals((string) $companyOwner->_id, $authDashboard['user_companies'][0]['id']);

        // Simular visita a /companies
        $responseCompanies = $this->actingAs($ownerUser)->get('/companies');
        $responseCompanies->assertStatus(200);

        $companiesInView = $responseCompanies->viewData('page')['props']['companies'] ?? [];
        $companiesIdsInView = collect($companiesInView)->pluck('id')->toArray();

        $this->assertContains((string) $companyOwner->_id, $companiesIdsInView);
        $this->assertNotContains((string) $companyOther->_id, $companiesIdsInView);

        // Limpieza
        $companyOwner->delete();
        $companyOther->delete();
        $ownerUser->delete();
        $otherUser->delete();
        $role->delete();
    }

    public function test_transferring_company_ownership_completely_removes_company_from_previous_owner(): void
    {
        $adminPerm = PermissionsModel::firstOrCreate(['module' => 'administration'], [
            'name' => 'Administración',
            'status' => 1,
        ]);

        $role = RoleModel::create([
            'name' => 'Admin Role ' . uniqid(),
            'role' => 'admin_role_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $adminPerm->_id, 'name' => 'Administración'],
            ],
        ]);

        $firstOwner = User::create([
            'name' => 'Dueño Inicial',
            'email' => 'primer_dueno_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $newOwner = User::create([
            'name' => 'Nuevo Dueño',
            'email' => 'nuevo_dueno_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        // Crear empresa con firstOwner
        $company = Company::create([
            'name' => 'Empresa Transferible ' . uniqid(),
            'slug' => 'empresa-trans-' . uniqid(),
            'user_id' => (string) $firstOwner->_id,
            'status' => 'active',
            'modules' => ['inventory' => true],
        ]);

        CompanyUser::create([
            'user_id' => (string) $firstOwner->_id,
            'company_id' => (string) $company->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        // Verificar que inicialmente firstOwner tiene la empresa
        $this->assertContains((string) $company->_id, $firstOwner->getAssignedCompanyIds());
        $this->assertNotContains((string) $company->_id, $newOwner->getAssignedCompanyIds());

        // Transferir la empresa a newOwner a través del endpoint PATCH /companies/{company}
        $response = $this->actingAs($firstOwner)->patch(route('companies.update', (string) $company->_id), [
            'user_id' => (string) $newOwner->_id,
            'name' => $company->name,
            'status' => 'active',
        ]);

        $response->assertSessionHas('flash.type', 'success');

        // Refrescar instancias
        $firstOwner = $firstOwner->fresh();
        $newOwner = $newOwner->fresh();
        $company = $company->fresh();

        // 1. Verificar que el user_id de la empresa es el nuevo dueño
        $this->assertEquals((string) $newOwner->_id, (string) $company->user_id);

        // 2. Verificar que el dueño anterior YA NO TIENE la empresa asignada
        $this->assertNotContains((string) $company->_id, $firstOwner->getAssignedCompanyIds());

        // 3. Verificar que el nuevo dueño SÍ TIENE la empresa asignada
        $this->assertContains((string) $company->_id, $newOwner->getAssignedCompanyIds());

        // 4. Verificar que no queda membresía de firstOwner para esa empresa
        $oldMembership = CompanyUser::where('company_id', (string) $company->_id)
            ->where('user_id', (string) $firstOwner->_id)
            ->first();
        $this->assertNull($oldMembership);

        // Limpieza
        $company->delete();
        $firstOwner->delete();
        $newOwner->delete();
        $role->delete();
    }
}
