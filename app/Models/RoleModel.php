<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
class RoleModel extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'roles';
    protected $table      = 'roles';

    protected $fillable = [
        'name', 'role', 'permissions', 'status'
    ];

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
}
