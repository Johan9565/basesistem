<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class CompanyUser extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'company_user';
    protected $table      = 'company_user';

    protected $fillable = [
        'user_id',
        'company_id',
        'role_id',
        'status',             // 'active', 'inactive', 'invited'
        'is_owner',           // boolean
        'custom_permissions', // array de slugs o IDs para excepciones
    ];

    protected function casts(): array
    {
        return [
            'is_owner'           => 'boolean',
            'custom_permissions' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function role()
    {
        return $this->belongsTo(RoleModel::class, 'role_id');
    }
}
