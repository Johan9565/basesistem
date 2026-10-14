<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use App\Models\User;
use App\Services\Tenancy\PermissionCacheService;
use Tests\TestCase;

class SuperAdminRolesAndAtomicPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCacheService::class)->invalidateAll();
    }

    public function test_non_superadmin_cannot_access_roles_module(): void
    {
        $role = RoleModel::firstOrCreate(['name' => 'Company Admin Role', 'role' => 'comp_admin'], ['status' => 1]);

        $normalUser = User::create([
            'name' => 'Normal Client Owner',
            'email' => 'client_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'user_type' => 'client',
            'role_id' => (string) $role->_id,
        ]);

        // Intentar acceder a /roles como usuario no superadmin debe responder 403
        $responseGet = $this->actingAs($normalUser)->get('/roles');
        $this->assertEquals(403, $responseGet->status());

        // Intentar crear un rol debe responder 403
        $responsePost = $this->actingAs($normalUser)->post('/roles', [
            'role' => 'Hacker Role',
        ]);
        $this->assertEquals(403, $responsePost->status());

        $normalUser->delete();
    }

    public function test_superadmin_can_access_roles_module(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Administrador Real',
            'email' => 'superadmin_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'user_type' => 'superadmin',
        ]);

        $this->assertTrue($superAdmin->isSuperAdmin());

        $response = $this->actingAs($superAdmin)->get('/roles');
        $response->assertStatus(200);

        $superAdmin->delete();
    }

    public function test_atomic_permissions_distinguish_view_and_create(): void
    {
        // Permiso atómico solo de lectura
        $viewPerm = PermissionsModel::firstOrCreate(
            ['name' => 'products.view'],
            ['module' => 'products', 'description' => 'Ver productos', 'status' => 1]
        );

        $createPerm = PermissionsModel::firstOrCreate(
            ['name' => 'products.create'],
            ['module' => 'products', 'description' => 'Crear productos', 'status' => 1]
        );

        // Rol que SOLO tiene products.view
        $readerRole = RoleModel::create([
            'name' => 'Reader Role ' . uniqid(),
            'role' => 'reader_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['id' => (string) $viewPerm->_id, 'name' => 'products.view'],
            ],
        ]);

        $readerUser = User::create([
            'name' => 'Usuario Lector',
            'email' => 'reader_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'user_type' => 'employee',
            'role_id' => (string) $readerRole->_id,
        ]);

        $company = Company::create([
            'name' => 'Empresa Test Atomic ' . uniqid(),
            'slug' => 'test-atomic-' . uniqid(),
            'user_id' => (string) $readerUser->_id,
            'status' => 'active',
            'modules' => ['inventory' => true],
        ]);

        CompanyUser::create([
            'company_id' => (string) $company->_id,
            'user_id' => (string) $readerUser->_id,
            'role_id' => (string) $readerRole->_id,
            'status' => 'active',
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        // 1. Con products.view debe poder acceder a GET /products
        $responseGet = $this->actingAs($readerUser)->get('/products');
        $responseGet->assertStatus(200);

        // 2. Sin products.create, intentar POST /products debe ser denegado (403 o redirección con flash error)
        $responsePost = $this->actingAs($readerUser)->post('/products', [
            'name' => 'Producto No Autorizado',
            'sku' => 'NOAUTH-1',
            'price' => 100,
            'stock' => 10,
        ]);

        // Middleware CheckPermission redirige con flash error o responde 403
        $this->assertTrue(
            $responsePost->status() === 403 ||
            $responsePost->status() === 302
        );

        if ($responsePost->status() === 302) {
            $responsePost->assertSessionHas('flash.type', 'error');
        }

        $readerUser->delete();
        $company->delete();
        $readerRole->delete();
    }

    public function test_roles_module_is_hidden_from_non_superadmin_menu(): void
    {
        $role = RoleModel::firstOrCreate(['name' => 'Client Role', 'role' => 'client_role'], ['status' => 1]);

        $normalUser = User::create([
            'name' => 'Cliente Sin Superadmin',
            'email' => 'cliente_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'user_type' => 'client',
            'role_id' => (string) $role->_id,
        ]);

        $response = $this->actingAs($normalUser)->get('/dashboard');
        $response->assertStatus(200);

        $auth = $response->viewData('page')['props']['auth'] ?? [];
        $topMenu = $auth['top_menu'] ?? [];
        $companyMenu = $auth['company_menu'] ?? [];

        // Verificar que 'roles' no está en el menú superior
        $hasRolesInTop = collect($topMenu)->contains(function ($item) {
            if (($item['route'] ?? '') === 'roles') return true;
            foreach ($item['children'] ?? [] as $child) {
                if (($child['route'] ?? '') === 'roles') return true;
            }
            return false;
        });
        $this->assertFalse($hasRolesInTop, 'Roles no debe aparecer en el top_menu para un usuario no superadmin');

        // Verificar que 'roles' no está en el menú lateral
        $hasRolesInCompany = collect($companyMenu)->contains(function ($section) {
            foreach ($section['items'] ?? [] as $item) {
                if (($item['route'] ?? '') === 'roles') return true;
            }
            return false;
        });
        $this->assertFalse($hasRolesInCompany, 'Roles no debe aparecer en el company_menu');

        $normalUser->delete();
    }
}
