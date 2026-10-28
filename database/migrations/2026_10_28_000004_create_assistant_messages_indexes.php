<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            $collection = DB::connection('mongodb')->getCollection('assistant_messages');

            // 1. Text index on 'content' with spanish language support
            $collection->createIndex(
                ['content' => 'text'],
                [
                    'default_language' => 'spanish',
                    'name'             => 'assistant_messages_content_text_spanish',
                    'background'       => true,
                ]
            );

            // 2. Compound index on user_id (asc) and created_at (desc) for fast temporal filtering
            $collection->createIndex(
                ['user_id' => 1, 'created_at' => -1],
                [
                    'name'       => 'assistant_messages_user_created_at_desc',
                    'background' => true,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('AssistantMessages indexes creation notice: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            $collection = DB::connection('mongodb')->getCollection('assistant_messages');
            $collection->dropIndex('assistant_messages_content_text_spanish');
            $collection->dropIndex('assistant_messages_user_created_at_desc');
        } catch (\Throwable $e) {
            Log::warning('AssistantMessages indexes drop notice: ' . $e->getMessage());
        }
    }
};

