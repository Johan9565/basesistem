<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'calendar_events';
    protected $table = 'calendar_events';

    protected $fillable = [
        'user_id',
        'title',
        'start_time',
        'end_time',
        'description',
        'location',
        'status',
        'google_event_id',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time'   => 'datetime',
            'synced_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
