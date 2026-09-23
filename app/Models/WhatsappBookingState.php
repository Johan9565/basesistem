<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WhatsappBookingState extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_booking_states';

    protected $table = 'whatsapp_booking_states';

    public const STAGE_RECEPTION = 'RECEPTION';

    public const STAGE_COLLECTING = 'COLLECTING';

    public const STAGE_CONFIRMING = 'CONFIRMING';

    public const STAGE_COMPLETED = 'COMPLETED';

    /** @var list<string> */
    public const STAGES = [
        self::STAGE_RECEPTION,
        self::STAGE_COLLECTING,
        self::STAGE_CONFIRMING,
        self::STAGE_COMPLETED,
    ];

    protected $fillable = [
        'instance_name',
        'user_phone',
        'step',
        'name',
        'service',
        'date',
        'time',
        'location',
        'notes',
        'intent',
        'friction_count',
        'bot_paused_at',
        'escalation_reason',
        'escalation_detail',
        'last_interaction_at',
        'welcome_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'friction_count' => 'integer',
            'bot_paused_at' => 'datetime',
            'last_interaction_at' => 'datetime',
            'welcome_sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
