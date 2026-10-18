<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;
use App\Models\RoleModel;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\PermissionCacheService;
use App\Services\Whatsapp\AppointmentOrchestrator;
use App\Services\Whatsapp\EvolutionApiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProductsAndServicesImagesAndBotTest extends TestCase
{
    protected User $adminUser;
    protected Company $company;
    protected Branch $branch;
    protected RoleModel $adminRole;
    protected WhatsappInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        app(PermissionCacheService::class)->invalidateAll();

        $this->adminRole = RoleModel::firstOrCreate(
            ['role' => 'admin_img_test'],
            [
                'name' => 'Admin Image Test',
                'status' => 1,
                'permissions' => [
                    'products',
                    'products.view',
                    'products.create',
                    'products.edit',
                    'products.delete',
                    'services',
                    'services.view',
                    'services.create',
                    'services.edit',
                    'services.delete',
                    'whatsapp.chat_test',
                ],
            ]
        );

        $this->adminUser = User::create([
            'name' => 'Admin Image User',
            'email' => 'admin_img_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'role_id' => (string) $this->adminRole->_id,
            'status' => 1,
        ]);

        $this->company = Company::create([
            'name' => 'Empresa Test Imagenes',
            'slug' => 'empresa-test-img-'.uniqid(),
            'user_id' => (string) $this->adminUser->_id,
            'status' => 'active',
            'modules' => [
                'inventory' => true,
                'services' => true,
                'whatsapp' => true,
                'appointments' => true,
            ],
        ]);

        CompanyUser::create([
            'user_id' => (string) $this->adminUser->_id,
            'company_id' => (string) $this->company->_id,
            'role_id' => (string) $this->adminRole->_id,
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'company_id' => (string) $this->company->_id,
            'name' => 'Sucursal Principal Test',
            'code' => 'MAT-IMG',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->instance = WhatsappInstance::create([
            'company_id' => (string) $this->company->_id,
            'instance_name' => 'bot_img_test_'.uniqid(),
            'business_name' => 'Empresa Test Imagenes',
            'status' => 'active',
            'ai_provider' => 'deepseek',
        ]);

        app(CompanyContext::class)->setCompanyId((string) $this->company->_id);
    }

    public function test_can_create_product_with_image_upload(): void
    {
        $this->actingAs($this->adminUser);

        $file = UploadedFile::fake()->image('tenis-nike.jpg', 600, 600);

        $response = $this->post(route('products.store'), [
            'branch_id' => (string) $this->branch->_id,
            'sku' => 'NIKE-01',
            'name' => 'Tenis Nike Air',
            'description' => 'Tenis deportivos con suela de aire',
            'category' => 'Calzado',
            'price' => 1899.99,
            'cost' => 1200.00,
            'stock' => 15,
            'min_stock' => 2,
            'is_active' => true,
            'image' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $product = Product::where('sku', 'NIKE-01')->first();
        $this->assertNotNull($product);
        $this->assertNotEmpty($product->image_path);
        $this->assertNotEmpty($product->image_url);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_can_update_product_and_replace_or_remove_image(): void
    {
        $this->actingAs($this->adminUser);

        $oldFile = UploadedFile::fake()->image('old-product.jpg', 500, 500);
        $oldPath = $oldFile->store('products', 'public');

        $product = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'sku' => 'PROD-UPDT',
            'name' => 'Producto A Actualizar',
            'price' => 250,
            'stock' => 10,
            'image_path' => $oldPath,
            'is_active' => true,
        ]);

        Storage::disk('public')->assertExists($oldPath);

        // Actualizar subiendo una nueva imagen
        $newFile = UploadedFile::fake()->image('new-product.png', 500, 500);
        $response = $this->post(route('products.update', (string) $product->_id), [
            '_method' => 'patch',
            'branch_id' => (string) $this->branch->_id,
            'sku' => 'PROD-UPDT',
            'name' => 'Producto Actualizado',
            'price' => 300,
            'stock' => 8,
            'is_active' => true,
            'image' => $newFile,
        ]);

        $response->assertSessionHasNoErrors();
        $product->refresh();

        // La imagen antigua debe haber sido eliminada del disco
        Storage::disk('public')->assertMissing($oldPath);
        // La nueva imagen debe existir
        $this->assertNotEquals($oldPath, $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);

        // Ahora remover la imagen
        $currentPath = $product->image_path;
        $removeResponse = $this->patch(route('products.update', (string) $product->_id), [
            'branch_id' => (string) $this->branch->_id,
            'sku' => 'PROD-UPDT',
            'name' => 'Producto Sin Imagen',
            'price' => 300,
            'stock' => 8,
            'is_active' => true,
            'remove_image' => true,
        ]);

        $removeResponse->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertNull($product->image_path);
        Storage::disk('public')->assertMissing($currentPath);
    }

    public function test_can_create_service_with_image_upload(): void
    {
        $this->actingAs($this->adminUser);

        $file = UploadedFile::fake()->image('masaje.jpg', 600, 600);

        $response = $this->post(route('services.store'), [
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Masaje Terapéutico',
            'description' => 'Sesión de relajación muscular',
            'duration_minutes' => 60,
            'price' => 650.00,
            'is_active' => true,
            'image' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $service = Service::where('name', 'Masaje Terapéutico')->first();
        $this->assertNotNull($service);
        $this->assertNotEmpty($service->image_path);
        $this->assertNotEmpty($service->image_url);
        Storage::disk('public')->assertExists($service->image_path);
    }

    public function test_bot_tool_query_inventory_products_includes_images(): void
    {
        $file = UploadedFile::fake()->image('audifonos.jpg', 400, 400);
        $path = $file->store('products', 'public');

        $product = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'sku' => 'AUD-001',
            'name' => 'Audífonos Bluetooth',
            'price' => 499.00,
            'stock' => 20,
            'image_path' => $path,
            'is_active' => true,
        ]);

        /** @var AppointmentOrchestrator $orchestrator */
        $orchestrator = app(AppointmentOrchestrator::class);
        $ref = new \ReflectionClass($orchestrator);
        $method = $ref->getMethod('toolQueryInventoryProducts');
        $method->setAccessible(true);

        $result = $method->invoke($orchestrator, $this->instance, ['search' => 'Audífonos']);

        $this->assertTrue($result['ok']);
        $this->assertGreaterThanOrEqual(1, $result['count']);
        $this->assertTrue($result['products'][0]['has_image']);
        $this->assertNotEmpty($result['products'][0]['image_url']);
    }

    public function test_bot_tool_send_catalog_image_sends_product_image_and_records_message(): void
    {
        $file = UploadedFile::fake()->image('reloj.jpg', 500, 500);
        $path = $file->store('products', 'public');

        $product = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'sku' => 'REL-09',
            'name' => 'Reloj Inteligente Fit',
            'price' => 1299.00,
            'stock' => 10,
            'image_path' => $path,
            'is_active' => true,
        ]);

        /** @var AppointmentOrchestrator $orchestrator */
        $orchestrator = app(AppointmentOrchestrator::class);
        $ref = new \ReflectionClass($orchestrator);
        $method = $ref->getMethod('toolSendCatalogImage');
        $method->setAccessible(true);

        $phone = '5215588889999';
        $result = $method->invoke($orchestrator, $this->instance, $phone, [
            'name_or_sku' => 'Reloj Inteligente',
            'caption' => 'Aquí tienes la foto del reloj inteligente',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['image_sent']);
        $this->assertEquals('Reloj Inteligente Fit', $result['item_name']);
        $this->assertNotEmpty($result['image_url']);

        // Debe haberse guardado el mensaje en el historial con la URL de la imagen
        $msg = WhatsappMessage::withoutGlobalScopes()
            ->where('instance_name', $this->instance->evolutionName())
            ->where('user_phone', $phone)
            ->where('role', 'assistant')
            ->first();

        $this->assertNotNull($msg);
        $this->assertEquals('Aquí tienes la foto del reloj inteligente', $msg->content);
        $this->assertEquals($result['image_url'], $msg->metadata['image_url'] ?? null);
    }

    public function test_bot_tool_send_catalog_image_for_service_with_image(): void
    {
        $file = UploadedFile::fake()->image('corte.jpg', 500, 500);
        $path = $file->store('services', 'public');

        $service = Service::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Corte de Cabello Estilo',
            'price' => 200,
            'duration_minutes' => 30,
            'image_path' => $path,
            'is_active' => true,
        ]);

        /** @var AppointmentOrchestrator $orchestrator */
        $orchestrator = app(AppointmentOrchestrator::class);
        $ref = new \ReflectionClass($orchestrator);
        $method = $ref->getMethod('toolSendCatalogImage');
        $method->setAccessible(true);

        $phone = '5215544443333';
        $result = $method->invoke($orchestrator, $this->instance, $phone, [
            'name_or_sku' => 'Corte de Cabello',
            'type' => 'service',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['image_sent']);
        $this->assertEquals('Corte de Cabello Estilo', $result['item_name']);
        $this->assertNotEmpty($result['image_url']);
    }

    public function test_bot_tool_send_catalog_image_informs_when_item_has_no_image(): void
    {
        $product = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'sku' => 'SIN-FOTO',
            'name' => 'Tornillos Industriales',
            'price' => 50,
            'stock' => 100,
            'is_active' => true,
        ]);

        /** @var AppointmentOrchestrator $orchestrator */
        $orchestrator = app(AppointmentOrchestrator::class);
        $ref = new \ReflectionClass($orchestrator);
        $method = $ref->getMethod('toolSendCatalogImage');
        $method->setAccessible(true);

        $phone = '5215511112222';
        $result = $method->invoke($orchestrator, $this->instance, $phone, [
            'name_or_sku' => 'Tornillos Industriales',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['image_sent']);
        $this->assertStringContainsString('no tiene una fotografía', $result['message']);
    }
}
