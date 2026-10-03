<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class NotificationsModel extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'notifications';

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'message',
        'item_id',
        'item_ids',
        'is_read',
        'read_at',
        'links',
        'routes',
        'route',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'item_ids' => 'array',
            'links' => 'array',
            'routes' => 'array',
        ];
    }
}
