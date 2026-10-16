<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'branches';

    protected $table = 'branches';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'phone',
        'email',
        'address',
        'business_hours',
        'is_main',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function whatsappInstances()
    {
        return $this->hasMany(WhatsappInstance::class, 'branch_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'branch_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'branch_id');
    }

    public function appointments()
    {
        return $this->hasMany(WhatsappAppointment::class, 'branch_id');
    }
}
