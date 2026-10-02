<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use App\Models\PermissionsModel;
class RoleModel extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'roles';
    protected $table      = 'roles';

    protected $fillable = [
        'name', 'role', 'permissions', 'status', 'company_id', 'is_system'
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // getAttribute('permissions') de Eloquent devuelve [] en MongoDB
    // por conflicto interno — leemos directo de los atributos raw
    public function getPermissionsList(): array
    {
        return $this->getAttributes()['permissions'] ?? [];
    }

    public function permissions()
    {
        return $this->hasMany(PermissionsModel::class, 'role_id');
    }

    /**
     * Scope para obtener roles disponibles para una empresa (roles globales + roles propios).
     */
    public function scopeAvailableForCompany($query, ?string $companyId = null)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->whereNull('company_id')->orWhere('company_id', '');
            if (!empty($companyId)) {
                $q->orWhere('company_id', (string) $companyId);
            }
        });
    }
}
