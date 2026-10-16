<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Product;
use App\Services\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    /**
     * Listado de pedidos y gestión de inventario de la empresa activa.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->input('search', ''));
        $branchId = trim((string) $request->input('branch_id', ''));
        $status = trim((string) $request->input('status', ''));

        $companyContext = app(CompanyContext::class);
        $activeCompanyId = $companyContext->getCompanyId();

        $branches = [];
        $products = [];

        if ($activeCompanyId) {
            $branches = Branch::where('company_id', $activeCompanyId)
                ->where('is_active', true)
                ->orderByDesc('is_main')
                ->orderBy('name', 'asc')
                ->get()
                ->map(fn ($b) => [
                    'id' => (string) $b->_id,
                    'name' => $b->name,
                    'code' => $b->code ?? '',
                    'is_main' => (bool) $b->is_main,
                ])
                ->values()
                ->toArray();

            $products = Product::where('company_id', $activeCompanyId)
                ->where('is_active', true)
                ->orderBy('name', 'asc')
                ->get()
                ->map(fn ($p) => [
                    'id' => (string) $p->_id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'price' => (float) ($p->price ?? 0),
                    'stock' => (int) ($p->stock ?? 0),
                    'branch_id' => (string) ($p->branch_id ?? ''),
                ])
                ->values()
                ->toArray();
        }

        $query = Order::with('branch')->orderBy('created_at', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        if ($branchId !== '') {
            $query->where('branch_id', $branchId);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $allCompanyOrders = Order::where('company_id', $activeCompanyId)->get();
        $metrics = [
            'total_orders' => $allCompanyOrders->count(),
            'pending_orders' => $allCompanyOrders->whereIn('status', ['pending', 'processing'])->count(),
            'completed_orders' => $allCompanyOrders->where('status', 'completed')->count(),
            'total_revenue' => (float) $allCompanyOrders->where('status', '!=', 'cancelled')->sum('total'),
        ];

        $orders = $query->paginate(15)->through(function ($o) {
            return [
                'id' => (string) $o->_id,
                'order_number' => $o->order_number,
                'branch_id' => (string) ($o->branch_id ?? ''),
                'branch_name' => $o->branch?->name ?? 'Sucursal Principal',
                'customer_name' => $o->customer_name,
                'customer_email' => $o->customer_email,
                'customer_phone' => $o->customer_phone,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'payment_method' => $o->payment_method,
                'items' => $o->items ?? [],
                'subtotal' => $o->subtotal ?? 0,
                'tax' => $o->tax ?? 0,
                'discount' => $o->discount ?? 0,
                'total' => $o->total ?? 0,
                'notes' => $o->notes,
                'stock_deducted' => (bool) $o->stock_deducted,
                'created_at' => $o->created_at?->format('d/m/Y H:i') ?? '',
            ];
        })->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'branches' => $branches,
            'products' => $products,
            'metrics' => $metrics,
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
                'status' => $status,
            ],
            'can' => [
                'create' => $user->hasPermission('orders.create'),
                'edit' => $user->hasPermission('orders.edit'),
                'delete' => $user->hasPermission('orders.delete'),
            ],
        ]);
    }

    /**
     * Registra un nuevo pedido y descuenta el stock de los productos correspondientes.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|string',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'status' => 'required|string|in:pending,completed,cancelled',
            'payment_status' => 'required|string|in:unpaid,paid,partial',
            'payment_method' => 'nullable|string|in:cash,card,transfer,other',
            'notes' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $companyContext = app(CompanyContext::class);
        $activeCompanyId = $companyContext->getCompanyId();

        if (empty($validated['branch_id'])) {
            $mainBranch = Branch::where('company_id', $activeCompanyId)
                ->where('is_main', true)
                ->first();
            $validated['branch_id'] = $mainBranch ? (string) $mainBranch->_id : null;
        }

        // Validación y preparación de items
        $processedItems = [];
        $subtotal = 0;

        foreach ($validated['items'] as $item) {
            $productId = $item['product_id'];
            $qty = (int) $item['quantity'];

            $product = Product::where('company_id', $activeCompanyId)->find($productId);
            if (! $product) {
                return back()->withErrors([
                    'items' => "Uno de los productos seleccionados no existe o no pertenece a esta empresa.",
                ]);
            }

            // Si el pedido no nace cancelado, validar que haya stock suficiente
            if ($validated['status'] !== 'cancelled' && ($product->stock ?? 0) < $qty) {
                return back()->withErrors([
                    'items' => "Stock insuficiente para '{$product->name}'. Disponible: {$product->stock}, Solicitado: {$qty}.",
                ]);
            }

            $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price'])
                ? (float) $item['unit_price']
                : (float) ($product->price ?? 0);

            $itemSubtotal = $unitPrice * $qty;
            $subtotal += $itemSubtotal;

            $processedItems[] = [
                'product_id' => (string) $product->_id,
                'name' => $product->name,
                'sku' => $product->sku ?? '',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $itemSubtotal,
            ];
        }

        $discount = (float) ($validated['discount'] ?? 0);
        $tax = (float) ($validated['tax'] ?? 0);
        $total = max(0, $subtotal - $discount + $tax);

        // Generar número de pedido único y consecutivo sin traslapes
        $orderNumber = Order::generateNextOrderNumber($activeCompanyId);

        $order = Order::create([
            'company_id' => $activeCompanyId,
            'branch_id' => $validated['branch_id'],
            'order_number' => $orderNumber,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'] ?? null,
            'customer_phone' => $validated['customer_phone'] ?? null,
            'status' => $validated['status'],
            'payment_status' => $validated['payment_status'],
            'payment_method' => $validated['payment_method'] ?? 'cash',
            'notes' => $validated['notes'] ?? null,
            'items' => $processedItems,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'stock_deducted' => false,
            'user_id' => (string) $request->user()->_id,
        ]);

        // Si el estado no es cancelado, descontar de inventario inmediatamente
        if ($order->status !== 'cancelled') {
            $order->deductStock();
        }

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Pedido {$orderNumber} creado exitosamente y stock descontado del inventario.",
        ]);
    }

    /**
     * Actualiza el pedido y ajusta el inventario si cambia el estado (ej. cancelación).
     */
    public function update(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|string',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'status' => 'required|string|in:pending,completed,cancelled',
            'payment_status' => 'required|string|in:unpaid,paid,partial',
            'payment_method' => 'nullable|string|in:cash,card,transfer,other',
            'notes' => 'nullable|string',
        ]);

        $previousStatus = $order->status;
        $newStatus = $validated['status'];

        // Si pasa a cancelado y tenía stock descontado, restaurarlo al inventario
        if ($newStatus === 'cancelled' && $previousStatus !== 'cancelled' && $order->stock_deducted) {
            $order->restoreStock();
        }

        // Si estaba cancelado y pasa a activo (pending o completed), volver a descontar verificando existencias
        if ($previousStatus === 'cancelled' && $newStatus !== 'cancelled' && ! $order->stock_deducted) {
            // Verificar stock
            foreach ($order->items ?? [] as $item) {
                $product = Product::find($item['product_id']);
                if ($product && ($product->stock ?? 0) < ($item['quantity'] ?? 0)) {
                    return back()->withErrors([
                        'status' => "No se puede reactivar el pedido porque no hay suficiente stock de '{$product->name}'. Disponible: {$product->stock}.",
                    ]);
                }
            }
            $order->deductStock();
        }

        $order->update($validated);

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Pedido {$order->order_number} actualizado correctamente.",
        ]);
    }

    /**
     * Elimina un pedido y restaura el stock si estaba descontado.
     */
    public function destroy(Order $order): RedirectResponse
    {
        if ($order->stock_deducted) {
            $order->restoreStock();
        }

        $orderNumber = $order->order_number;
        $order->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Pedido {$orderNumber} eliminado y stock restaurado en el inventario.",
        ]);
    }
}
