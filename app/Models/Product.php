<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Product extends Model
{
    use HasFactory, BelongsToCompany;

    protected $connection = 'mongodb';
    protected $collection = 'products';
    protected $table      = 'products';

    protected $fillable = [
        'company_id',
        'sku',
        'name',
        'description',
        'category',
        'price',
        'cost',
        'stock',
        'min_stock',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price'     => 'float',
            'cost'      => 'float',
            'stock'     => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
            'metadata'  => 'array',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
