<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\PermissionsModel;
use App\Models\Product;
use App\Models\RoleModel;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsappAppointment;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\PermissionCacheService;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class GlobalCompanyBranchIdentificationTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCacheService::class)->invalidateAll();
    }

    public function test_products_and_services_are_global_to_company_and_identified_by_branch(): void
    {
        // 1. Crear empresa con 2 sucursales
        $owner = User::create([
            'name' => 'Owner Multi Branch ' . uniqid(),
            'email' => 'owner_mb_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
        ]);

        $company = Company::create([
            'name' => 'Empresa Global ' . uniqid(),
            'slug' => 'empresa-global-' . uniqid(),
            'user_id' => (string) $owner->_id,
            'status' => 'active',
            'modules' => [
                'inventory' => true,
                'services' => true,
                'appointments' => true,
                'whatsapp' => true,
            ],
        ]);

        $mainBranch = Branch::where('company_id', (string) $company->_id)->where('is_main', true)->first();
        $this->assertNotNull($mainBranch);

        $secondBranch = Branch::create([
            'company_id' => (string) $company->_id,
            'name' => 'Sucursal Norte',
            'code' => 'NORTE-01',
            'is_main' => false,
            'is_active' => true,
        ]);

        // Rol con permisos de productos y servicios
        $role = RoleModel::create([
            'name' => 'Admin Products Services ' . uniqid(),
            'role' => 'admin_ps_' . uniqid(),
            'status' => 1,
            'permissions' => [
                ['name' => 'products'],
                ['name' => 'products.view'],
                ['name' => 'products.create'],
                ['name' => 'products.edit'],
                ['name' => 'services'],
                ['name' => 'services.view'],
                ['name' => 'services.create'],
                ['name' => 'services.edit'],
            ],
        ]);

        CompanyUser::create([
            'user_id' => (string) $owner->_id,
            'company_id' => (string) $company->_id,
            'role_id' => (string) $role->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        app(PermissionCacheService::class)->invalidateAll();

        // 2. Crear producto asignado a la sucursal 2
        $productResponse = $this->actingAs($owner)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->post(route('products.store'), [
                'branch_id' => (string) $secondBranch->_id,
                'sku' => 'SKU-NORTE-101',
                'name' => 'Producto Norte Especial',
                'price' => 150.50,
                'stock' => 20,
            ]);
        $productResponse->assertSessionHas('flash.type', 'success');

        $createdProduct = Product::where('company_id', (string) $company->_id)
            ->where('sku', 'SKU-NORTE-101')
            ->first();
        $this->assertNotNull($createdProduct);
        $this->assertEquals((string) $secondBranch->_id, (string) $createdProduct->branch_id);
        $this->assertEquals('Sucursal Norte', $createdProduct->branch->name);

        // 3. Crear servicio sin especificar sucursal -> se asigna automáticamente a la matriz (is_main = true)
        $serviceResponse = $this->actingAs($owner)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->post(route('services.store'), [
                'name' => 'Servicio Matriz',
                'duration_minutes' => 45,
                'price' => 250.00,
            ]);
        $serviceResponse->assertSessionHas('flash.type', 'success');

        $createdService = Service::where('company_id', (string) $company->_id)
            ->where('name', 'Servicio Matriz')
            ->first();
        $this->assertNotNull($createdService);
        $this->assertEquals((string) $mainBranch->_id, (string) $createdService->branch_id);
        $this->assertEquals((string) $mainBranch->name, $createdService->branch->name);

        // 4. Crear cita y verificar que tiene branch_id y relación
        $appointment = WhatsappAppointment::create([
            'company_id' => (string) $company->_id,
            'branch_id' => (string) $secondBranch->_id,
            'service_id' => (string) $createdService->_id,
            'summary' => 'Cita en Sucursal Norte',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(45),
            'user_phone' => '+5219999999999',
            'instance_name' => 'instance_test',
        ]);
        $this->assertEquals((string) $secondBranch->_id, (string) $appointment->branch_id);
        $this->assertEquals('Sucursal Norte', $appointment->branch->name);

        // 5. Verificar filtrado de productos por sucursal en el controlador
        $indexResponse = $this->actingAs($owner)
            ->withSession(['active_company_id' => (string) $company->_id])
            ->get(route('products', ['branch_id' => (string) $secondBranch->_id]));
        $indexResponse->assertStatus(200);

        $productsData = $indexResponse->viewData('page')['props']['products']['data'] ?? [];
        $this->assertCount(1, $productsData);
        $this->assertEquals('Sucursal Norte', $productsData[0]['branch_name']);

        // Limpieza
        $appointment->delete();
        $createdProduct->delete();
        $createdService->delete();
        Branch::where('company_id', (string) $company->_id)->delete();
        $company->delete();
        $owner->delete();
        $role->delete();
    }
}
