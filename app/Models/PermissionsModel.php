<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class PermissionsModel extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'permissions';

    protected $table = 'permissions';

    protected $fillable = [
        'name',        // "viaticos", "users_system", etc.
        'description', // "Gestión de viáticos"
        'status',      // 1 o 0
        'icon',        // El HTML del icono
        'module',       // El slug que conecta con el Módulo
    ];
}
