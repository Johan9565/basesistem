<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MirModel extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'mir';

    protected $table = 'mir';

    protected $fillable = [
        'programa_presupuestario',
    ];
}
