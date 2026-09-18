<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WhatsappInstance extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_instances';

    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'instance_name',
        'google_calendar_id',
        'system_prompt',
        'status',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
