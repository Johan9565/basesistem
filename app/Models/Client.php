<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'clients';

    protected $table = 'clients';

    protected $fillable = [
        'name',
        'document_number',
        'billing_email',
        'phone',
        'status',          // 'active', 'suspended', 'cancelled'
        'plan',            // 'basic', 'pro', 'enterprise'
        'max_companies',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'max_companies' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function companies()
    {
        return $this->hasMany(Company::class, 'client_id');
    }
}
