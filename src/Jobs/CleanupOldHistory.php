<?php

namespace MattFalahe\Seat\DiscordPings\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use MattFalahe\Seat\DiscordPings\Models\PingHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanupOldHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    }
}
