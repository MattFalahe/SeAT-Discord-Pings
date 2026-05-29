<?php

namespace DiscordPings\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DiscordPings\Helpers\DiscordHelper;
use DiscordPings\Models\DiscordWebhook;

class SendPingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public $tries = 1;

    public function __construct(
        public int $webhookId,
        public array $data,
        public int $userId,
        public string $userName,
    ) {
    }

    public function handle(): void
    {
        $webhook = DiscordWebhook::find($this->webhookId);
        if (! $webhook || ! $webhook->is_active) {
            return;
        }

        $user = (object) ['id' => $this->userId, 'name' => $this->userName];

        (new DiscordHelper())->sendPing($webhook, $this->data, $user);
    }
}
