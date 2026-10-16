<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    /**
     * Listado de productos del inventario de la empresa activa.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $branchId = trim((string) $request->input('branch_id', ''));

        $companyContext = app(\App\Services\Tenancy\CompanyContext::class);
        $activeCompanyId = $companyContext->getCompanyId();

        $branches = [];
        if ($activeCompanyId) {
            $branches = \App\Models\Branch::where('company_id', $activeCompanyId)
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
        }

        $query = Product::with('branch')->orderBy('name', 'asc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($branchId !== '') {
            $query->where('branch_id', $branchId);
        }

        $products = $query->paginate(15)->through(function ($p) {
            return [
                'id' => (string) $p->_id,
                'branch_id' => (string) ($p->branch_id ?? ''),
                'branch_name' => $p->branch?->name ?? 'Sucursal Principal',
                'branch_code' => $p->branch?->code ?? '',
                'sku' => $p->sku,
                'name' => $p->name,
                'description' => $p->description,
                'category' => $p->category,
                'price' => $p->price,
                'cost' => $p->cost,
                'stock' => $p->stock,
                'min_stock' => $p->min_stock,
                'is_active' => (bool) $p->is_active,
            ];
        })->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'branches' => $branches,
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
            ],
        ]);
    }

    /**
     * Guarda un nuevo producto.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|string',
            'sku' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['branch_id'])) {
            $companyContext = app(\App\Services\Tenancy\CompanyContext::class);
            $mainBranch = \App\Models\Branch::where('company_id', $companyContext->getCompanyId())
                ->where('is_main', true)
                ->first();
            $validated['branch_id'] = $mainBranch ? (string) $mainBranch->_id : null;
        }

        Product::create($validated);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Producto creado exitosamente en el inventario.',
        ]);
    }

    /**
     * Actualiza un producto existente.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|string',
            'sku' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['branch_id'])) {
            $companyContext = app(\App\Services\Tenancy\CompanyContext::class);
            $mainBranch = \App\Models\Branch::where('company_id', $companyContext->getCompanyId())
                ->where('is_main', true)
                ->first();
            $validated['branch_id'] = $mainBranch ? (string) $mainBranch->_id : null;
        }

        $product->update($validated);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Producto actualizado correctamente.',
        ]);
    }

    /**
     * Elimina un producto.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Producto eliminado del inventario.',
        ]);
    }
}
