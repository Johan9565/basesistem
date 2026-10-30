<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $query = Product::with(['branch', 'images'])->orderBy('name', 'asc');

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

        $products = $query->paginate(16)->through(function ($p) {
            // Resolver imagen principal: primera de la galería o fallback legacy
            $images = $p->images->map(fn (ProductImage $img) => [
                'id' => (string) $img->_id,
                'url' => $img->url,
                'path' => $img->path,
                'order' => $img->order,
                'is_primary' => (bool) $img->is_primary,
                'is_processed' => (bool) $img->is_processed,
            ])->values();

            $primaryImage = $images->firstWhere('is_primary', true) ?? $images->first();
            $imageUrl = $primaryImage['url'] ?? $p->image_url;

            // Compatibilidad: si no tenía imagen y generó fallback, persistirla
            if (empty($p->getRawOriginal('image_url')) && empty($p->image_path) && $images->isEmpty() && ! empty($imageUrl)) {
                $p->update(['image_url' => $imageUrl]);
            }

            return [
                'id' => (string) $p->_id,
                'branch_id' => (string) ($p->branch_id ?? ''),
                'branch_name' => $p->branch ? $p->branch->name : 'Sucursal Principal',
                'branch_code' => $p->branch ? ($p->branch->code ?? '') : '',
                'sku' => $p->sku,
                'name' => $p->name,
                'description' => $p->description,
                'category' => $p->category,
                'price' => $p->price,
                'cost' => $p->cost,
                'stock' => $p->stock,
                'min_stock' => $p->min_stock,
                'image_path' => $p->image_path ?? null,
                'image_url' => $imageUrl,
                'is_active' => (bool) $p->is_active,
                'images' => $images,
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
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['branch_id'])) {
            $companyContext = app(\App\Services\Tenancy\CompanyContext::class);
            $mainBranch = \App\Models\Branch::where('company_id', $companyContext->getCompanyId())
                ->where('is_main', true)
                ->first();
            $validated['branch_id'] = $mainBranch ? (string) $mainBranch->_id : null;
        }

        unset($validated['images']);

        $product = Product::create($validated);

        // Procesar imágenes subidas
        if ($request->hasFile('images')) {
            $this->storeProductImages($product, $request->file('images'));
        }

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Producto creado exitosamente en el inventario.',
        ]);
    }

    /**
     * Actualiza un producto existente.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['branch_id'])) {
            $companyContext = app(\App\Services\Tenancy\CompanyContext::class);
            $mainBranch = \App\Models\Branch::where('company_id', $companyContext->getCompanyId())
                ->where('is_main', true)
                ->first();
            $validated['branch_id'] = $mainBranch ? (string) $mainBranch->_id : null;
        }

        // 1. Eliminar imágenes marcadas para borrar
        $removeIds = $validated['remove_image_ids'] ?? [];
        if (! empty($removeIds)) {
            $this->deleteProductImages($product, $removeIds);
        }

        // 2. Actualizar imagen principal
        if (! empty($validated['primary_image_id'])) {
            $this->setPrimaryImage($product, $validated['primary_image_id']);
        }

        // 3. Actualizar orden de imágenes
        if (! empty($validated['image_order'])) {
            $this->reorderImages($validated['image_order']);
        }

        // 4. Subir nuevas imágenes
        if ($request->hasFile('images')) {
            $currentCount = ProductImage::where('product_id', (string) $product->_id)->count();
            $this->storeProductImages($product, $request->file('images'), $currentCount);
        }

        // 5. Actualizar campos del producto (sin los campos de imágenes)
        unset(
            $validated['images'],
            $validated['remove_image_ids'],
            $validated['image_order'],
            $validated['primary_image_id']
        );

        $product->update($validated);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Producto actualizado correctamente.',
        ]);
    }

    /**
     * Elimina un producto y todas sus imágenes.
     */
    public function destroy(Product $product): RedirectResponse
    {
        // Limpiar imágenes de la galería
        $product->purgeAllImages();

        // Limpiar imagen legacy si existe
        if (! empty($product->image_path)) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Producto eliminado del inventario.',
        ]);
    }

    /**
     * Almacena imágenes de producto, crea registros en BD y despacha jobs de procesamiento.
     *
     * @param  Product  $product
     * @param  array<\Illuminate\Http\UploadedFile>  $files
     * @param  int  $startOrder  Orden inicial para las nuevas imágenes.
     */
    private function storeProductImages(Product $product, array $files, int $startOrder = 0): void
    {
        $hasExistingPrimary = ProductImage::where('product_id', (string) $product->_id)
            ->where('is_primary', true)
            ->exists();

        foreach ($files as $index => $file) {
            $path = $file->store('products/'.(string) $product->_id, 'public');

            $image = ProductImage::create([
                'company_id' => $product->company_id,
                'product_id' => (string) $product->_id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'order' => $startOrder + $index + 1,
                'is_primary' => ! $hasExistingPrimary && $index === 0,
                'is_processed' => false,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            ProcessProductImage::dispatch((string) $image->_id);
        }
    }

    /**
     * Elimina imágenes específicas de un producto (storage + BD).
     */
    private function deleteProductImages(Product $product, array $imageIds): void
    {
        $images = ProductImage::where('product_id', (string) $product->_id)
            ->whereIn('_id', $imageIds)
            ->get();

        $wasPrimaryDeleted = false;

        foreach ($images as $img) {
            if ($img->is_primary) {
                $wasPrimaryDeleted = true;
            }
            if (! empty($img->path)) {
                Storage::disk('public')->delete($img->path);
            }
            $img->delete();
        }

        // Si se eliminó la primaria, promover la primera imagen restante
        if ($wasPrimaryDeleted) {
            $firstRemaining = ProductImage::where('product_id', (string) $product->_id)
                ->orderBy('order')
                ->first();

            if ($firstRemaining) {
                $firstRemaining->update(['is_primary' => true]);
            }
        }
    }

    /**
     * Establece la imagen principal de un producto.
     */
    private function setPrimaryImage(Product $product, string $imageId): void
    {
        // Quitar is_primary de todas las imágenes del producto
        ProductImage::where('product_id', (string) $product->_id)
            ->update(['is_primary' => false]);

        // Establecer la nueva primaria
        ProductImage::where('_id', $imageId)
            ->where('product_id', (string) $product->_id)
            ->update(['is_primary' => true]);
    }

    /**
     * Reordena las imágenes según el array de IDs proporcionado.
     */
    private function reorderImages(array $imageIds): void
    {
        foreach ($imageIds as $order => $imageId) {
            ProductImage::where('_id', $imageId)
                ->update(['order' => $order + 1]);
        }
    }
}
