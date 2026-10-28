<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'assistant_messages';
    protected $table = 'assistant_messages';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'channel',        // 'app' | 'whatsapp'
        'direction',      // 'inbound' | 'outbound'
        'prompt',
        'reply',
        'display_text',
        'speech_text',
        'action',
        'status',         // 'received' | 'processing' | 'completed' | 'failed'
        'retry_count',
        'metadata',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'metadata'    => 'array',
            'retry_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
