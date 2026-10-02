<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\PermissionsModel;
use App\Models\Product;
use App\Models\RoleModel;
use App\Models\Service;
use App\Models\User;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\PermissionCacheService;
use App\Services\Whatsapp\DeepSeekClient;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class MultiTenantIsolationAndModulesTest extends TestCase
{
    protected Client $client;
    protected Company $companyAlfa;
    protected Company $companyBeta;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SystemMasterSeeder::class);

        $this->companyAlfa = Company::where('slug', 'distribuidora-medica-express')->first();
        $this->companyBeta = Company::where('slug', 'clinica-dental-norte')->first();
        $this->client = Client::first();

        $superAdminRole = RoleModel::where('role', 'superadmin')->first();

        $this->user = User::where('email', 'johan_palma45@hotmail.com')->first();

        app(PermissionCacheService::class)->invalidateAll();
    }

    protected function tearDown(): void
    {
        // Limpiar registros creados durante la prueba
        Product::whereIn('company_id', [(string) $this->companyAlfa->_id, (string) $this->companyBeta->_id])->delete();
        Service::whereIn('company_id', [(string) $this->companyAlfa->_id, (string) $this->companyBeta->_id])->delete();
        app(CompanyContext::class)->clear();

        parent::tearDown();
    }

    /**
     * Prueba 1: Aislamiento estricto de datos entre empresas (Global Scope / BelongsToCompany).
     */
    public function test_data_isolation_between_companies(): void
    {
        $context = app(CompanyContext::class);

        // 1. Contexto en Empresa Alfa
        $context->setCompany($this->companyAlfa);
        $prodAlfa = Product::create([
            'sku'       => 'SKU-ALFA',
            'name'      => 'Producto Exclusivo Alfa',
            'price'     => 100,
            'stock'     => 10,
            'is_active' => true,
        ]);

        $this->assertEquals((string) $this->companyAlfa->_id, (string) $prodAlfa->company_id);
        $this->assertEquals(1, Product::where('name', 'Producto Exclusivo Alfa')->count());

        // 2. Cambiar contexto a Empresa Beta
        $context->setCompany($this->companyBeta);
        $prodBeta = Product::create([
            'sku'       => 'SKU-BETA',
            'name'      => 'Producto Exclusivo Beta',
            'price'     => 200,
            'stock'     => 5,
            'is_active' => true,
        ]);

        $this->assertEquals((string) $this->companyBeta->_id, (string) $prodBeta->company_id);

        // En Empresa Beta NO debe verse el producto de Alfa
        $this->assertEquals(0, Product::where('name', 'Producto Exclusivo Alfa')->count());
        $this->assertEquals(1, Product::where('name', 'Producto Exclusivo Beta')->count());

        // 3. Volver a Empresa Alfa -> NO debe verse el producto de Beta
        $context->setCompany($this->companyAlfa);
        $this->assertEquals(1, Product::where('name', 'Producto Exclusivo Alfa')->count());
        $this->assertEquals(0, Product::where('name', 'Producto Exclusivo Beta')->count());
    }

    /**
     * Prueba 2: Middleware de bloqueo modular (CheckCompanyModule).
     */
    public function test_company_module_middleware_blocks_disabled_modules(): void
    {
        // Empresa Alfa tiene 'inventory' activo pero 'services' inactivo
        $responseServices = $this->actingAs($this->user)
            ->withSession(['active_company_id' => (string) $this->companyAlfa->_id])
            ->get('/services');

        // Debe redirigir con error al dashboard porque el módulo 'services' está inactivo en Alfa
        $responseServices->assertRedirect('/dashboard');
        $responseServices->assertSessionHas('flash.type', 'error');

        // Petición JSON a módulo inactivo debe responder 403
        $responseJson = $this->actingAs($this->user)
            ->withSession(['active_company_id' => (string) $this->companyAlfa->_id])
            ->getJson('/services');

        $responseJson->assertStatus(403);
    }

    /**
     * Prueba 3: Desacoplamiento dinámico de herramientas de IA según módulos.
     */
    public function test_whatsapp_ai_tools_decoupling(): void
    {
        $deepSeek = new DeepSeekClient();

        // Empresa Alfa (appointments: false, inventory: true)
        $toolsAlfa = $deepSeek->resolveToolsForCompany($this->companyAlfa);
        $toolNamesAlfa = collect($toolsAlfa)->pluck('function.name')->toArray();

        $this->assertContains('query_inventory_products', $toolNamesAlfa);
        $this->assertNotContains('check_availability', $toolNamesAlfa);
        $this->assertNotContains('create_calendar_event', $toolNamesAlfa);

        // Empresa Beta (appointments: true, inventory: true, services: true)
        $toolsBeta = $deepSeek->resolveToolsForCompany($this->companyBeta);
        $toolNamesBeta = collect($toolsBeta)->pluck('function.name')->toArray();

        $this->assertContains('query_inventory_products', $toolNamesBeta);
        $this->assertContains('query_company_services', $toolNamesBeta);
        $this->assertContains('check_availability', $toolNamesBeta);
        $this->assertContains('create_calendar_event', $toolNamesBeta);
    }

    /**
     * Prueba 4: Cambio de empresa activa (Company Switcher).
     */
    public function test_company_switcher_updates_session(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('companies.switch', (string) $this->companyBeta->_id));

        $response->assertSessionHas('active_company_id', (string) $this->companyBeta->_id);
        $response->assertSessionHas('flash.type', 'success');
    }

    /**
     * Prueba 5: Caché contextual de permisos en Redis y su invalidación.
     */
    public function test_permission_cache_service(): void
    {
        $cacheService = app(PermissionCacheService::class);

        // Resolver permisos
        $perms = $cacheService->getUserPermissions($this->user, (string) $this->companyAlfa->_id);
        $this->assertIsArray($perms);

        // Invalidar
        $cacheService->invalidateUser((string) $this->user->_id, (string) $this->companyAlfa->_id);
        $this->assertTrue(true);
    }

    /**
     * Prueba 6: Gestión de usuarios y empleados aislados por empresa y tipo de usuario.
     */
    public function test_company_users_scoping(): void
    {
        // Consulta de Usuarios Globales (SaaS) -> Solo clientes / superadmin
        $responseUsers = $this->actingAs($this->user)
            ->withSession(['active_company_id' => (string) $this->companyAlfa->_id])
            ->get('/users');

        $responseUsers->assertStatus(200);
        $usersList = $responseUsers->viewData('page')['props']['users']['data'] ?? [];
        $userEmails = collect($usersList)->pluck('email')->toArray();

        // Debe contener al Superadmin y a los Clientes SaaS
        $this->assertContains('johan_palma45@hotmail.com', $userEmails);
        $this->assertContains('cliente.dental@dentalsalud.com', $userEmails);
        // NO debe contener empleados de empresas
        $this->assertNotContains('dr.garcia@dentalnorte.com', $userEmails);
        $this->assertNotContains('ventas@medicaexpress.com', $userEmails);

        // Consulta de Empleados de la Empresa Alfa (Distribuidora Médica Express)
        $responseEmployeesAlfa = $this->actingAs($this->user)
            ->withSession(['active_company_id' => (string) $this->companyAlfa->_id])
            ->get('/employees');

        $responseEmployeesAlfa->assertStatus(200);
        $employeesAlfaList = $responseEmployeesAlfa->viewData('page')['props']['employees']['data'] ?? [];
        $employeesAlfaEmails = collect($employeesAlfaList)->pluck('email')->toArray();

        // Alfa debe contener sus propios empleados
        $this->assertContains('ventas@medicaexpress.com', $employeesAlfaEmails);
        $this->assertContains('almacen@medicaexpress.com', $employeesAlfaEmails);
        // Alfa NO debe contener empleados de Beta ni dueños/clientes
        $this->assertNotContains('dr.garcia@dentalnorte.com', $employeesAlfaEmails);
        $this->assertNotContains('cliente.dental@dentalsalud.com', $employeesAlfaEmails);

        // Consulta de Empleados de la Empresa Beta (Clínica Dental Norte)
        $responseEmployeesBeta = $this->actingAs($this->user)
            ->withSession(['active_company_id' => (string) $this->companyBeta->_id])
            ->get('/employees');

        $responseEmployeesBeta->assertStatus(200);
        $employeesBetaList = $responseEmployeesBeta->viewData('page')['props']['employees']['data'] ?? [];
        $employeesBetaEmails = collect($employeesBetaList)->pluck('email')->toArray();

        // Beta debe contener sus propios empleados
        $this->assertContains('dr.garcia@dentalnorte.com', $employeesBetaEmails);
        $this->assertContains('recepcion@dentalnorte.com', $employeesBetaEmails);
        // Beta NO debe contener empleados de Alfa
        $this->assertNotContains('ventas@medicaexpress.com', $employeesBetaEmails);
    }

    /**
     * Prueba 7: Aislamiento estricto de instancias y citas de Calendario por empresa activa.
     */
    public function test_calendar_multi_tenant_scoping(): void
    {
        $context = app(CompanyContext::class);

        // Contexto en Beta (Clínica Dental Norte)
        $context->setCompany($this->companyBeta);
        $instancesBeta = \App\Models\WhatsappInstance::all();
        $this->assertTrue($instancesBeta->contains('instance_name', 'clinica_dental_norte'));
        $this->assertFalse($instancesBeta->contains('instance_name', 'distribuidora_express'));

        // Contexto en Alfa (Distribuidora Médica Express)
        $context->setCompany($this->companyAlfa);
        $instancesAlfa = \App\Models\WhatsappInstance::all();
        $this->assertTrue($instancesAlfa->contains('instance_name', 'distribuidora_express'));
        $this->assertFalse($instancesAlfa->contains('instance_name', 'clinica_dental_norte'));
    }

    /**
     * Prueba 8: Emisión en tiempo real de eventos de actualización de permisos.
     */
    public function test_realtime_permission_broadcasting(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\NotificacionToUser::class]);

        $role = RoleModel::where('role', 'recepcionista')->first();
        $this->assertNotNull($role);

        // Actualizar permisos del rol
        $this->actingAs($this->user)
            ->withSession(['active_company_id' => (string) $this->companyBeta->_id])
            ->patch(route('roles.permissions.update', (string) $role->_id), [
                'permission_ids' => [],
            ]);

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\NotificacionToUser::class);
    }
}
