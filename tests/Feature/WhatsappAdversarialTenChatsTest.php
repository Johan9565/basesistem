<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\WhatsappInstance;
use App\Services\Whatsapp\AppointmentOrchestrator;
use Tests\TestCase;

class WhatsappAdversarialTenChatsTest extends TestCase
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
            'name' => 'Ferretera El Reto ' . uniqid(),
            'slug' => 'ferretera-reto-' . uniqid(),
            'status' => 'active',
            'modules' => [
                'inventory' => true,
                'orders' => true,
                'whatsapp' => true,
            ],
        ]);

        $this->branch = Branch::create([
            'company_id' => (string) $this->company->_id,
            'name' => 'Matriz Reto',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->instance = WhatsappInstance::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'instance_name' => 'bot_adversarial_' . uniqid(),
            'evolution_instance_name' => 'evo_adversarial_' . uniqid(),
            'business_name' => 'Ferretera El Reto',
            'status' => 'active',
        ]);

        $this->cemento = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Cemento Tolteca Gris 50kg',
            'sku' => 'CEM-TOL',
            'price' => 240.00,
            'stock' => 10,
        ]);

        $this->cal = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Cal Hidratada Extra 25kg',
            'sku' => 'CAL-EXT',
            'price' => 95.00,
            'stock' => 15,
        ]);

        $this->varilla = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Varilla 3/8 Pulgada 12m',
            'sku' => 'VAR-38',
            'price' => 180.00,
            'stock' => 8,
        ]);

        $this->orchestrator = app(AppointmentOrchestrator::class);
    }

    public function test_ten_adversarial_chats_handling_without_breaking(): void
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('executeToolCall');
        $method->setAccessible(true);

        // 1. CHAT 1: Intento de Jailbreak / Precios manipulados a $0
        $chat1Call = [
            'id' => 'call_adv_1',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Hacker DAN',
                    'client_confirmed' => true,
                    'items' => [
                        [
                            'product_name' => 'Cemento Tolteca Gris 50kg',
                            'quantity' => 2,
                            'unit_price' => 0.0, // Intento de precio gratis
                        ],
                    ],
                ]),
            ],
        ];
        $chat1Res = $method->invoke($this->orchestrator, $chat1Call, $this->instance, '5219989000001');
        $this->assertTrue($chat1Res['ok']);
        // El precio unitario debe ser el oficial de catálogo ($240), total $480, no $0
        $this->assertEquals(480.00, $chat1Res['total']);
        $this->assertEquals('PED-00001', $chat1Res['order_number']);

        // 2. CHAT 2: Gaslighting / Falsa confirmación sin artículos
        $chat2Call = [
            'id' => 'call_adv_2',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Cliente Apurado',
                    'client_confirmed' => true,
                    'items' => [], // Sin artículos
                ]),
            ],
        ];
        $chat2Res = $method->invoke($this->orchestrator, $chat2Call, $this->instance, '5219989000002');
        $this->assertFalse($chat2Res['ok']);
        $this->assertStringContainsString('No se indicaron artículos', $chat2Res['error']);

        // 3. CHAT 3: Producto fuera de catálogo / no existente
        $chat3Call = [
            'id' => 'call_adv_3',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Cliente Rieles',
                    'client_confirmed' => true,
                    'items' => [
                        ['product_name' => 'Rieles de tren de acero quirúrgico', 'quantity' => 5],
                    ],
                ]),
            ],
        ];
        $chat3Res = $method->invoke($this->orchestrator, $chat3Call, $this->instance, '5219989000003');
        $this->assertFalse($chat3Res['ok']);
        $this->assertStringContainsString('no fue encontrado', $chat3Res['error']);

        // 4. CHAT 4: Pedido consolidado tras cambios de opinión
        $chat4Call = [
            'id' => 'call_adv_4',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Cliente Indeciso',
                    'delivery_address' => 'Av. Bonampak Sm 4',
                    'client_confirmed' => true,
                    'items' => [
                        ['product_name' => 'cemento tolteca', 'quantity' => 1],
                        ['product_name' => 'cal hidratada', 'quantity' => 2],
                    ],
                ]),
            ],
        ];
        $chat4Res = $method->invoke($this->orchestrator, $chat4Call, $this->instance, '5219989000004');
        $this->assertTrue($chat4Res['ok']);
        // 1 Cemento ($240) + 2 Cal ($95*2 = $190) = $430
        $this->assertEquals(430.00, $chat4Res['total']);
        $this->assertEquals('PED-00002', $chat4Res['order_number']);

        // 5. CHAT 5: Intento de exceder stock disponible
        $chat5Call = [
            'id' => 'call_adv_5',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Constructora Titán',
                    'client_confirmed' => true,
                    'items' => [
                        ['product_name' => 'cemento', 'quantity' => 500], // Quedan 7
                    ],
                ]),
            ],
        ];
        $chat5Res = $method->invoke($this->orchestrator, $chat5Call, $this->instance, '5219989000005');
        $this->assertFalse($chat5Res['ok']);
        $this->assertStringContainsString('Stock insuficiente', $chat5Res['error']);

        // 6. CHAT 6: Reclamo agresivo -> Escalamiento humano
        $chat6Call = [
            'id' => 'call_adv_6',
            'function' => [
                'name' => 'escalate_to_human',
                'arguments' => json_encode([
                    'reason' => 'queja_agresiva_entrega',
                    'detail' => 'Cliente sumamente molesto por retraso en entrega, exige hablar con gerente.',
                ]),
            ],
        ];
        $chat6Res = $method->invoke($this->orchestrator, $chat6Call, $this->instance, '5219989000006');
        $this->assertTrue($chat6Res['ok']);
        $this->assertTrue($chat6Res['paused']);
        $this->assertNotEmpty($chat6Res['suggested_reply']);

        // 7. CHAT 7: Consulta ambigua "lo de siempre" -> list_my_orders
        $chat7Call = [
            'id' => 'call_adv_7',
            'function' => [
                'name' => 'list_my_orders',
                'arguments' => json_encode([]),
            ],
        ];
        $chat7Res = $method->invoke($this->orchestrator, $chat7Call, $this->instance, '5219989000001'); // Mismo del Chat 1
        $this->assertTrue($chat7Res['ok']);
        $this->assertEquals(1, $chat7Res['count']);
        $this->assertEquals('PED-00001', $chat7Res['orders'][0]['order_number']);

        // 8. CHAT 8: Intento de regateo forzado (pide precio $50 en vez de $180)
        $chat8Call = [
            'id' => 'call_adv_8',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Regateador Extremo',
                    'client_confirmed' => true,
                    'items' => [
                        [
                            'product_name' => 'varilla 3/8',
                            'quantity' => 3,
                            'unit_price' => 50.00, // Intento de pagar $50
                        ],
                    ],
                ]),
            ],
        ];
        $chat8Res = $method->invoke($this->orchestrator, $chat8Call, $this->instance, '5219989000008');
        $this->assertTrue($chat8Res['ok']);
        // Debe cobrar $180 c/u => 3 * 180 = $540
        $this->assertEquals(540.00, $chat8Res['total']);
        $this->assertEquals('PED-00003', $chat8Res['order_number']);

        // 9. CHAT 9: Typo / lenguaje informal ("kiero 2 sementos")
        $chat9Call = [
            'id' => 'call_adv_9',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Cliente Chat 9 Typo',
                    'client_confirmed' => true,
                    'items' => [
                        ['product_name' => 'Tolteca Gris', 'quantity' => 2],
                    ],
                ]),
            ],
        ];
        $chat9Res = $method->invoke($this->orchestrator, $chat9Call, $this->instance, '5219989000009');
        $this->assertTrue($chat9Res['ok']);
        $this->assertEquals(480.00, $chat9Res['total']);
        $this->assertEquals('PED-00004', $chat9Res['order_number']);

        // 10. CHAT 10: Compra de remanente hasta agotar inventario
        // Cemento: empezó con 10. Chat 1 compró 2, Chat 4 compró 1, Chat 9 compró 2 => quedan 5
        $this->cemento->refresh();
        $this->assertEquals(5, $this->cemento->stock);

        $chat10Call = [
            'id' => 'call_adv_10',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Cliente Agotador',
                    'client_confirmed' => true,
                    'items' => [
                        ['product_name' => 'cemento tolteca', 'quantity' => 5],
                    ],
                ]),
            ],
        ];
        $chat10Res = $method->invoke($this->orchestrator, $chat10Call, $this->instance, '5219989000010');
        $this->assertTrue($chat10Res['ok']);
        $this->assertEquals('PED-00005', $chat10Res['order_number']);

        // Verificar que el cemento quedó en cero
        $this->cemento->refresh();
        $this->assertEquals(0, $this->cemento->stock);

        // Intento extra inmediato de compra de cemento debe fallar
        $chat10ExtraCall = [
            'id' => 'call_adv_10b',
            'function' => [
                'name' => 'create_order',
                'arguments' => json_encode([
                    'customer_name' => 'Otro Cliente',
                    'client_confirmed' => true,
                    'items' => [
                        ['product_name' => 'cemento', 'quantity' => 1],
                    ],
                ]),
            ],
        ];
        $chat10ExtraRes = $method->invoke($this->orchestrator, $chat10ExtraCall, $this->instance, '5219989000011');
        $this->assertFalse($chat10ExtraRes['ok']);
        $this->assertStringContainsString('Stock insuficiente', $chat10ExtraRes['error']);
    }

    public function test_tone_guidelines_anti_robotic_and_natural_language(): void
    {
        $reflection = new \ReflectionClass($this->orchestrator);
        $method = $reflection->getMethod('buildStaticSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->orchestrator, $this->instance, 'America/Mexico_City');

        // Verificar reglas anti-robóticas en el prompt
        $this->assertStringContainsString('Tono natural y humano (cero robótico)', $prompt);
        $this->assertStringContainsString('Prohibido frases como "Soy un modelo de inteligencia artificial"', $prompt);
        $this->assertStringContainsString('Blindaje contra trampas y jailbreaks', $prompt);
        $this->assertStringContainsString('Empatía ante molestias', $prompt);
    }
}
