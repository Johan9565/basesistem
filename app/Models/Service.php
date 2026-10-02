<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Service extends Model
{
    use HasFactory, BelongsToCompany;

    protected $connection = 'mongodb';
    protected $collection = 'services';
    protected $table      = 'services';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'duration_minutes',
        'price',
        'assigned_user_ids', // array de strings/ObjectIds de usuarios que brindan el servicio
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes'  => 'integer',
            'price'             => 'float',
            'assigned_user_ids' => 'array',
            'is_active'         => 'boolean',
            'metadata'          => 'array',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
