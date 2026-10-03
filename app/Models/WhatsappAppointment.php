<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use MongoDB\Laravel\Eloquent\Model;

class WhatsappAppointment extends Model
{
    use BelongsToCompany;

    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_appointments';

    protected $table = 'whatsapp_appointments';

    protected $fillable = [
        'company_id',
        'service_id',
        'employee_id',
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

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

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
