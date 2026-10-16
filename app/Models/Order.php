<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Order extends Model
{
    use BelongsToCompany, HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'orders';

    protected $table = 'orders';

    protected $fillable = [
        'company_id',
        'branch_id',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status',          // 'pending', 'completed', 'cancelled'
        'payment_status',  // 'unpaid', 'paid', 'partial'
        'payment_method',  // 'cash', 'transfer', 'card', 'other'
        'items',           // [['product_id', 'name', 'sku', 'quantity', 'unit_price', 'subtotal']]
        'subtotal',
        'tax',
        'discount',
        'total',
        'notes',
        'stock_deducted',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'subtotal' => 'float',
            'tax' => 'float',
            'discount' => 'float',
            'total' => 'float',
            'stock_deducted' => 'boolean',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Descuenta del inventario los productos del pedido.
     */
    public function deductStock(): bool
    {
        if ($this->stock_deducted) {
            return false;
        }

        $items = $this->items ?? [];
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $qty = (int) ($item['quantity'] ?? 0);

            if ($productId && $qty > 0) {
                $product = Product::find($productId);
                if ($product) {
                    $newStock = max(0, ($product->stock ?? 0) - $qty);
                    $product->update(['stock' => $newStock]);
                }
            }
        }

        $this->update(['stock_deducted' => true]);

        return true;
    }

    /**
     * Restaura al inventario los productos del pedido (ej. por cancelación o eliminación).
     */
    public function restoreStock(): bool
    {
        if (! $this->stock_deducted) {
            return false;
        }

        $items = $this->items ?? [];
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $qty = (int) ($item['quantity'] ?? 0);

            if ($productId && $qty > 0) {
                $product = Product::find($productId);
                if ($product) {
                    $product->update(['stock' => ($product->stock ?? 0) + $qty]);
                }
            }
        }

        $this->update(['stock_deducted' => false]);

        return true;
    }

    /**
     * Genera el siguiente número consecutivo de pedido sin traslapes ni colisiones.
     */
    public static function generateNextOrderNumber(string $companyId): string
    {
        $lock = \Illuminate\Support\Facades\Cache::lock("seq:order_num:{$companyId}", 10);

        return $lock->block(5, function () use ($companyId) {
            $lastOrder = static::where('company_id', $companyId)
                ->where('order_number', 'regex', '/^PED-\d+$/')
                ->orderBy('order_number', 'desc')
                ->first();

            $nextNum = 1;
            if ($lastOrder && preg_match('/^PED-(\d+)$/', (string) $lastOrder->order_number, $matches)) {
                $nextNum = ((int) $matches[1]) + 1;
            } else {
                $count = static::where('company_id', $companyId)->count();
                $nextNum = $count + 1;
            }

            do {
                $orderNumber = 'PED-'.str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
                $exists = static::where('company_id', $companyId)->where('order_number', $orderNumber)->exists();
                if ($exists) {
                    $nextNum++;
                }
            } while ($exists);

            return $orderNumber;
        });
    }
}
