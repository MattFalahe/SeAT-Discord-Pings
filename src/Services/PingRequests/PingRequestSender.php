<?php

namespace DiscordPings\Services\PingRequests;

use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PingRequestButton;
use DiscordPings\Services\DiscordUserResolver;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Posts a member's button press to Discord.
 *
 * This stays clear of DiscordHelper's sendPing on purpose. That one publishes
 * pings.broadcast.sent and HR Manager counts those to work out who is an FC,
 * so a line member asking for help three times would end up with the FC badge.
 *
 * It posts inside the request with wait=true, because the member is told the
 * message got there and without wait=true Discord doesn't report a failure.
 */
class PingRequestSender
{
    private const TIMEOUT = 8;
    private const CONNECT_TIMEOUT = 3;

    /** Longest Discord's own rate limit is waited out in the request before giving up, in seconds. */
    private const LONGEST_WAIT = 3.0;

    /**
     * @param array<string, mixed> $answers
     * @return array{ok: bool, status: string, message: string, retry_in: ?int}
     */
    public function press(PingRequestButton $button, array $answers, $user, ?string $savedForm = null): array
    {
        $webhook = $button->webhook;

        if (! $webhook || ! $webhook->is_active) {
            Log::warning('[DiscordPings] Ping request button has no working webhook', ['button_id' => $button->id]);

            return $this->outcome(false, 'not_configured', 'Not delivered. This button has no working webhook, so let your admins know.');
        }

        $body = StarterText::compose($button->starter_text, $answers);
        $sender = DiscordUserResolver::senderIdentity($user);

        // Only request buttons go through this. An emergency skips the
        // cooldown and the plugin's own counters, so the only thing that can
        // hold one up is Discord itself.
        if (! $button->isEmergency() && ($held = $this->holdBack($button, $webhook, $user))) {
            $this->record($button, $webhook, $user, $sender, $body, $savedForm, $held['status'], $held['detail']);

            return $this->outcome(false, $held['status'], $held['message'], $held['retry_in']);
        }

        $delivery = $this->post($webhook, RequestMessage::payload(
            $button->name,
            $body,
            $button->mention_role_id,
            $sender,
            $this->color($button, $webhook),
            (string) config('discordpings.app_name', 'SeAT Broadcast'),
            gmdate('Y-m-d\TH:i:s\Z')
        ));

        $this->record($button, $webhook, $user, $sender, $body, $savedForm, $delivery['ok'] ? 'sent' : 'failed', $delivery['error'], $delivery['message_id']);

        if ($delivery['ok']) {
            return $this->outcome(true, 'sent', 'Sent to Discord.', $button->isEmergency() ? null : (int) $button->cooldown_minutes * 60);
        }

        // Nothing reached Discord, so the member shouldn't have to sit out a
        // cooldown before trying again.
        Cache::forget($this->cooldownKey($button, $user));

        if ($delivery['retry_in'] !== null) {
            return $this->outcome(false, 'failed', 'Not delivered. Discord is limiting this channel right now, try again in ' . Cooldown::describe($delivery['retry_in']) . '.', $delivery['retry_in']);
        }

        return $this->outcome(false, 'failed', 'Not delivered. Nothing reached Discord, so press again or tell someone directly.');
    }

    /** How long this member has to wait before this button works again. */
    public function secondsUntilReady(PingRequestButton $button, $user): int
    {
        if ($button->isEmergency()) {
            return 0;
        }

        return Cooldown::secondsLeft(false, (int) $button->cooldown_minutes, $this->lastPressedAt($button, $user), time());
    }

    /**
     * Separate counters from the FC pings, or members pressing buttons on a
     * shared webhook could use up what the FCs need.
     *
     * @return array{status: string, message: string, detail: string, retry_in: int}|null
     */
    private function holdBack(PingRequestButton $button, DiscordWebhook $webhook, $user): ?array
    {
        $minutes = (int) $button->cooldown_minutes;
        $key = $this->cooldownKey($button, $user);

        if ($minutes > 0 && ! Cache::add($key, time(), $minutes * 60)) {
            $left = Cooldown::secondsLeft(false, $minutes, $this->lastPressedAt($button, $user), time());

            if ($left > 0) {
                $when = Cooldown::describe($left);

                return [
                    'status' => 'blocked',
                    'message' => 'Not sent. You can press this again in ' . $when . '.',
                    'detail' => 'Cooldown, could go again in ' . $when,
                    'retry_in' => $left,
                ];
            }
        }

        if (! config('discordpings.rate_limit.enabled', true)) {
            return null;
        }

        $limits = [
            'discordpings:requests:min:' . $webhook->id => [(int) config('discordpings.rate_limit.requests_per_minute', 10), 60],
            'discordpings:requests:hour:' . $webhook->id => [(int) config('discordpings.rate_limit.requests_per_hour', 100), 3600],
        ];

        foreach ($limits as $counter => [$max]) {
            if (RateLimiter::tooManyAttempts($counter, $max)) {
                $left = (int) RateLimiter::availableIn($counter);
                $when = Cooldown::describe($left);

                // It never went out, so it doesn't count towards the member's cooldown.
                Cache::forget($key);

                return [
                    'status' => 'rate_limited',
                    'message' => 'Not sent. Too many requests are going to this channel, try again in ' . $when . '.',
                    'detail' => 'Member request limit reached on this webhook, could go again in ' . $when,
                    'retry_in' => $left,
                ];
            }
        }

        foreach ($limits as $counter => [, $window]) {
            RateLimiter::hit($counter, $window);
        }

        return null;
    }

