<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use App\Models\User;
use App\Services\Tenancy\PermissionCacheService;
use Tests\TestCase;

class CompanyPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCacheService::class)->invalidateAll();
    }

    public function test_user_without_companies_view_permission_is_redirected_with_error(): void
    {
        $role = RoleModel::create([
            'name' => 'Operador Sin Permiso ' . uniqid(),
            'role' => 'operador_' . uniqid(),
            'status' => 1,
            'permissions' => [],
        ]);

        $user = User::create([
            'name' => 'Usuario Sin Permisos',
            'email' => 'noperm_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'name' => 'Empresa Test ' . uniqid(),
            'slug' => 'empresa-test-' . uniqid(),
            'user_id' => (string) $user->_id,
            'status' => 'active',
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'role_id' => (string) $role->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        $response = $this->actingAs($user)->get('/companies');
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('flash.type', 'error');

        // Limpieza
        $company->delete();
        $user->delete();
        $role->delete();
    }

    public function test_company_admin_with_view_and_edit_can_view_companies_and_props(): void
    {
        $viewPerm = PermissionsModel::firstOrCreate(['name' => 'companies.view'], [
            'module' => 'companies',
            'description' => 'Ver listado y detalles de empresas',
            'status' => 1,
        ]);

        $editPerm = PermissionsModel::firstOrCreate(['name' => 'companies.edit'], [
            'module' => 'companies',
            'description' => 'Modificar información de empresas',
            'status' => 1,
        ]);

        $role = RoleModel::create([
            'name' => 'Company Admin Role ' . uniqid(),
            'role' => 'company_admin_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $viewPerm->_id, 'name' => 'companies.view'],
                ['id' => (string) $editPerm->_id, 'name' => 'companies.edit'],
            ],
        ]);

        $user = User::create([
            'name' => 'Admin de Empresa',
            'email' => 'admin_empresa_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'name' => 'Sucursal Central ' . uniqid(),
            'slug' => 'sucursal-central-' . uniqid(),
            'user_id' => (string) $user->_id,
            'status' => 'active',
            'modules' => ['inventory' => true, 'services' => false],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'role_id' => (string) $role->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        $response = $this->actingAs($user)->get('/companies');
        $response->assertStatus(200);

        $pageProps = $response->viewData('page')['props'] ?? [];
        $this->assertNotEmpty($pageProps['companies']);
        $this->assertFalse($pageProps['can']['create']);
        $this->assertTrue($pageProps['can']['edit']);
        $this->assertFalse($pageProps['can']['toggle_module']);
        $this->assertFalse($pageProps['can']['manage_owner']);

        // Limpieza
        $company->delete();
        $user->delete();
        $role->delete();
    }

    public function test_non_owner_employee_cannot_transfer_ownership_via_update(): void
    {
        $viewPerm = PermissionsModel::firstOrCreate(['name' => 'companies.view'], [
            'module' => 'companies',
            'status' => 1,
        ]);
        $editPerm = PermissionsModel::firstOrCreate(['name' => 'companies.edit'], [
            'module' => 'companies',
            'status' => 1,
        ]);

        $role = RoleModel::create([
            'name' => 'Editor Role ' . uniqid(),
            'role' => 'editor_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $viewPerm->_id, 'name' => 'companies.view'],
                ['id' => (string) $editPerm->_id, 'name' => 'companies.edit'],
            ],
        ]);

        $owner = User::create([
            'name' => 'Dueño Original',
            'email' => 'dueno_orig_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $employee = User::create([
            'name' => 'Empleado Editor',
            'email' => 'empleado_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $thirdParty = User::create([
            'name' => 'Tercero',
            'email' => 'tercero_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'name' => 'Empresa No Transferible ' . uniqid(),
            'slug' => 'empresa-no-trans-' . uniqid(),
            'user_id' => (string) $owner->_id,
            'status' => 'active',
        ]);

        // Membresía del empleado (con permiso de edición pero NO es dueño)
        CompanyUser::create([
            'user_id' => (string) $employee->_id,
            'company_id' => (string) $company->_id,
            'role_id' => (string) $role->_id,
            'status' => 'active',
            'is_owner' => false,
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        // El empleado intenta actualizar nombre y forzar user_id hacia $thirdParty
        $response = $this->actingAs($employee)->patch(route('companies.update', (string) $company->_id), [
            'user_id' => (string) $thirdParty->_id,
            'name' => 'Nombre Actualizado por Empleado',
            'status' => 'inactive',
        ]);

        $response->assertSessionHas('flash.type', 'success');

        $company = $company->fresh();
        // El nombre sí cambia porque el empleado tiene companies.edit
        $this->assertEquals('Nombre Actualizado por Empleado', $company->name);
        // Pero el dueño y el estado NO se modifican porque no es superadmin ni dueño legítimo
        $this->assertEquals((string) $owner->_id, (string) $company->user_id);
        $this->assertEquals('active', $company->status);

        // Limpieza
        $company->delete();
        $owner->delete();
        $employee->delete();
        $thirdParty->delete();
        $role->delete();
    }

    public function test_user_without_toggle_module_permission_cannot_toggle_modules(): void
    {
        $viewPerm = PermissionsModel::firstOrCreate(['name' => 'companies.view'], [
            'module' => 'companies',
            'status' => 1,
        ]);

        $role = RoleModel::create([
            'name' => 'View Only Role ' . uniqid(),
            'role' => 'view_only_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $viewPerm->_id, 'name' => 'companies.view'],
            ],
        ]);

        $user = User::create([
            'name' => 'Usuario Solo Ver',
            'email' => 'solover_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'name' => 'Empresa Modulos ' . uniqid(),
            'slug' => 'empresa-modulos-' . uniqid(),
            'user_id' => (string) $user->_id,
            'status' => 'active',
            'modules' => ['inventory' => true],
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'role_id' => (string) $role->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        // Intenta hacer toggle de módulo sin tener companies.toggle_module
        $response = $this->actingAs($user)->post(route('companies.toggle-module', (string) $company->_id), [
            'module' => 'inventory',
            'enabled' => false,
        ]);

        // Debe ser redirigido por CheckPermission middleware con error
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('flash.type', 'error');

        $company = $company->fresh();
        // El módulo sigue activo
        $this->assertTrue($company->isModuleEnabled('inventory'));

        // Limpieza
        $company->delete();
        $user->delete();
        $role->delete();
    }

    public function test_user_with_manage_owner_permission_can_transfer_company_ownership(): void
    {
        $viewPerm = PermissionsModel::firstOrCreate(['name' => 'companies.view'], ['module' => 'companies', 'status' => 1]);
        $editPerm = PermissionsModel::firstOrCreate(['name' => 'companies.edit'], ['module' => 'companies', 'status' => 1]);
        $manageOwnerPerm = PermissionsModel::firstOrCreate(['name' => 'companies.manage_owner'], ['module' => 'companies', 'status' => 1]);

        $role = RoleModel::create([
            'name' => 'Manager Role ' . uniqid(),
            'role' => 'manager_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $viewPerm->_id, 'name' => 'companies.view'],
                ['id' => (string) $editPerm->_id, 'name' => 'companies.edit'],
                ['id' => (string) $manageOwnerPerm->_id, 'name' => 'companies.manage_owner'],
            ],
        ]);

        $owner = User::create([
            'name' => 'Dueño Inicial',
            'email' => 'owner_init_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $newOwner = User::create([
            'name' => 'Nuevo Dueño Destino',
            'email' => 'new_owner_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $managerUser = User::create([
            'name' => 'Usuario Gestor',
            'email' => 'gestor_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'name' => 'Empresa Con Transferencia ' . uniqid(),
            'slug' => 'empresa-transf-' . uniqid(),
            'user_id' => (string) $owner->_id,
            'status' => 'active',
        ]);

        CompanyUser::create([
            'user_id' => (string) $managerUser->_id,
            'company_id' => (string) $company->_id,
            'role_id' => (string) $role->_id,
            'status' => 'active',
            'is_owner' => false,
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        // El usuario gestor (no es dueño pero tiene companies.manage_owner) transfiere la empresa a newOwner
        $response = $this->actingAs($managerUser)->patch(route('companies.update', (string) $company->_id), [
            'user_id' => (string) $newOwner->_id,
            'name' => 'Empresa Transferida con Éxito',
            'status' => 'active',
        ]);

        $response->assertSessionHas('flash.type', 'success');

        $company = $company->fresh();
        $this->assertEquals((string) $newOwner->_id, (string) $company->user_id);
        $this->assertEquals('Empresa Transferida con Éxito', $company->name);

        // Limpieza
        $company->delete();
        $owner->delete();
        $newOwner->delete();
        $managerUser->delete();
        $role->delete();
    }

    public function test_branch_crud_operations_work_properly_for_company(): void
    {
        $viewPerm = PermissionsModel::firstOrCreate(['name' => 'companies.view'], ['module' => 'companies', 'status' => 1]);
        $editPerm = PermissionsModel::firstOrCreate(['name' => 'companies.edit'], ['module' => 'companies', 'status' => 1]);
        $branchViewPerm = PermissionsModel::firstOrCreate(['name' => 'branches.view'], ['module' => 'branches', 'status' => 1]);
        $branchCreatePerm = PermissionsModel::firstOrCreate(['name' => 'branches.create'], ['module' => 'branches', 'status' => 1]);
        $branchEditPerm = PermissionsModel::firstOrCreate(['name' => 'branches.edit'], ['module' => 'branches', 'status' => 1]);
        $branchDeletePerm = PermissionsModel::firstOrCreate(['name' => 'branches.delete'], ['module' => 'branches', 'status' => 1]);

        $role = RoleModel::create([
            'name' => 'Company Admin Role ' . uniqid(),
            'role' => 'admin_role_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $viewPerm->_id, 'name' => 'companies.view'],
                ['id' => (string) $editPerm->_id, 'name' => 'companies.edit'],
                ['id' => (string) $branchViewPerm->_id, 'name' => 'branches.view'],
                ['id' => (string) $branchCreatePerm->_id, 'name' => 'branches.create'],
                ['id' => (string) $branchEditPerm->_id, 'name' => 'branches.edit'],
                ['id' => (string) $branchDeletePerm->_id, 'name' => 'branches.delete'],
            ],
        ]);

        $user = User::create([
            'name' => 'Admin Sucursales',
            'email' => 'admin_sucursales_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $company = Company::create([
            'name' => 'Empresa Multirama ' . uniqid(),
            'slug' => 'empresa-multirama-' . uniqid(),
            'user_id' => (string) $user->_id,
            'status' => 'active',
        ]);

        CompanyUser::create([
            'user_id' => (string) $user->_id,
            'company_id' => (string) $company->_id,
            'role_id' => (string) $role->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        // 1. La empresa al nacer ya tiene su primera sucursal principal creada automáticamente
        $mainBranch = \App\Models\Branch::where('company_id', (string) $company->_id)->first();
        $this->assertNotNull($mainBranch);
        $this->assertTrue($mainBranch->is_main);
        $this->assertEquals($company->name, $mainBranch->name);

        // 2. Crear segunda sucursal
        $responseCreate2 = $this->actingAs($user)->post(route('companies.branches.store', (string) $company->_id), [
            'name' => 'Sucursal Norte',
            'code' => 'SUC-02',
            'is_main' => false,
        ]);
        $responseCreate2->assertSessionHas('flash.type', 'success');
        $this->assertCount(2, $company->fresh()->branches);

        $branch2 = \App\Models\Branch::where('company_id', (string) $company->_id)->where('code', 'SUC-02')->first();
        $this->assertNotNull($branch2);
        $this->assertFalse($branch2->is_main);

        // 3. Editar segunda sucursal para convertirla en main
        $responseUpdate = $this->actingAs($user)->patch(route('companies.branches.update', [(string) $company->_id, (string) $branch2->_id]), [
            'name' => 'Sucursal Norte Actualizada',
            'is_main' => true,
        ]);
        $responseUpdate->assertSessionHas('flash.type', 'success');

        $branch2 = $branch2->fresh();
        $mainBranch = $mainBranch->fresh();
        $this->assertTrue($branch2->is_main);
        $this->assertFalse($mainBranch->is_main);

        // 4. Eliminar una sucursal
        $responseDelete = $this->actingAs($user)->delete(route('companies.branches.destroy', [(string) $company->_id, (string) $mainBranch->_id]));
        $responseDelete->assertSessionHas('flash.type', 'success');
        $this->assertNull(\App\Models\Branch::find($mainBranch->_id));

        // 5. Intentar eliminar la única sucursal restante (debe ser rechazado con error)
        $responseDeleteLast = $this->actingAs($user)->delete(route('companies.branches.destroy', [(string) $company->_id, (string) $branch2->_id]));
        $responseDeleteLast->assertSessionHas('flash.type', 'error');
        $this->assertNotNull(\App\Models\Branch::find($branch2->_id));

        // Limpieza
        \App\Models\Branch::where('company_id', (string) $company->_id)->delete();
        $company->delete();
        $user->delete();
        $role->delete();
    }

    public function test_creating_company_automatically_creates_its_first_branch_with_company_name(): void
    {
        $viewPerm = PermissionsModel::firstOrCreate(['name' => 'companies.view'], ['module' => 'companies', 'status' => 1]);
        $createPerm = PermissionsModel::firstOrCreate(['name' => 'companies.create'], ['module' => 'companies', 'status' => 1]);

        $role = RoleModel::create([
            'name' => 'Creator Role ' . uniqid(),
            'role' => 'creator_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $viewPerm->_id, 'name' => 'companies.view'],
                ['id' => (string) $createPerm->_id, 'name' => 'companies.create'],
            ],
        ]);

        $user = User::create([
            'name' => 'Dueño Creador',
            'email' => 'creador_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $role->_id,
        ]);

        $companyName = 'Ferretería La Central ' . uniqid();

        // Crear empresa a través del endpoint POST /companies
        $response = $this->actingAs($user)->post(route('companies.store'), [
            'name' => $companyName,
            'phone' => '555-987-6543',
            'email' => 'contacto@ferreteria.com',
        ]);

        $response->assertSessionHas('flash.type', 'success');

        $company = Company::where('name', $companyName)->first();
        $this->assertNotNull($company);

        // Verificar que la empresa automáticamente tiene su primera sucursal creada
        $branches = \App\Models\Branch::where('company_id', (string) $company->_id)->get();
        $this->assertCount(1, $branches);

        $firstBranch = $branches->first();
        $this->assertEquals($companyName, $firstBranch->name);
        $this->assertEquals('MATRIZ', $firstBranch->code);
        $this->assertTrue($firstBranch->is_main);
        $this->assertTrue($firstBranch->is_active);
        $this->assertEquals('555-987-6543', $firstBranch->phone);
        $this->assertEquals('contacto@ferreteria.com', $firstBranch->email);

        // Si se actualiza el nombre de la empresa, la primera sucursal matriz se mantiene sincronizada
        $newName = $companyName . ' Modificada';
        $company->update(['name' => $newName]);

        $firstBranch = $firstBranch->fresh();
        $this->assertEquals($newName, $firstBranch->name);

        // Limpieza
        \App\Models\Branch::where('company_id', (string) $company->_id)->delete();
        $company->delete();
        $user->delete();
        $role->delete();
    }

    public function test_user_with_manage_all_permission_can_select_and_configure_unowned_companies(): void
    {
        $viewPerm = PermissionsModel::firstOrCreate(['name' => 'companies.view'], ['module' => 'companies', 'status' => 1]);
        $editPerm = PermissionsModel::firstOrCreate(['name' => 'companies.edit'], ['module' => 'companies', 'status' => 1]);
        $manageAllPerm = PermissionsModel::firstOrCreate(['name' => 'companies.manage_all'], ['module' => 'companies', 'status' => 1]);

        $adminRole = RoleModel::create([
            'name' => 'Admin All Companies ' . uniqid(),
            'role' => 'admin_all_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $viewPerm->_id, 'name' => 'companies.view'],
                ['id' => (string) $editPerm->_id, 'name' => 'companies.edit'],
                ['id' => (string) $manageAllPerm->_id, 'name' => 'companies.manage_all'],
            ],
        ]);

        $companyOwner = User::create([
            'name' => 'Dueño Empresa Aislada',
            'email' => 'owner_isolated_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
        ]);

        $company = Company::create([
            'name' => 'Empresa Externa ' . uniqid(),
            'slug' => 'empresa-externa-' . uniqid(),
            'user_id' => (string) $companyOwner->_id,
            'status' => 'active',
        ]);

        $adminUser = User::create([
            'name' => 'Administrador Global',
            'email' => 'admin_global_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $adminRole->_id,
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        // 1. El usuario tiene canManageAllCompanies = true
        $this->assertTrue($adminUser->canManageAllCompanies());

        // 2. La empresa externa aparece en sus accessible company IDs
        $accessibleIds = $adminUser->getAccessibleCompanyIds();
        $this->assertContains((string) $company->_id, $accessibleIds);

        // 3. Puede seleccionar y alternar la empresa en el contexto de sesión
        $switchResponse = $this->actingAs($adminUser)->post(route('companies.switch', (string) $company->_id));
        $switchResponse->assertSessionHas('flash.type', 'success');
        $this->assertEquals((string) $company->_id, session('active_company_id'));

        // 4. Puede ver el listado de empresas y sus props incluyen can.manage_all = true
        $indexResponse = $this->actingAs($adminUser)->get('/companies');
        $indexResponse->assertStatus(200);
        $pageProps = $indexResponse->viewData('page')['props'] ?? [];
        $this->assertTrue($pageProps['can']['manage_all']);

        // 5. Puede configurar y actualizar los datos de la empresa aunque no sea suya
        $updateResponse = $this->actingAs($adminUser)->patch(route('companies.update', (string) $company->_id), [
            'user_id' => (string) $companyOwner->_id,
            'name' => 'Empresa Externa Configurada por Admin',
            'status' => 'active',
        ]);
        $updateResponse->assertSessionHas('flash.type', 'success');

        $company = $company->fresh();
        $this->assertEquals('Empresa Externa Configurada por Admin', $company->name);

        // Limpieza
        \App\Models\Branch::where('company_id', (string) $company->_id)->delete();
        $company->delete();
        $adminUser->delete();
        $companyOwner->delete();
        $adminRole->delete();
    }

    public function test_standard_user_without_manage_all_cannot_switch_or_configure_unowned_company(): void
    {
        $viewPerm = PermissionsModel::firstOrCreate(['name' => 'companies.view'], ['module' => 'companies', 'status' => 1]);
        $editPerm = PermissionsModel::firstOrCreate(['name' => 'companies.edit'], ['module' => 'companies', 'status' => 1]);

        $standardRole = RoleModel::create([
            'name' => 'Standard Role ' . uniqid(),
            'role' => 'standard_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $viewPerm->_id, 'name' => 'companies.view'],
                ['id' => (string) $editPerm->_id, 'name' => 'companies.edit'],
            ],
        ]);

        $companyOwner = User::create([
            'name' => 'Dueño Legítimo',
            'email' => 'owner_legit_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
        ]);

        $company = Company::create([
            'name' => 'Empresa Privada ' . uniqid(),
            'slug' => 'empresa-privada-' . uniqid(),
            'user_id' => (string) $companyOwner->_id,
            'status' => 'active',
        ]);

        $standardUser = User::create([
            'name' => 'Usuario Sin Permiso Global',
            'email' => 'standard_user_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $standardRole->_id,
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        // 1. canManageAllCompanies es false
        $this->assertFalse($standardUser->canManageAllCompanies());

        // 2. La empresa no aparece en accessible company IDs
        $accessibleIds = $standardUser->getAccessibleCompanyIds();
        $this->assertNotContains((string) $company->_id, $accessibleIds);

        // 3. Intento de alternar contexto es bloqueado con error
        $switchResponse = $this->actingAs($standardUser)->post(route('companies.switch', (string) $company->_id));
        $switchResponse->assertSessionHas('flash.type', 'error');

        // 4. Intento de modificar la empresa es rechazado con 403 Forbidden
        $updateResponse = $this->actingAs($standardUser)->patch(route('companies.update', (string) $company->_id), [
            'user_id' => (string) $companyOwner->_id,
            'name' => 'Intento De Hack Modificacion',
            'status' => 'active',
        ]);
        $updateResponse->assertStatus(403);

        // Limpieza
        \App\Models\Branch::where('company_id', (string) $company->_id)->delete();
        $company->delete();
        $standardUser->delete();
        $companyOwner->delete();
        $standardRole->delete();
    }

    public function test_admin_user_with_only_companies_view_edit_does_not_see_other_companies_or_branches(): void
    {
        $compViewPerm = PermissionsModel::firstOrCreate(['name' => 'companies.view'], [
            'module' => 'companies',
            'status' => 1,
        ]);
        $compEditPerm = PermissionsModel::firstOrCreate(['name' => 'companies.edit'], [
            'module' => 'companies',
            'status' => 1,
        ]);

        // Rol con nombre o slug 'admin' pero SOLO permisos de companies.view y companies.edit
        $adminRole = RoleModel::create([
            'name' => 'Admin de Prueba ' . uniqid(),
            'role' => 'admin',
            'status' => 1,
            'permissions' => [
                ['id' => (string) $compViewPerm->_id, 'name' => 'companies.view'],
                ['id' => (string) $compEditPerm->_id, 'name' => 'companies.edit'],
            ],
        ]);

        $adminUser = User::create([
            'name' => 'Usuario Admin Limitado',
            'email' => 'admin_limitado_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => (string) $adminRole->_id,
        ]);

        $otherOwner = User::create([
            'name' => 'Otro Dueño',
            'email' => 'otro_dueno_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
        ]);

        $ownCompany = Company::create([
            'name' => 'Empresa Propia ' . uniqid(),
            'slug' => 'empresa-propia-' . uniqid(),
            'user_id' => (string) $adminUser->_id,
            'status' => 'active',
        ]);

        $unownedCompany = Company::create([
            'name' => 'Empresa Ajena ' . uniqid(),
            'slug' => 'empresa-ajena-' . uniqid(),
            'user_id' => (string) $otherOwner->_id,
            'status' => 'active',
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        // 1. No puede gestionar todas las empresas
        $this->assertFalse($adminUser->canManageAllCompanies());
        $accessibleIds = $adminUser->getAccessibleCompanyIds();
        $this->assertContains((string) $ownCompany->_id, $accessibleIds);
        $this->assertNotContains((string) $unownedCompany->_id, $accessibleIds);

        // 2. En /companies solo ve su empresa propia
        $response = $this->actingAs($adminUser)->get('/companies');
        $response->assertStatus(200);
        $companiesInView = $response->viewData('page')['props']['companies'] ?? [];
        $idsInView = collect($companiesInView)->pluck('id')->toArray();
        $this->assertContains((string) $ownCompany->_id, $idsInView);
        $this->assertNotContains((string) $unownedCompany->_id, $idsInView);

        // 3. can array refleja permisos de sucursales en false
        $canProps = $response->viewData('page')['props']['can'] ?? [];
        $this->assertFalse($canProps['branches_view']);
        $this->assertFalse($canProps['branches_create']);
        $this->assertFalse($canProps['branches_edit']);
        $this->assertFalse($canProps['branches_delete']);

        // 4. Intento de crear sucursal es bloqueado por falta de branches.create
        $storeBranchResponse = $this->actingAs($adminUser)->post(route('companies.branches.store', (string) $ownCompany->_id), [
            'name' => 'Sucursal No Permitida',
        ]);
        $storeBranchResponse->assertRedirect(route('dashboard'));
        $storeBranchResponse->assertSessionHas('flash.type', 'error');

        // Limpieza
        \App\Models\Branch::where('company_id', (string) $ownCompany->_id)->delete();
        \App\Models\Branch::where('company_id', (string) $unownedCompany->_id)->delete();
        $ownCompany->delete();
        $unownedCompany->delete();
        $adminUser->delete();
        $otherOwner->delete();
        $adminRole->delete();
    }
}

