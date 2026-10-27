<?php

namespace App\Models;

use App\Services\Assistant\ModelRouterService;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;

class AssistantAiModel extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'assistant_ai_models';
    protected $table = 'assistant_ai_models';

    protected $fillable = [
        'alias',
        'role',         // 'write_intent' | 'read_intent' | 'reasoning_fallback'
        'base_url',     // OpenAI-compatible endpoint base
        'api_key',      // Encrypted in DB
        'model',        // Model identifier e.g. 'deepseek-chat', 'mimo-v2.6-pro-ultraspeed'
        'temperature',
        'max_tokens',
        'timeout_ms',
        'headers',      // Key-value array of custom headers
        'status',       // 'active' | 'inactive' | 'standby'
        'is_default',
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens'  => 'integer',
        'timeout_ms'  => 'integer',
        'headers'     => 'array',
        'is_default'  => 'boolean',
    ];

    /**
     * Encrypt API Key before saving in MongoDB.
     */
    public function setApiKeyAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['api_key'] = null;
            return;
        }

        // If it is already encrypted, do not re-encrypt
        try {
            Crypt::decryptString($value);
            $this->attributes['api_key'] = $value;
        } catch (\Throwable $e) {
            $this->attributes['api_key'] = Crypt::encryptString((string) $value);
        }
    }

    /**
     * Decrypt API Key when accessed.
     */
    public function getApiKeyAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * Masked API Key for secure frontend exposure.
     */
    public function getMaskedApiKey(): string
    {
        $key = $this->api_key;
        if (empty($key)) {
            return 'No configurada';
        }

        $len = strlen($key);
        if ($len <= 8) {
            return 'sk-****';
        }

        $prefix = substr($key, 0, 3);
        $suffix = substr($key, -4);

        return "{$prefix}...{$suffix}";
    }

    /**
     * Automatically invalidate router cache when models change.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            try {
                if (app()->bound(ModelRouterService::class)) {
                    app(ModelRouterService::class)->invalidateCache();
                }
            } catch (\Throwable $e) {
                // Ignore if in test bootstrapping
            }
        });

        static::deleted(function () {
            try {
                if (app()->bound(ModelRouterService::class)) {
                    app(ModelRouterService::class)->invalidateCache();
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        });
    }
}
