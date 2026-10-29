<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        'role',           // 'user' | 'assistant'
        'content',        // Raw turn text
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

    protected static function booted(): void
    {
        static::saving(function (AssistantMessage $message) {
            // Auto-sync role and content with legacy prompt/reply fields
            if (!empty($message->prompt) && empty($message->content)) {
                $message->content = $message->prompt;
                $message->role = $message->role ?: 'user';
            } elseif (!empty($message->content) && empty($message->prompt) && $message->role === 'user') {
                $message->prompt = $message->content;
            }

            if (!empty($message->reply) && empty($message->content) && $message->role === 'assistant') {
                $message->content = $message->reply;
            } elseif (!empty($message->content) && empty($message->reply) && $message->role === 'assistant') {
                $message->reply = $message->content;
                if (empty($message->display_text)) {
                    $message->display_text = $message->content;
                }
            }
        });
    }

    /**
     * Programmatically ensures required MongoDB compound and text indexes.
     */
    public static function ensureIndexes(): void
    {
        try {
            $collection = DB::connection('mongodb')->getCollection('assistant_messages');

            $collection->createIndex(
                ['content' => 'text'],
                [
                    'default_language' => 'spanish',
                    'name'             => 'assistant_messages_content_text_spanish',
                    'background'       => true,
                ]
            );

            $collection->createIndex(
                ['user_id' => 1, 'created_at' => -1],
                [
                    'name'       => 'assistant_messages_user_created_at_desc',
                    'background' => true,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Error ensuring AssistantMessage indexes: ' . $e->getMessage());
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
