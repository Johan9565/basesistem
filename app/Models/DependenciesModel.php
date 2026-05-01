<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DependenciesModel extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'dependencies';
    protected $table      = 'dependencies';

    protected $fillable = [
        'name',
        'status',
        'parent_id',
    ];
}
