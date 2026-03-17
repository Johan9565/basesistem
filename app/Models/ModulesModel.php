<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class ModulesModel extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'modules';
    protected $table      = 'modules';

    // Mapeamos los campos tal cual los tienes en tu JSON
    protected $fillable = [
        'route',
        'status',
        'relation',
        'order_index',
        'name'
    ];
}
