<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use MongoDB\Laravel\Eloquent\Model;

class WhatsappInstance extends Model
{
    use BelongsToCompany;

    /**
     * Longitud máxima permitida para system_prompt (configuración de instancia).
     */
    public const SYSTEM_PROMPT_MAX = 10000;

    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_instances';

    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'company_id',
        'branch_id',
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
        'slot_busy_policy',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

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
     * Límite duro del campo: la configuración de la instancia nunca debe
     * guardar más de SYSTEM_PROMPT_MAX caracteres.
     */
    public function setSystemPromptAttribute($value): void
    {
        $text = $value === null ? null : (string) $value;

        if ($text !== null && $text !== '') {
            $text = mb_substr($text, 0, self::SYSTEM_PROMPT_MAX);
        }

        $this->attributes['system_prompt'] = $text;
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

        // Filtrado en PHP: evita fallos de whereNull/orWhere raros en Mongo
        // cuando hay perfiles duplicados que comparten la misma sesión Evolution.
        return static::withoutGlobalScopes()
            ->where('status', 'active')
            ->get()
            ->first(fn (self $row) => $row->evolutionName() === $evolutionName);
    }
}
