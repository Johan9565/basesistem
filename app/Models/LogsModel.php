<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LogsModel extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'logs';
    protected $table      = 'logs';

    protected $fillable = [
        'user_id',
        'action',
        'description',

    ];
}
