<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WhatsappAppointment extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_appointments';

    protected $table = 'whatsapp_appointments';

    protected $fillable = [
        'instance_name',
        'user_phone',
        'summary',
        'description',
        'starts_at',
        'ends_at',
        'timezone',
        'google_calendar_id',
        'google_event_id',
        'google_html_link',
        'sync_status',
        'sync_error',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
