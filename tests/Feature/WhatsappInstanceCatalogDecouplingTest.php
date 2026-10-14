<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\WhatsappInstance;
use App\Services\Tenancy\CompanyContext;
use App\Services\Whatsapp\AppointmentOrchestrator;
use Tests\TestCase;

class WhatsappInstanceCatalogDecouplingTest extends TestCase
{
    protected Company $company;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SystemMasterSeeder::class);

        $this->company = Company::where('slug', 'distribuidora-medica-express')->first();

        // Crear una sucursal para la empresa
        $this->branch = Branch::create([
            'company_id' => (string) $this->company->_id,
            'name' => 'Sucursal Norte Médica',
            'address' => 'Av. Tecnológico 123, Mérida',
            'phone' => '+529991234567',
            'business_hours' => 'Lunes a Viernes 8:00 a 19:00',
            'is_main' => true,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Branch::where('company_id', (string) $this->company->_id)->delete();
        WhatsappInstance::where('instance_name', 'test_instancia_modular')->delete();
        Product::where('company_id', (string) $this->company->_id)->where('sku', 'TEST-MED-1')->delete();
        Service::where('company_id', (string) $this->company->_id)->where('name', 'Consulta Especializada')->delete();
        app(CompanyContext::class)->clear();

        parent::tearDown();
    }

    public function test_whatsapp_instance_is_linked_to_branch_and_isolated_by_company(): void
    {
        $instance = WhatsappInstance::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'instance_name' => 'test_instancia_modular',
            'google_calendar_id' => 'test-calendar@gmail.com',
            'status' => 'active',
        ]);

        $this->assertEquals((string) $this->branch->_id, (string) $instance->branch_id);
        $this->assertEquals('Sucursal Norte Médica', $instance->branch?->name);
        $this->assertEquals((string) $this->company->_id, (string) $instance->company_id);
    }

    public function test_orchestrator_builds_catalog_directly_from_company_and_branch_data(): void
    {
        // 1. Activar módulos de la empresa
        $this->company->enableModule('services');
        $this->company->enableModule('inventory');

        // 2. Crear servicio y producto oficiales en la BD de la empresa
        Service::create([
            'company_id' => (string) $this->company->_id,
            'name' => 'Consulta Especializada',
            'price' => 500,
            'duration_minutes' => 45,
            'description' => 'Diagnóstico inicial completo',
            'is_active' => true,
        ]);

        Product::create([
            'company_id' => (string) $this->company->_id,
            'sku' => 'TEST-MED-1',
            'name' => 'Tensiómetro Digital',
            'price' => 750,
            'stock' => 12,
            'is_active' => true,
        ]);

        // 3. Crear instancia sin ningún texto de catálogo manual
        $instance = WhatsappInstance::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'instance_name' => 'test_instancia_modular',
            'google_calendar_id' => 'test-calendar@gmail.com',
            'status' => 'active',
        ]);

        /** @var AppointmentOrchestrator $orchestrator */
        $orchestrator = app(AppointmentOrchestrator::class);

        // Invocar el método protegido formatBusinessCatalog mediante reflection
        $reflection = new \ReflectionClass($orchestrator);
        $method = $reflection->getMethod('formatBusinessCatalog');
        $method->setAccessible(true);

        $catalogOutput = $method->invoke($orchestrator, $instance);

        // Verificaciones:
        // A. La ubicación proviene de la sucursal asignada
        $this->assertStringContainsString('Sucursal Sucursal Norte Médica', $catalogOutput);
        $this->assertStringContainsString('Av. Tecnológico 123, Mérida', $catalogOutput);

        // B. Los horarios provienen de la sucursal
        $this->assertStringContainsString('Lunes a Viernes 8:00 a 19:00', $catalogOutput);

        // C. Los servicios provienen de la colección de Servicios de la empresa
        $this->assertStringContainsString('Consulta Especializada', $catalogOutput);
        $this->assertStringContainsString('$500', $catalogOutput);

        // D. Los productos provienen del Inventario oficial de la empresa
        $this->assertStringContainsString('Tensiómetro Digital', $catalogOutput);
        $this->assertStringContainsString('Stock: 12', $catalogOutput);
    }
}
