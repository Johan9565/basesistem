<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WhatsappInstance extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'whatsapp_instances';
    protected $table      = 'whatsapp_instances';

    protected $fillable = [
        'user_id',
        'instance_name',
        'phone',
        'status',
        'profile_name',
        'profile_pic_url',
        'system_prompt',
        'enabled',
        'webhook_url',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'enabled'    => 'boolean',
            'settings'   => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
