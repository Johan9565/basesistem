<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WhatsappInstance extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_instances';

    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'instance_name',
        'evolution_instance_name',
        'google_calendar_id',
        'google_credentials_path',
        'google_service_email',
        'system_prompt',
        'business_name',
        'services',
        'business_hours',
        'prices',
        'promotions',
        'locations',
        'ai_provider',
        'status',
        'timezone',
        'telegram_bot_token',
        'telegram_bot_username',
        'telegram_webhook_secret',
        'telegram_link_code',
        'telegram_allowed_user_ids',
    ];

    protected $hidden = [
        'telegram_bot_token',
        'telegram_webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'telegram_allowed_user_ids' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Nombre en Evolution API (sesión WhatsApp). Puede compartirse entre perfiles de negocio.
     */
    public function evolutionName(): string
    {
        $shared = trim((string) ($this->evolution_instance_name ?? ''));

        return $shared !== '' ? $shared : (string) ($this->instance_name ?? '');
    }

    /**
     * Perfil activo que atiende una sesión Evolution (webhook).
     */
    public static function resolveActiveByEvolutionSession(string $evolutionName): ?self
    {
        $evolutionName = trim($evolutionName);
        if ($evolutionName === '') {
            return null;
        }

        $byShared = static::query()
            ->where('status', 'active')
            ->where('evolution_instance_name', $evolutionName)
            ->first();

        if ($byShared) {
            return $byShared;
        }

        return static::query()
            ->where('status', 'active')
            ->where('instance_name', $evolutionName)
            ->where(function ($q) {
                $q->whereNull('evolution_instance_name')
                    ->orWhere('evolution_instance_name', '')
                    ->orWhere('evolution_instance_name', null);
            })
            ->first();
    }
}
