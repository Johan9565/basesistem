<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class LogsModel extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'logs';

    protected $table = 'logs';

    protected $fillable = [
        'user_id',
        'action',
        'description',

    ];
}
