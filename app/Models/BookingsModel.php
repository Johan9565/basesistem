<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class BookingsModel extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'bookings';
    protected $table      = 'bookings';

    protected $fillable = [
        '_id',
        'photographer_id',
        'title',
        'start',
        'end',
        'status',
        'notes',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
        ];
    }
}
