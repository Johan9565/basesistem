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

        $query = Product::query()->orderBy('name', 'asc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $products = $query->paginate(15)->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters'  => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Guarda un nuevo producto.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sku'         => 'required|string|max:50',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'price'       => 'required|numeric|min:0',
            'cost'        => 'nullable|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'min_stock'   => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        Product::create($validated);

        return back()->with('flash', [
            'type'    => 'success',
            'message' => 'Producto creado exitosamente en el inventario.',
        ]);
    }

    /**
     * Actualiza un producto existente.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'sku'         => 'required|string|max:50',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:100',
            'price'       => 'required|numeric|min:0',
            'cost'        => 'nullable|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'min_stock'   => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $product->update($validated);

        return back()->with('flash', [
            'type'    => 'success',
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
            'type'    => 'success',
            'message' => 'Producto eliminado del inventario.',
        ]);
    }
}
