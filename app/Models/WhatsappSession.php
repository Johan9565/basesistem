<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use MongoDB\Laravel\Eloquent\Model;

class WhatsappSession extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVA = 'activa';

    public const STATUS_CERRADA = 'cerrada';

    public const REASON_INACTIVIDAD = 'inactividad';

    public const REASON_COMANDO = 'comando_cerrar';

    public const REASON_IA = 'ia_despedida';

    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_sessions';

    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'company_id',
        'instance_name',
        'user_phone',
        'session_id',
        'status',
        'close_reason',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
