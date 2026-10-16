<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\WhatsappInstance;
use App\Services\Whatsapp\AppointmentOrchestrator;
use Tests\TestCase;

class WhatsappFiftyChatsOrdersStressTest extends TestCase
{
    protected Company $company;
    protected Branch $branch;
    protected WhatsappInstance $instance;
    /** @var array<string, Product> */
    protected array $products = [];
    protected AppointmentOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Mega Distribuidora 50 Chats ' . uniqid(),
            'slug' => 'mega-distribuidora-' . uniqid(),
            'status' => 'active',
            'modules' => [
                'inventory' => true,
                'orders' => true,
                'whatsapp' => true,
            ],
        ]);

        $this->branch = Branch::create([
            'company_id' => (string) $this->company->_id,
            'name' => 'Sucursal Principal 50',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->instance = WhatsappInstance::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'instance_name' => 'bot_50chats_' . uniqid(),
            'evolution_instance_name' => 'evo_50chats_' . uniqid(),
            'business_name' => 'Mega Distribuidora',
            'status' => 'active',
        ]);

        // Catálogo amplio con existencias definidas
        $catalog = [
            'cemento' => ['name' => 'Cemento CPC 30R 50kg', 'sku' => 'CEM-50', 'price' => 245.0, 'stock' => 80],
            'cal' => ['name' => 'Cal Quimica 25kg', 'sku' => 'CAL-25', 'price' => 90.0, 'stock' => 100],
            'varilla' => ['name' => 'Varilla 1/2 pulgada', 'sku' => 'VAR-12', 'price' => 220.0, 'stock' => 50],
            'block' => ['name' => 'Block Ligero 15x20x40', 'sku' => 'BLK-15', 'price' => 16.5, 'stock' => 500],
            'arena' => ['name' => 'Metro Arena Triturada', 'sku' => 'ARN-M3', 'price' => 450.0, 'stock' => 30],
        ];

        foreach ($catalog as $key => $data) {
            $this->products[$key] = Product::create([
                'company_id' => (string) $this->company->_id,
                'branch_id' => (string) $this->branch->_id,
                'name' => $data['name'],
                'sku' => $data['sku'],
                'price' => $data['price'],
                'stock' => $data['stock'],
            ]);
        }

        $this->orchestrator = app(AppointmentOrchestrator::class);
    }

    public function test_fifty_distinct_chats_concurrent_orders_and_isolation(): void
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $executeToolMethod = $reflection->getMethod('executeToolCall');
        $executeToolMethod->setAccessible(true);

        $productKeys = array_keys($this->products);
        $initialStocks = [];
        foreach ($this->products as $key => $p) {
            $initialStocks[$key] = $p->stock;
        }

        $expectedDeductions = array_fill_keys($productKeys, 0);
        $ordersCreated = [];
        $ordersByPhone = [];

        // Generar 50 solicitudes de clientes simulados
        for ($i = 1; $i <= 50; $i++) {
            $phone = sprintf('5219982%06d', $i);
            $customerName = "Cliente #{$i} - Persona " . chr(65 + ($i % 26)) . $i;

            // Variar las solicitudes
            // Chats 1-35: pedidos normales válidos (1 o 2 productos, cantidades moderadas)
            // Chats 36-40: pedidos con múltiples productos
            // Chats 41-45: intentos con cantidades excesivas por encima de stock restante
            // Chats 46-50: pedidos válidos adicionales de artículos con stock alto (ej. blocks)
            if ($i <= 35) {
                $targetKey = $productKeys[$i % count($productKeys)];
                $qty = ($i % 3) + 1; // 1, 2 o 3 unidades
                $items = [
                    ['product_id' => (string) $this->products[$targetKey]->_id, 'quantity' => $qty]
                ];
            } elseif ($i <= 40) {
                $items = [
                    ['product_id' => (string) $this->products['block']->_id, 'quantity' => 10],
                    ['product_id' => (string) $this->products['cal']->_id, 'quantity' => 2],
                ];
            } elseif ($i <= 45) {
                // Cantidad exagerada para forzar rechazo por stock
                $items = [
                    ['product_id' => (string) $this->products['arena']->_id, 'quantity' => 999],
                ];
            } else {
                $items = [
                    ['product_id' => (string) $this->products['block']->_id, 'quantity' => 5],
                ];
            }

            $toolCall = [
                'id' => "call_50chat_{$i}",
                'function' => [
                    'name' => 'create_order',
                    'arguments' => json_encode([
                        'customer_name' => $customerName,
                        'delivery_address' => "Mz {$i} Lote " . ($i * 2) . ", Cancún",
                        'client_confirmed' => true,
                        'items' => $items,
                    ]),
                ],
            ];

            $result = $executeToolMethod->invoke(
                $this->orchestrator,
                $toolCall,
                $this->instance,
                $phone
            );

            if ($i >= 41 && $i <= 45) {
                // Debe ser rechazado por stock insuficiente
                $this->assertFalse($result['ok'], "Chat {$i} debía ser rechazado por exceder stock.");
                $this->assertStringContainsString('Stock insuficiente', $result['error']);
            } else {
                // Debe ser exitoso
                $this->assertTrue($result['ok'], "Chat {$i} falló inesperadamente: " . ($result['error'] ?? ''));
                $orderNum = $result['order_number'];
                $this->assertNotEmpty($orderNum);

                // Verificar colisiones de números de orden
                $this->assertNotContains(
                    $orderNum,
                    $ordersCreated,
                    "¡COLISIÓN DETECTADA en chat {$i}! El folio {$orderNum} ya existía."
                );

                $ordersCreated[] = $orderNum;
                $ordersByPhone[$phone][] = $orderNum;

                // Sumar deducción esperada
                foreach ($items as $item) {
                    foreach ($this->products as $pKey => $pModel) {
                        if ((string) $pModel->_id === (string) $item['product_id']) {
                            $expectedDeductions[$pKey] += (int) $item['quantity'];
                        }
                    }
                }
            }
        }

        // 45 pedidos exitosos (35 normales + 5 múltiples + 5 adicionales de block)
        $this->assertCount(45, $ordersCreated);
        $this->assertCount(45, array_unique($ordersCreated));

        // Verificar el stock final exacto de cada producto en inventario
        foreach ($this->products as $key => $product) {
            $product->refresh();
            $expectedStock = $initialStocks[$key] - $expectedDeductions[$key];
            $this->assertEquals(
                $expectedStock,
                $product->stock,
                "El stock final de {$product->name} ({$key}) no coincide. Esperado: {$expectedStock}, Real: {$product->stock}"
            );
        }

        // Verificar aislamiento y consulta con list_my_orders en una muestra representativa de los chats
        $sampledPhones = array_slice(array_keys($ordersByPhone), 0, 15);
        foreach ($sampledPhones as $phone) {
            $queryCall = [
                'id' => "query_{$phone}",
                'function' => [
                    'name' => 'list_my_orders',
                    'arguments' => json_encode([]),
                ],
            ];

            $queryResult = $executeToolMethod->invoke(
                $this->orchestrator,
                $queryCall,
                $this->instance,
                $phone
            );

            $this->assertTrue($queryResult['ok']);
            $returnedOrderNums = array_column($queryResult['orders'], 'order_number');
            $expectedForThisPhone = $ordersByPhone[$phone];

            foreach ($expectedForThisPhone as $expectedNum) {
                $this->assertContains($expectedNum, $returnedOrderNums);
            }

            // Asegurar que no ve pedidos de otros teléfonos
            foreach ($ordersCreated as $anyOrderNum) {
                if (! in_array($anyOrderNum, $expectedForThisPhone, true)) {
                    $this->assertNotContains(
                        $anyOrderNum,
                        $returnedOrderNums,
                        "Fuga de datos: El teléfono {$phone} puede ver el pedido ajeno {$anyOrderNum}"
                    );
                }
            }
        }
    }
}