    /**
     * @return array{ok: bool, error: ?string, message_id: ?string, retry_in: ?int}
     */
    private function post(DiscordWebhook $webhook, array $payload): array
    {
        $url = $webhook->webhook_url;
        $url .= (str_contains($url, '?') ? '&' : '?') . 'wait=true';

        try {
            $client = new Client([
                'timeout' => self::TIMEOUT,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'http_errors' => false,
            ]);

            $response = $client->post($url, ['json' => $payload]);

            if ($response->getStatusCode() === 429) {
                $wait = $this->retryAfter($response);

                if ($wait > 0 && $wait <= self::LONGEST_WAIT) {
                    usleep((int) ceil($wait * 1000000));
                    $response = $client->post($url, ['json' => $payload]);
                }
            }

            $status = $response->getStatusCode();
            $json = json_decode((string) $response->getBody(), true);

            if ($status >= 200 && $status < 300) {
                return ['ok' => true, 'error' => null, 'message_id' => isset($json['id']) ? (string) $json['id'] : null, 'retry_in' => null];
            }

            $error = 'Discord answered ' . $status . (is_string($json['message'] ?? null) ? ': ' . $json['message'] : '');

            return [
                'ok' => false,
                'error' => ErrorText::safe($error),
                'message_id' => null,
                'retry_in' => $status === 429 ? max(1, (int) ceil($this->retryAfter($response))) : null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => ErrorText::safe($e->getMessage()), 'message_id' => null, 'retry_in' => null];
        }
    }

    private function retryAfter($response): float
    {
        $header = $response->getHeader('Retry-After')[0] ?? null;
        if (is_numeric($header)) {
            return (float) $header;
        }

        $json = json_decode((string) $response->getBody(), true);

        return is_numeric($json['retry_after'] ?? null) ? (float) $json['retry_after'] : 0.0;
    }

    /** Written after the post, and never allowed to get in its way. */
    private function record(PingRequestButton $button, DiscordWebhook $webhook, $user, SenderIdentity $sender, string $body, ?string $savedForm, string $status, ?string $error, ?string $messageId = null): void
    {
        try {
            PingHistory::create([
                'webhook_id' => $webhook->id,
                'user_id' => $user->id,
                'user_name' => $sender->mainName,
                'message' => $body !== '' ? $body : $button->name,
                'fields' => array_filter([
                    'source' => PingHistory::SOURCE_MEMBER_REQUEST,
                    'button_id' => $button->id,
                    'button' => $button->name,
                    'type' => $button->type,
                    'saved_form' => $savedForm,
                    'main' => $sender->mainName,
                    'corporation' => $sender->corporation,
                    'discord' => $sender->discordLine(),
                ], fn ($value) => $value !== null && $value !== ''),
                'status' => $status,
                'error_message' => $error,
                'discord_message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[DiscordPings] Could not record a ping request: ' . ErrorText::safe($e->getMessage()), ['button_id' => $button->id]);
        }
    }

    private function color(PingRequestButton $button, DiscordWebhook $webhook): int
    {
        if ($button->isEmergency()) {
            return 0xDC3545;
        }

        $hex = ltrim((string) $webhook->embed_color, '#');

        return preg_match('/^[0-9a-f]{6}$/i', $hex) ? (int) hexdec($hex) : 0x5865F2;
    }

    private function lastPressedAt(PingRequestButton $button, $user): ?int
    {
        $at = Cache::get($this->cooldownKey($button, $user));

        return is_numeric($at) ? (int) $at : null;
    }

    private function cooldownKey(PingRequestButton $button, $user): string
    {
        return 'discordpings:requests:pressed:' . $button->id . ':' . $user->id;
    }

    /** @return array{ok: bool, status: string, message: string, retry_in: ?int} */
    private function outcome(bool $ok, string $status, string $message, ?int $retryIn = null): array
    {
        return ['ok' => $ok, 'status' => $status, 'message' => $message, 'retry_in' => $retryIn];
    }
}
