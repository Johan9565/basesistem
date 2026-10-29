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
            $collection = DB::connection('mongodb')->getCollection('daily_summaries');

            // Unique compound index on user_id (asc) and date (asc)
            $collection->createIndex(
                ['user_id' => 1, 'date' => 1],
                [
                    'unique'     => true,
                    'name'       => 'daily_summaries_user_date_unique',
                    'background' => true,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('DailySummaries indexes creation notice: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            $collection = DB::connection('mongodb')->getCollection('daily_summaries');
            $collection->dropIndex('daily_summaries_user_date_unique');
        } catch (\Throwable $e) {
            Log::warning('DailySummaries indexes drop notice: ' . $e->getMessage());
        }
    }
};

