<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_messages';

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'instance_name',
        'user_phone',
        'role',
        'content',
        'tool_call_id',
        'tool_name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
