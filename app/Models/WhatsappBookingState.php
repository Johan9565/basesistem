<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WhatsappBookingState extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_booking_states';

    protected $table = 'whatsapp_booking_states';

    protected $fillable = [
        'instance_name',
        'user_phone',
        'step',
        'name',
        'service',
        'date',
        'time',
        'notes',
        'last_interaction_at',
        'welcome_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'last_interaction_at' => 'datetime',
            'welcome_sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
