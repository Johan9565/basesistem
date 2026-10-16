<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Order;
use App\Models\Product;
use App\Models\RoleModel;
use App\Models\User;
use App\Services\Tenancy\PermissionCacheService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrdersModuleAndInventoryDeductionTest extends TestCase
{
    protected User $adminUser;
    protected Company $company;
    protected Branch $branch;
    protected RoleModel $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\OrdersModuleSeeder']);

        $this->adminRole = RoleModel::firstOrCreate(
            ['role' => 'admin'],
            [
                'name' => 'Administrador de Empresa',
                'status' => 1,
                'permissions' => [
                    'orders',
                    'orders.view',
                    'orders.create',
                    'orders.edit',
                    'orders.delete',
                    'products',
                    'products.view',
                    'products.create',
                    'products.edit',
                ],
            ]
        );

        $this->adminUser = User::create([
            'name' => 'Admin Test',
            'email' => 'admin_orders_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'role_id' => (string) $this->adminRole->_id,
            'status' => 1,
        ]);

        $this->company = Company::create([
            'name' => 'Empresa Test Pedidos',
            'slug' => 'empresa-test-pedidos-'.uniqid(),
            'user_id' => (string) $this->adminUser->_id,
            'status' => 'active',
            'modules' => [
                'inventory' => true,
                'orders' => true,
            ],
        ]);

        $this->branch = Branch::create([
            'company_id' => (string) $this->company->_id,
            'name' => 'Matriz Central',
            'code' => 'MAT',
            'is_main' => true,
            'is_active' => true,
        ]);

        CompanyUser::create([
            'user_id' => (string) $this->adminUser->_id,
            'company_id' => (string) $this->company->_id,
            'role_id' => (string) $this->adminRole->_id,
            'status' => 'active',
            'is_owner' => true,
        ]);

        app(PermissionCacheService::class)->invalidateAll();
    }

    public function test_orders_module_inherits_from_inventory(): void
    {
        // 1. Con ambos activos, isModuleEnabled('orders') es true
        $this->assertTrue($this->company->isModuleEnabled('inventory'));
        $this->assertTrue($this->company->isModuleEnabled('orders'));

        // 2. Si inventario se desactiva, pedidos también es false automáticamente
        $this->company->disableModule('inventory');
        $this->assertFalse($this->company->isModuleEnabled('inventory'));
        $this->assertFalse($this->company->isModuleEnabled('orders'));

        // 3. Si se activa pedidos cuando inventario está desactivado, inventario se auto-activa
        $this->company->enableModule('orders');
        $this->assertTrue($this->company->isModuleEnabled('inventory'));
        $this->assertTrue($this->company->isModuleEnabled('orders'));
    }

    public function test_cannot_access_orders_if_module_is_disabled(): void
    {
        $this->company->disableModule('orders');

        $response = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => (string) $this->company->_id])
            ->get(route('orders'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_creating_order_deducts_inventory_stock(): void
    {
        $product = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Laptop Gamer RTX',
            'sku' => 'LAP-RTX-01',
            'price' => 1500.00,
            'stock' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => (string) $this->company->_id])
            ->post(route('orders.store'), [
                'branch_id' => (string) $this->branch->_id,
                'customer_name' => 'Carlos Cliente',
                'customer_phone' => '5551234567',
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'items' => [
                    [
                        'product_id' => (string) $product->_id,
                        'quantity' => 3,
                        'unit_price' => 1500.00,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.type', 'success');

        // Verificar que el stock se descontó de 10 a 7
        $product->refresh();
        $this->assertEquals(7, $product->stock);

        // Verificar que el pedido existe y tiene stock_deducted = true
        $order = Order::where('customer_name', 'Carlos Cliente')->first();
        $this->assertNotNull($order);
        $this->assertTrue($order->stock_deducted);
        $this->assertEquals(4500.00, $order->total);
    }

    public function test_order_creation_fails_if_insufficient_stock(): void
    {
        $product = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Mouse Ergonómico',
            'sku' => 'MOU-ERG-01',
            'price' => 50.00,
            'stock' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => (string) $this->company->_id])
            ->post(route('orders.store'), [
                'branch_id' => (string) $this->branch->_id,
                'customer_name' => 'Comprador Excesivo',
                'status' => 'completed',
                'payment_status' => 'paid',
                'items' => [
                    [
                        'product_id' => (string) $product->_id,
                        'quantity' => 5, // Mayor que 2
                        'unit_price' => 50.00,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('items');

        // El stock no debe alterarse
        $product->refresh();
        $this->assertEquals(2, $product->stock);
    }

    public function test_cancelling_order_restores_inventory_stock(): void
    {
        $product = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Teclado Mecánico',
            'sku' => 'TEC-MEC-01',
            'price' => 100.00,
            'stock' => 5,
            'is_active' => true,
        ]);

        // Crear pedido con 2 teclados
        $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => (string) $this->company->_id])
            ->post(route('orders.store'), [
                'branch_id' => (string) $this->branch->_id,
                'customer_name' => 'Cliente Arrepentido',
                'status' => 'completed',
                'payment_status' => 'paid',
                'items' => [
                    [
                        'product_id' => (string) $product->_id,
                        'quantity' => 2,
                        'unit_price' => 100.00,
                    ],
                ],
            ]);

        $product->refresh();
        $this->assertEquals(3, $product->stock);

        $order = Order::where('customer_name', 'Cliente Arrepentido')->first();

        // Cambiar estado a cancelado
        $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => (string) $this->company->_id])
            ->patch(route('orders.update', $order->id), [
                'customer_name' => 'Cliente Arrepentido',
                'status' => 'cancelled',
                'payment_status' => 'unpaid',
            ]);

        // El stock debe haberse restaurado a 5
        $product->refresh();
        $this->assertEquals(5, $product->stock);

        $order->refresh();
        $this->assertFalse($order->stock_deducted);
    }

    public function test_deleting_order_restores_inventory_stock(): void
    {
        $product = Product::create([
            'company_id' => (string) $this->company->_id,
            'branch_id' => (string) $this->branch->_id,
            'name' => 'Monitor 4K',
            'sku' => 'MON-4K-01',
            'price' => 400.00,
            'stock' => 4,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => (string) $this->company->_id])
            ->post(route('orders.store'), [
                'branch_id' => (string) $this->branch->_id,
                'customer_name' => 'Cliente Eliminar',
                'status' => 'completed',
                'payment_status' => 'paid',
                'items' => [
                    [
                        'product_id' => (string) $product->_id,
                        'quantity' => 1,
                        'unit_price' => 400.00,
                    ],
                ],
            ]);

        $product->refresh();
        $this->assertEquals(3, $product->stock);

        $order = Order::where('customer_name', 'Cliente Eliminar')->first();

        // Eliminar pedido
        $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => (string) $this->company->_id])
            ->delete(route('orders.destroy', $order->id));

        // El stock debe volver a 4
        $product->refresh();
        $this->assertEquals(4, $product->stock);
        $this->assertNull(Order::find($order->id));
    }
}
