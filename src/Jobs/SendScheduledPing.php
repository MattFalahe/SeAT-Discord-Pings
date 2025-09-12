<?php

namespace MattFalahe\Seat\DiscordPings\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use MattFalahe\Seat\DiscordPings\Models\ScheduledPing;
use MattFalahe\Seat\DiscordPings\Helpers\DiscordHelper;
use Carbon\Carbon;

class SendScheduledPing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job
     */
    public function handle()
    {
        $pings = ScheduledPing::due()->with('webhook')->get();
        $helper = new DiscordHelper();

        foreach ($pings as $ping) {
            // Prepare data
            $data = array_merge([
                'message' => $ping->message,
                'webhook_id' => $ping->webhook_id,
            ], $ping->fields ?? []);

            // Create user object
            $user = (object) [
                'id' => $ping->user_id,
                'name' => 'Scheduled Ping'
            ];

            // Send ping
            $result = $helper->sendPing($ping->webhook, $data, $user);

            // Update scheduled ping
            $ping->last_sent_at = Carbon::now();
            $ping->times_sent++;

            // Handle repeat logic
            if ($ping->repeat_interval) {
                $nextRun = $ping->calculateNextRun();
                
                if ($nextRun && (!$ping->repeat_until || $nextRun <= $ping->repeat_until)) {
                    $ping->scheduled_at = $nextRun;
                } else {
                    $ping->is_active = false;
                }
            } else {
                // One-time ping
                $ping->is_active = false;
            }

            $ping->save();
        }
    }
}
