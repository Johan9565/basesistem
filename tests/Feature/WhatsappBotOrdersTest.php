<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\WhatsappInstance;
use App\Services\Whatsapp\AppointmentOrchestrator;
use App\Services\Whatsapp\DeepSeekClient;
use Tests\TestCase;

class WhatsappBotOrdersTest extends TestCase
{
    protected Company $company;
    protected Branch $branch;
    protected WhatsappInstance $instance;
    protected Product $product;
    protected AppointmentOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Ferretería y Materiales ' . uniqid(),
            'slug' => 'ferreteria-' . uniqid(),
            'status' => 'active',
            'modules' => [
                'inventory' => true,
                'orders' => true,
                'whatsapp' => true,
            ],
        ]);

        $this->branch = Branch::create([
            'company_id' => (string) $this->company->_id,
            'name' => 'Sucursal Norte',
            'code' => 'SUC-NTE',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->instance = WhatsappInstance::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'instance_name' => 'bot_pedidos_' . uniqid(),
            'evolution_instance_name' => 'evo_pedidos_' . uniqid(),
            'business_name' => 'Materiales El Constructor',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Cemento Tolteca 50kg',
            'sku' => 'CEM-TOL-50',
            'price' => 250.00,
            'stock' => 20,
            'is_active' => true,
        ]);

        $this->orchestrator = app(AppointmentOrchestrator::class);
    }

    public function test_bot_tools_include_orders_tools_when_orders_module_is_enabled(): void
    {
        $deepSeek = new DeepSeekClient();
        $tools = $deepSeek->resolveToolsForCompany($this->company);

        $toolNames = array_map(fn ($t) => $t['function']['name'] ?? '', $tools);

        $this->assertContains('create_order', $toolNames);
        $this->assertContains('list_my_orders', $toolNames);
        $this->assertContains('query_inventory_products', $toolNames);
    }

    public function test_bot_tools_exclude_orders_when_orders_module_is_disabled(): void
    {
        $this->company->disableModule('orders');

        $deepSeek = new DeepSeekClient();
        $tools = $deepSeek->resolveToolsForCompany($this->company);

        $toolNames = array_map(fn ($t) => $t['function']['name'] ?? '', $tools);

        $this->assertNotContains('create_order', $toolNames);
        $this->assertNotContains('list_my_orders', $toolNames);
    }

    public function test_bot_creates_order_and_deducts_inventory_stock(): void
    {
        $phone = '5219981234567';

        $toolCall = [
            'id' => 'call_ord_1',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Mario Constructor',
                    'client_confirmed' => true,
                    'delivery_address' => 'Av. Kabah con Niños Héroes, Cancún',
                    'items' => [
                        [
                            'name' => 'Cemento Tolteca 50kg',
                            'quantity' => 5,
                        ],
                    ],
                ]),
            ],
        ];

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('executeToolCall');
        $method->setAccessible(true);

        $result = $method->invoke($this->orchestrator, $toolCall, $this->instance, $phone);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['order_number']);
        $this->assertEquals(1250.00, $result['total']);

        // Verificar que el producto se descontó en inventario de 20 a 15
        $this->product->refresh();
        $this->assertEquals(15, $this->product->stock);

        // Verificar que la orden existe en MongoDB
        $order = Order::where('company_id', (string) $this->company->_id)
            ->where('order_number', $result['order_number'])
            ->first();
        $this->assertNotNull($order);
        $this->assertEquals('Mario Constructor', $order->customer_name);
        $this->assertEquals($phone, $order->customer_phone);
        $this->assertTrue($order->stock_deducted);
        $this->assertStringContainsString('Av. Kabah', $order->notes);
    }

    public function test_bot_rejects_order_if_client_not_confirmed(): void
    {
        $phone = '5219981234567';

        $toolCall = [
            'id' => 'call_ord_2',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Mario Constructor',
                    'client_confirmed' => false, // No confirmado aún
                    'items' => [
                        [
                            'name' => 'Cemento Tolteca 50kg',
                            'quantity' => 2,
                        ],
                    ],
                ]),
            ],
        ];

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('executeToolCall');
        $method->setAccessible(true);

        $result = $method->invoke($this->orchestrator, $toolCall, $this->instance, $phone);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('no ha confirmado', $result['error']);

        // El stock permanece intacto
        $this->product->refresh();
        $this->assertEquals(20, $this->product->stock);
    }

    public function test_bot_rejects_order_if_insufficient_stock(): void
    {
        $phone = '5219981234567';

        $toolCall = [
            'id' => 'call_ord_3',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Mario Constructor',
                    'client_confirmed' => true,
                    'items' => [
                        [
                            'name' => 'Cemento Tolteca 50kg',
                            'quantity' => 50, // Solo hay 20 disponibles
                        ],
                    ],
                ]),
            ],
        ];

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('executeToolCall');
        $method->setAccessible(true);

        $result = $method->invoke($this->orchestrator, $toolCall, $this->instance, $phone);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Stock insuficiente', $result['error']);

        // El stock sigue siendo 20
        $this->product->refresh();
        $this->assertEquals(20, $this->product->stock);
    }

    public function test_bot_list_my_orders_returns_orders_for_phone(): void
    {
        $phone = '5219989998877';

        // Crear una orden previa para este teléfono
        Order::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'order_number' => 'PED-99999',
            'customer_name' => 'Ana Compradora',
            'customer_phone' => $phone,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'items' => [
                [
                    'name' => 'Cemento Tolteca 50kg',
                    'quantity' => 1,
                    'unit_price' => 250.00,
                    'subtotal' => 250.00,
                ],
            ],
            'total' => 250.00,
        ]);

        $toolCall = [
            'id' => 'call_ord_4',
            'function' => [
                'name' => 'list_my_orders',
                'arguments' => json_encode([]),
            ],
        ];

        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('executeToolCall');
        $method->setAccessible(true);

        $result = $method->invoke($this->orchestrator, $toolCall, $this->instance, $phone);

        $this->assertTrue($result['ok']);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals('PED-99999', $result['orders'][0]['order_number']);
    }

    public function test_orchestrator_builds_static_prompt_without_undefined_variable_errors(): void
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('buildStaticSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->orchestrator, $this->instance, 'America/Mexico_City');

        $this->assertIsString($prompt);
        $this->assertStringContainsString('Ventas y Generación de Pedidos', $prompt);
        $this->assertStringContainsString('create_order', $prompt);
    }
}
