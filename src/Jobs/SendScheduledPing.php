<?php

namespace DiscordPings\Jobs;

use Carbon\Carbon;
use DiscordPings\Helpers\DiscordHelper;
use DiscordPings\Models\ScheduledPing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledPing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 1;

    /**
     * If a scheduled ping comes back rate-limited, we retry it on each
     * cron tick (no state change) up to this many minutes past its
     * scheduled_at. Beyond that we give up on this occurrence (one-time
     * pings deactivate, recurring pings advance to the next run) so a
     * misconfigured rate limit cannot lock a ping into an endless retry
     * loop. 15 minutes = 15 cron retries with the default 1-minute tick.
     */
    const RATE_LIMIT_RETRY_WINDOW_MINUTES = 15;

    /**
     * Execute the job.
     *
     * Outcomes per ping (driven by DiscordHelper::sendPing's 'status'
     * field: 'sent' / 'rate_limited' / 'failed'):
     *
     *   sent          - advance the schedule (recurring) or deactivate
     *                   (one-time). times_sent++, last_sent_at = now.
     *   rate_limited  - transient; leave state unchanged and try again on
     *                   the next cron tick. Past RATE_LIMIT_RETRY_WINDOW
     *                   minutes we treat it as a hard failure and advance
     *                   so the schedule cannot get stuck.
     *   failed        - log a warning and advance the schedule (recurring)
     *                   or deactivate (one-time). The failure is already
     *                   recorded in PingHistory; the operator can resend
     *                   from the History page.
     */
    public function handle()
    {
        $pings  = ScheduledPing::due()->with('webhook')->get();
        $helper = new DiscordHelper();
        $now    = Carbon::now();

        foreach ($pings as $ping) {
            $data = array_merge([
                'message'    => $ping->message,
                'webhook_id' => $ping->webhook_id,
                // Marker for the EventBus publisher so subscribers can
                // distinguish a scheduled-firing from a manual broadcast.
                '_is_scheduled' => true,
            ], $ping->fields ?? []);

            $user = (object) [
                'id'   => $ping->user_id,
                'name' => 'Scheduled Ping',
            ];

            $result = $helper->sendPing($ping->webhook, $data, $user);
            // Back-compat: if a future helper or older path omits 'status',
            // derive it from 'success'. New code always sets it.
            $status = $result['status']
                ?? (($result['success'] ?? false) ? 'sent' : 'failed');

            if ($status === 'rate_limited') {
                $stuck = $ping->scheduled_at->lt(
                    $now->copy()->subMinutes(self::RATE_LIMIT_RETRY_WINDOW_MINUTES)
                );
                if (! $stuck) {
                    // Try again on the next cron tick. No state change so
                    // the due() scope still picks this up next minute.
                    continue;
                }
                Log::warning(
                    'Discord Pings: scheduled ping rate-limited past retry window, advancing',
                    [
                        'scheduled_ping_id' => $ping->id,
                        'webhook_id'        => $ping->webhook_id,
                        'scheduled_at'      => (string) $ping->scheduled_at,
                    ]
                );
                // Fall through to the advance logic below.
            } elseif ($status === 'failed') {
                Log::warning(
                    'Discord Pings: scheduled ping send failed, advancing schedule',
                    [
                        'scheduled_ping_id' => $ping->id,
                        'webhook_id'        => $ping->webhook_id,
                        'error'             => $result['error'] ?? 'unknown',
                    ]
                );
                // Fall through to advance — the failure is in PingHistory;
                // we do not loop on hard failures.
            }

            $ping->last_sent_at = $now;
            $ping->times_sent++;

            if ($ping->repeat_interval) {
                $nextRun = $ping->calculateNextRun();
                if ($nextRun && (! $ping->repeat_until || $nextRun <= $ping->repeat_until)) {
                    $ping->scheduled_at = $nextRun;
                } else {
                    $ping->is_active = false;
                }
            } else {
                // One-time ping — done (success or hard failure).
                $ping->is_active = false;
            }

            $ping->save();
        }
    }
}
