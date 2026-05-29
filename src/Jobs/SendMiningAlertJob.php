<?php

namespace DiscordPings\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DiscordPings\Helpers\DiscordHelper;
use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\TacticalEvent;

/**
 * Delivers a single pre-expiry mining-extraction alert to one webhook.
 * Dispatched by MiningExtractionHandler when a mining.extraction_unstable
 * event arrives, once per opted-in webhook.
 *
 * $tries = 1: the handler's pinged_1h_at latch already guarantees one
 * dispatch per extraction, and a retried HTTP POST could double-post to
 * Discord.
 */
class SendMiningAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public $tries = 1;

    public function __construct(
        public int $webhookId,
        public int $tacticalEventId,
    ) {
    }

    public function handle(): void
    {
        $webhook = DiscordWebhook::find($this->webhookId);
        if (! $webhook || ! $webhook->is_active || ! $webhook->receives_mining_alerts) {
            return;
        }

        $event = TacticalEvent::find($this->tacticalEventId);
        if (! $event) {
            return;
        }

        (new DiscordHelper())->sendMiningExtractionAlert($webhook, $event);
    }
}
