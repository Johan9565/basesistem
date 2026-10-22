<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceNote extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'workspace_notes';
    protected $table = 'workspace_notes';

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'tags',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'tags'      => 'array',
            'is_pinned' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
