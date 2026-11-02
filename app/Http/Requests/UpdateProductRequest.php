<?php

namespace App\Http\Requests;

use App\Models\ProductImage;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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

            // Imágenes nuevas a subir
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:10240',

            // IDs de imágenes existentes a eliminar
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'string',

            // Orden de imágenes: array de IDs en el orden deseado
            'image_order' => 'nullable|array|max:5',
            'image_order.*' => 'string',

            // ID de la imagen principal
            'primary_image_id' => 'nullable|string',
        ];
    }

    /**
     * Validación adicional: verificar que el total de imágenes no exceda 5.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = $this->route('product');
            if (! $product) {
                return;
            }

            $currentCount = ProductImage::where('product_id', (string) $product->_id)->count();
            $removeCount = count($this->input('remove_image_ids', []));
            $addCount = count($this->file('images', []));

            $finalCount = $currentCount - $removeCount + $addCount;

            if ($finalCount > 5) {
                $validator->errors()->add(
                    'images',
                    "El producto tendría {$finalCount} imágenes. El máximo permitido es 5. Actualmente tiene {$currentCount}, eliminas {$removeCount} y subes {$addCount}."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'images.max' => 'Solo puedes subir un máximo de 5 imágenes nuevas a la vez.',
            'images.*.image' => 'Cada archivo debe ser una imagen válida.',
            'images.*.mimes' => 'Solo se permiten imágenes en formato JPEG, PNG o WebP.',
            'images.*.max' => 'Cada imagen no debe superar los 10MB.',
        ];
    }
}

