<?php

namespace App\Console\Commands;

use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\DailySummaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateDailySummariesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'assistant:generate-daily-summaries
                            {--date= : The date to summarize in YYYY-MM-DD format (defaults to yesterday)}
                            {--user= : Optional user ID to process exclusively}';

    /**
     * The console command description.
     */
    protected $description = 'Extracts and synthesizes conversation messages from the preceding day into daily_summaries';

    /**
     * Execute the console command.
     */
    public function handle(DailySummaryService $summaryService): int
    {
        $timezone = 'America/Cancun';
        $targetDate = $this->option('date') ?: Carbon::yesterday($timezone)->format('Y-m-d');
        $targetUserId = $this->option('user');

        $this->info("Starting daily summaries generation for date: {$targetDate} ({$timezone})");

        // Determine users to process
        if (!empty($targetUserId)) {
            $user = User::find($targetUserId);
            if (!$user) {
                $this->error("User not found with ID: {$targetUserId}");
                return Command::FAILURE;
            }
            $users = collect([$user]);
        } else {
            // Find users who had activity on the target date
            $start = Carbon::parse($targetDate, $timezone)->startOfDay();
            $end   = Carbon::parse($targetDate, $timezone)->endOfDay();
            $startUtc = new \MongoDB\BSON\UTCDateTime($start->getTimestampMs());
            $endUtc   = new \MongoDB\BSON\UTCDateTime($end->getTimestampMs());

            $userIds = AssistantMessage::where('created_at', '>=', $startUtc)
                ->where('created_at', '<=', $endUtc)
                ->pluck('user_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

            if (empty($userIds)) {
                $this->info("No conversation activity found for date: {$targetDate}");
                return Command::SUCCESS;
            }

            $users = User::whereIn('_id', $userIds)->get();
        }

        $processedCount = 0;
        foreach ($users as $user) {
            $userId = (string) ($user->_id ?? $user->id);
            try {
                $summary = $summaryService->generateSummaryForUserAndDate($user, $targetDate);
                if ($summary) {
                    $this->line("✔ Summary generated for [{$user->name}] ({$userId}): {$summary->summary}");
                    $processedCount++;
                } else {
                    $this->line("- No valid dialogue turns to summarize for [{$user->name}] ({$userId})");
                }
            } catch (\Throwable $e) {
                $this->error("Failed to summarize user {$userId}: " . $e->getMessage());
                Log::error("GenerateDailySummariesCommand error for user {$userId}: " . $e->getMessage());
            }
        }

        $this->info("Completed daily summaries generation. Total processed: {$processedCount}");
        return Command::SUCCESS;
    }
}
