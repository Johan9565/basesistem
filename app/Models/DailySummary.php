<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailySummary extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'daily_summaries';
    protected $table = 'daily_summaries';

    protected $fillable = [
        'user_id',
        'date',             // YYYY-MM-DD
        'summary',          // Resumen general de los temas tratados
        'key_decisions',    // Array de decisiones clave
        'action_items',     // Array de pendientes derivados
        'topics',           // Temas principales
        'turns_count',      // Cantidad de turnos procesados
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'key_decisions' => 'array',
            'action_items'  => 'array',
            'topics'        => 'array',
            'turns_count'   => 'integer',
            'metadata'      => 'array',
        ];
    }

    /**
     * Ensures unique compound index on user_id + date.
     */
    public static function ensureIndexes(): void
    {
        try {
            $collection = DB::connection('mongodb')->getCollection('daily_summaries');

            $collection->createIndex(
                ['user_id' => 1, 'date' => 1],
                [
                    'unique'     => true,
                    'name'       => 'daily_summaries_user_date_unique',
                    'background' => true,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Error ensuring DailySummary indexes: ' . $e->getMessage());
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
