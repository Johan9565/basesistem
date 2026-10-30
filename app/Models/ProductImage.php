<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use MongoDB\Laravel\Eloquent\Model;

class ProductImage extends Model
{
    use BelongsToCompany;

    protected $connection = 'mongodb';

    protected $collection = 'product_images';

    protected $table = 'product_images';

    protected $fillable = [
        'company_id',
        'product_id',
        'path',
        'original_name',
        'order',
        'is_primary',
        'is_processed',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_primary' => 'boolean',
            'is_processed' => 'boolean',
            'size' => 'integer',
        ];
    }

    /**
     * Producto al que pertenece esta imagen.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * URL pública de la imagen procesada.
     */
    public function getUrlAttribute(): ?string
    {
        if (! empty($this->path)) {
            return asset('storage/'.$this->path);
        }

        return null;
    }
}

