<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class TelegramAdminMessage extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'telegram_admin_messages';

    protected $table = 'telegram_admin_messages';

    protected $fillable = [
        'instance_name',
        'chat_id',
        'role',
        'content',
        'tool_call_id',
        'tool_name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
