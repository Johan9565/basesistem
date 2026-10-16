<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\AppointmentOrchestrator;
use Tests\TestCase;

class WhatsappTenChatsOrdersNoOverlapTest extends TestCase
{
    protected Company $company;
    protected Branch $branch;
    protected WhatsappInstance $instance;
    protected Product $cemento;
    protected Product $cal;
    protected Product $varilla;
    protected AppointmentOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Construrama 10 Chats ' . uniqid(),
            'slug' => 'construrama-' . uniqid(),
            'status' => 'active',
            'modules' => [
                'inventory' => true,
                'orders' => true,
                'whatsapp' => true,
            ],
        ]);

        $this->branch = Branch::create([
            'company_id' => (string) $this->company->_id,
            'name' => 'Sucursal Central',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->instance = WhatsappInstance::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'instance_name' => 'bot_10chats_' . uniqid(),
            'evolution_instance_name' => 'evo_10chats_' . uniqid(),
            'business_name' => 'Construrama Central',
            'status' => 'active',
        ]);

        // Catálogo de prueba con stocks controlados
        $this->cemento = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Cemento Gris 50kg',
            'sku' => 'CEM-50',
            'price' => 240.00,
            'stock' => 15,
        ]);

        $this->cal = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Cal Hidratada 25kg',
            'sku' => 'CAL-25',
            'price' => 95.00,
            'stock' => 20,
        ]);

        $this->varilla = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Varilla Corrugada 3/8',
            'sku' => 'VAR-38',
            'price' => 180.00,
            'stock' => 10,
        ]);

        $this->orchestrator = app(AppointmentOrchestrator::class);
    }

    public function test_ten_distinct_chats_create_orders_without_overlapping_or_colliding(): void
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $executeToolMethod = $reflection->getMethod('executeToolCall');
        $executeToolMethod->setAccessible(true);

        $chatConfigs = [
            [
                'phone' => '5219981000001',
                'customer' => 'Cliente Chat 1 - Carlos',
                'items' => [['product_id' => (string) $this->cemento->_id, 'quantity' => 2]],
                'expect_success' => true,
            ],
            [
                'phone' => '5219981000002',
                'customer' => 'Cliente Chat 2 - Laura',
                'items' => [['product_id' => (string) $this->cal->_id, 'quantity' => 3]],
                'expect_success' => true,
            ],
            [
                'phone' => '5219981000003',
                'customer' => 'Cliente Chat 3 - Roberto',
                'items' => [['product_id' => (string) $this->varilla->_id, 'quantity' => 1]],
                'expect_success' => true,
            ],
            [
                'phone' => '5219981000004',
                'customer' => 'Cliente Chat 4 - Maria',
                'items' => [['product_id' => (string) $this->cemento->_id, 'quantity' => 4]],
                'expect_success' => true,
            ],
            [
                'phone' => '5219981000005',
                'customer' => 'Cliente Chat 5 - Juan',
                'items' => [['product_id' => (string) $this->cal->_id, 'quantity' => 5]],
                'expect_success' => true,
            ],
            [
                'phone' => '5219981000006',
                'customer' => 'Cliente Chat 6 - Pedro',
                'items' => [['product_id' => (string) $this->varilla->_id, 'quantity' => 2]],
                'expect_success' => true,
            ],
            [
                'phone' => '5219981000007',
                'customer' => 'Cliente Chat 7 - Sofia',
                'items' => [
                    ['product_id' => (string) $this->cemento->_id, 'quantity' => 3],
                    ['product_id' => (string) $this->cal->_id, 'quantity' => 2],
                ],
                'expect_success' => true,
            ],
            [
                // Chat 8: Intenta pedir más cemento del disponible (quedan 15 - 2 - 4 - 3 = 6, pide 10)
                'phone' => '5219981000008',
                'customer' => 'Cliente Chat 8 - Diego (Exceso de stock)',
                'items' => [['product_id' => (string) $this->cemento->_id, 'quantity' => 10]],
                'expect_success' => false,
            ],
            [
                // Chat 9: Compra exactamente las 6 unidades restantes de Cemento
                'phone' => '5219981000009',
                'customer' => 'Cliente Chat 9 - Elena (Agotar cemento)',
                'items' => [['product_id' => (string) $this->cemento->_id, 'quantity' => 6]],
                'expect_success' => true,
            ],
            [
                // Chat 10: Intenta comprar 1 unidad de Cemento ahora que está en 0
                'phone' => '5219981000010',
                'customer' => 'Cliente Chat 10 - Andres (Stock en cero)',
                'items' => [['product_id' => (string) $this->cemento->_id, 'quantity' => 1]],
                'expect_success' => false,
            ],
        ];

        $successfulOrderNumbers = [];
        $successfulCustomerPhones = [];

        foreach ($chatConfigs as $index => $chat) {
            $toolCall = [
                'id' => 'call_chat_' . ($index + 1),
                'function' => [
                    'name' => 'create_order',
                    'arguments' => json_encode([
                        'customer_name' => $chat['customer'],
                        'delivery_address' => 'Calle 100 # ' . ($index + 1),
                        'client_confirmed' => true,
                        'items' => $chat['items'],
                    ]),
                ],
            ];

            $result = $executeToolMethod->invoke(
                $this->orchestrator,
                $toolCall,
                $this->instance,
                $chat['phone']
            );

            if ($chat['expect_success']) {
                $this->assertTrue($result['ok'], "Chat {$index} ({$chat['customer']}) debía procesarse exitosamente: " . ($result['error'] ?? ''));
                $this->assertNotEmpty($result['order_number'], "El pedido debe contener order_number");
                $this->assertNotContains(
                    $result['order_number'],
                    $successfulOrderNumbers,
                    "¡COLISIÓN DETECTADA! El número de pedido {$result['order_number']} ya fue asignado a otro chat."
                );

                $successfulOrderNumbers[] = $result['order_number'];
                $successfulCustomerPhones[$result['order_number']] = $chat['phone'];

                // Verificar en base de datos
                $orderDoc = Order::where('company_id', (string) $this->company->_id)
                    ->where('order_number', $result['order_number'])
                    ->first();

                $this->assertNotNull($orderDoc);
                $this->assertEquals($chat['phone'], $orderDoc->customer_phone);
                $this->assertEquals($chat['customer'], $orderDoc->customer_name);
                $this->assertTrue($orderDoc->stock_deducted);
            } else {
                $this->assertFalse($result['ok'], "Chat {$index} ({$chat['customer']}) debía fallar por falta de existencias.");
                $this->assertStringContainsString('Stock insuficiente', $result['error']);
            }
        }

        // 8 chats exitosos, 2 rechazados por falta de stock
        $this->assertCount(8, $successfulOrderNumbers);
        $this->assertEquals(8, count(array_unique($successfulOrderNumbers)));

        // Verificar inventario final de Cemento: arrancó en 15, compras: 2 + 4 + 3 + 6 = 15 => debe quedar en 0
        $this->cemento->refresh();
        $this->assertEquals(0, $this->cemento->stock, 'El stock final de Cemento debe ser 0');

        // Verificar inventario final de Cal: arrancó en 20, compras: 3 + 5 + 2 = 10 => debe quedar en 10
        $this->cal->refresh();
        $this->assertEquals(10, $this->cal->stock, 'El stock final de Cal debe ser 10');

        // Verificar inventario final de Varilla: arrancó en 10, compras: 1 + 2 = 3 => debe quedar en 7
        $this->varilla->refresh();
        $this->assertEquals(7, $this->varilla->stock, 'El stock final de Varilla debe ser 7');

        // Verificar que cada chat puede consultar sus propias órdenes sin ver las de los otros
        foreach ($successfulCustomerPhones as $orderNumber => $phone) {
            $queryToolCall = [
                'id' => 'query_' . $orderNumber,
                'function' => [
                    'name' => 'list_my_orders',
                    'arguments' => json_encode([]),
                ],
            ];

            $queryResult = $executeToolMethod->invoke(
                $this->orchestrator,
                $queryToolCall,
                $this->instance,
                $phone
            );

            $this->assertTrue($queryResult['ok']);
            $orderNumsInResult = array_column($queryResult['orders'], 'order_number');
            $this->assertContains($orderNumber, $orderNumsInResult, "El chat {$phone} debe ver su propia orden {$orderNumber}");

            // Verificar que no ve las de los otros clientes
            foreach ($successfulCustomerPhones as $otherOrderNumber => $otherPhone) {
                if ($otherPhone !== $phone) {
                    $this->assertNotContains(
                        $otherOrderNumber,
                        $orderNumsInResult,
                        "El chat {$phone} NO debe ver la orden {$otherOrderNumber} de otro cliente."
                    );
                }
            }
        }
    }

    public function test_prompt_enforces_conciseness_and_no_rambling(): void
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('buildStaticSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->orchestrator, $this->instance, 'America/Mexico_City');

        // Verificar directivas explícitas de brevedad
        $this->assertStringContainsString('Máximo 2–3 oraciones por respuesta', $prompt);
        $this->assertStringContainsString('sé conciso y no te explayes innecesariamente', $prompt);
        $this->assertStringContainsString('sin rodeos', $prompt);
        $this->assertStringContainsString('Sé conciso y directo: responde en 2 a 3 oraciones', $prompt);
    }
}
