<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;

class WhatsappMessage extends Model
{
    use BelongsToCompany;

    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_messages';

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'company_id',
        'instance_name',
        'user_phone',
        'role',
        'content',
        'tool_call_id',
        'tool_name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
