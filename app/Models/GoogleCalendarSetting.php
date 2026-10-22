<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarSetting extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'google_calendar_settings';
    protected $table = 'google_calendar_settings';

    protected $fillable = [
        'user_id',
        'calendar_id',
        'service_account_json',
        'timezone',
        'sync_enabled',
        'auto_sync_on_create',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'sync_enabled'        => 'boolean',
            'auto_sync_on_create' => 'boolean',
            'last_synced_at'      => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
