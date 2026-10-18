<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Product extends Model
{
    use BelongsToCompany, HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'products';

    protected $table = 'products';

    protected $fillable = [
        'company_id',
        'branch_id',
        'sku',
        'name',
        'description',
        'category',
        'price',
        'cost',
        'stock',
        'min_stock',
        'is_active',
        'image_path',
        'image_url',
        'metadata',
    ];

    public function getImageUrlAttribute(?string $value): ?string
    {
        if (! empty($value)) {
            return $value;
        }

        if (! empty($this->image_path)) {
            return asset('storage/'.$this->image_path);
        }

        return self::resolveFallbackFreeImage($this->name, $this->category, $this->sku);
    }

    public static function resolveFallbackFreeImage(?string $name = '', ?string $category = '', ?string $sku = ''): string
    {
        $text = mb_strtolower(trim(($name ?? '').' '.($category ?? '').' '.($sku ?? '')));

        if (str_contains($text, 'cepillo') || str_contains($text, 'dental') || str_contains($text, 'bucal') || str_contains($text, 'dientes') || str_contains($text, 'enjuague')) {
            if (str_contains($text, 'enjuague') || str_contains($text, 'clorhexidina')) {
                return 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=800&q=80';
            }
            return 'https://images.unsplash.com/photo-1559494007-9f5847c49d94?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'guante') || str_contains($text, 'nitrilo') || str_contains($text, 'proteccion') || str_contains($text, 'protección')) {
            return 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'termometro') || str_contains($text, 'termómetro') || str_contains($text, 'infrarrojo')) {
            return 'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'oximetro') || str_contains($text, 'oxímetro') || str_contains($text, 'pulso') || str_contains($text, 'sensor')) {
            return 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'cemento') || str_contains($text, 'construccion') || str_contains($text, 'construcción') || str_contains($text, 'concreto')) {
            return 'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'herramienta') || str_contains($text, 'taladro') || str_contains($text, 'tornillo')) {
            return 'https://images.unsplash.com/photo-1504148455328-c376907d081c?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'tenis') || str_contains($text, 'calzado') || str_contains($text, 'zapato') || str_contains($text, 'nike')) {
            return 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'audifono') || str_contains($text, 'audífono') || str_contains($text, 'headphone') || str_contains($text, 'bluetooth')) {
            return 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'reloj') || str_contains($text, 'smartwatch')) {
            return 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'cafe') || str_contains($text, 'café') || str_contains($text, 'alimento') || str_contains($text, 'bebida')) {
            return 'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'termo') || str_contains($text, 'botella')) {
            return 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?auto=format&fit=crop&w=800&q=80';
        }

        if (str_contains($text, 'mascarilla') || str_contains($text, 'cubrebocas') || str_contains($text, 'tapabocas')) {
            return 'https://images.unsplash.com/photo-1586942593568-29361efcd571?auto=format&fit=crop&w=800&q=80';
        }

        $genericPool = [
            'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1572635196237-14b3f281503f?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1560343090-f0409e92791a?auto=format&fit=crop&w=800&q=80',
        ];

        $index = abs(crc32($text ?: 'generic')) % count($genericPool);

        return $genericPool[$index];
    }

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'cost' => 'float',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
