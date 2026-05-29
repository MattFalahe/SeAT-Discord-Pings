<?php

namespace DiscordPings\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use DiscordPings\Models\PingHistory;
use DiscordPings\Models\TacticalEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanupOldHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    /**
     * Execute the job
     */
    public function handle()
    {
        $retentionDays = config('discordpings.history_retention_days', 90);
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        
        try {
            $deletedCount = PingHistory::where('created_at', '<', $cutoffDate)->delete();

            Log::info('Discord Pings: Cleaned up ' . $deletedCount . ' old history records.');
        } catch (\Exception $e) {
            Log::error('Discord Pings: Failed to cleanup history', [
                'error' => $e->getMessage()
            ]);

            throw $e; // Re-throw to mark job as failed
        }

        // Prune resolved tactical events (structure timers ingested from
        // Manager Core's EventBus). Best-effort — never fails the job.
        try {
            $timerRetentionDays = (int) config('discordpings.structure_events.retention_days', 14);
            $timerCutoff = Carbon::now()->subDays(max(1, $timerRetentionDays));

            $prunedTimers = TacticalEvent::whereIn('status', ['dismissed', 'elapsed'])
                ->where('updated_at', '<', $timerCutoff)
                ->delete();

            Log::info('Discord Pings: Cleaned up ' . $prunedTimers . ' resolved tactical event records.');
        } catch (\Throwable $e) {
            Log::warning('Discord Pings: Failed to cleanup tactical events', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
