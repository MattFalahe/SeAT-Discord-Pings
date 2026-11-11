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

    /** How long after an emergency goes out its sender can still add to it, in seconds. */
    private const DETAILS_WINDOW = 600;

    /** Far more than one message needs, even with every section filled in after the press, so a page left open can't keep editing it. */
    private const MOST_DETAIL_UPDATES = 50;

    /**
     * @param array<string, mixed> $answers
     * @param string|null $savedForm the name of the saved form it was sent with, null for none
     * @return array{ok: bool, status: string, message: string, retry_in: ?int, active?: ?array}
     */
    public function press(PingRequestButton $button, array $answers, $user, ?string $savedForm = null): array
    {
        $webhook = $button->webhook;

        if (! $webhook || ! $webhook->is_active) {
            Log::warning('[DiscordPings] Ping request button has no working webhook', ['button_id' => $button->id]);

            return $this->outcome(false, 'not_configured', 'Not delivered. This button has no working webhook, so let your admins know.');
        }

        $rows = $button->sectionRows();
        $body = Sections::compose($rows, $answers);
        $sender = DiscordUserResolver::senderIdentity($user);

        // Only request buttons go through this. An emergency skips the
        // cooldown and the plugin's own counters, so the only thing that can
        // hold one up is Discord itself.
        if (! $button->isEmergency() && ($held = $this->holdBack($button, $webhook, $user))) {
            $this->record($button, $webhook, $user, $sender, $body, $savedForm, $held['status'], $held['detail']);

            return $this->outcome(false, $held['status'], $held['message'], $held['retry_in']);
        }

        // Asked only once the message is out, so the questions can never hold an emergency up.
        // A request already asked everything in its confirm box.
        $open = $button->isEmergency() ? $this->openAfter($rows, $savedForm) : [];
        $sentAt = time();
        $closesAt = $open !== [] ? $sentAt + self::DETAILS_WINDOW : null;

        $payload = RequestMessage::payload(
            $button->name,
            Sections::forDiscord($rows, $answers),
            $button->mention_role_id,
            $sender,
            $this->color($button, $webhook),
            (string) config('discordpings.app_name', 'SeAT Broadcast'),
            gmdate('Y-m-d\TH:i:s\Z', $sentAt),
            $closesAt
        );

        $delivery = $this->post($webhook, $payload);

        $historyId = $this->record($button, $webhook, $user, $sender, $body, $savedForm, $delivery['ok'] ? 'sent' : 'failed', $delivery['error'], $delivery['message_id']);

        if ($delivery['ok']) {
            $outcome = $this->outcome(true, 'sent', 'Sent to Discord.', $button->isEmergency() ? null : (int) $button->cooldown_minutes * 60);

            if ($closesAt !== null) {
                $outcome['active'] = $this->keepOpen($button, $webhook, $user, $delivery['message_id'], $historyId, $rows, $answers, $open, $savedForm, $payload['embeds'][0], $sentAt, $closesAt);

                if ($outcome['active'] !== null) {
                    $outcome['message'] = 'Sent to Discord. Add what you know under Active requests.';
                }
            }

            return $outcome;
        }

        // Nothing reached Discord, so the member shouldn't have to sit out a
        // cooldown before trying again.
        Cache::forget($this->cooldownKey($button, $user));

        if ($delivery['retry_in'] !== null) {
            return $this->outcome(false, 'failed', 'Not delivered. Discord is limiting this channel right now, try again in ' . Cooldown::describe($delivery['retry_in']) . '.', $delivery['retry_in']);
        }

        return $this->outcome(false, 'failed', 'Not delivered. Nothing reached Discord, so press again or tell someone directly.');
    }

    /**
     * Rebuilds an emergency's message with what its sender knows now and
     * edits it on Discord, in place of posting another one.
     *
     * @param array<string, mixed> $answers every after-the-press answer as it stands, blanks included
     * @return array{ok: bool, status: string, message: string, retry_in: ?int}
     */
    public function addDetails(string $token, array $answers, $user): array
    {
        $press = ctype_xdigit($token) ? Cache::get($this->detailsKey($token)) : null;

        if (! $this->isOpenFor($press, $user)) {
            return $this->outcome(false, 'expired', 'Not updated. This request has closed, so press its button again if you need to.');
        }

        // Not the key the details sit under: the rate limiter keeps its count in the same cache.
        $counter = 'discordpings:requests:detail-updates:' . $token;

        if (RateLimiter::tooManyAttempts($counter, self::MOST_DETAIL_UPDATES)) {
            return $this->outcome(false, 'rate_limited', 'Not updated. This message has been changed as often as it can be, so tell someone directly.');
        }

        RateLimiter::hit($counter, self::DETAILS_WINDOW);

        $webhook = DiscordWebhook::find($press['webhook_id']);

        if (! $webhook || ! $webhook->is_active) {
            return $this->outcome(false, 'not_configured', 'Not updated. The webhook this went out on is gone or switched off.');
        }

        $rows = $press['rows'];
        $answers = array_merge($press['answers'], array_intersect_key($answers, array_flip($press['open'])));
        $embed = RequestMessage::withBody(RequestMessage::updated($press['embed'], time()), Sections::forDiscord($rows, $answers));
        $delivery = $this->edit($webhook, $press['message_id'], $embed);

        if (! $delivery['ok']) {
            Log::warning('[DiscordPings] Could not update a ping request on Discord: ' . $delivery['error'], ['history_id' => $press['history_id'] ?? null]);

            // The message or its webhook is gone on Discord, so every later try would fail the same way.
            if ($delivery['http_status'] === 404) {
                Cache::forget($this->detailsKey($token));

                return $this->outcome(false, 'expired', "Not updated. Discord can't find this message any more, so tell someone directly.");
            }

            if ($delivery['retry_in'] !== null) {
                return $this->outcome(false, 'failed', 'Not updated. Discord is limiting this channel right now, try again in ' . Cooldown::describe($delivery['retry_in']) . '.', $delivery['retry_in']);
            }

            return $this->outcome(false, 'failed', 'Not updated. Your message is still on Discord as it was, so try again or tell someone directly.');
        }

        $this->keepUpdate($token, $answers, $embed);

        $body = Sections::compose($rows, $answers);
        $this->followInHistory($press['history_id'] ?? null, $body !== '' ? $body : (string) ($press['embed']['title'] ?? ''));

        return $this->outcome(true, 'updated', 'Added to your message on Discord.');
    }

    /**
     * The member is done with it early. Their message on Discord says so,
     * and nothing more can be added to it from any page.
     *
     * @return array{ok: bool, status: string, message: string, retry_in: ?int}
     */
    public function close(string $token, $user): array
    {
        $press = ctype_xdigit($token) ? Cache::get($this->detailsKey($token)) : null;

        // Closed already, run out, or never theirs: either way there's nothing left to close.
        if (! $this->isOpenFor($press, $user)) {
            return $this->outcome(true, 'closed', 'Closed.');
        }

        Cache::forget($this->detailsKey($token));

        try {
            $this->changeOpenList($user, function (array $open) use ($token) {
                unset($open[$token]);

                return $open;
            });
        } catch (\Throwable $e) {
            // The list only says where to look, and with the details gone there's nothing to find there.
        }

        $webhook = DiscordWebhook::find($press['webhook_id']);
        $embed = RequestMessage::withBody(RequestMessage::closed($press['embed'], time()), Sections::forDiscord($press['rows'], $press['answers']));
        $delivery = $webhook && $webhook->is_active
            ? $this->edit($webhook, $press['message_id'], $embed)
            : ['ok' => false, 'error' => 'The webhook is gone or switched off'];

        if (! $delivery['ok']) {
            Log::warning('[DiscordPings] Could not mark a ping request closed on Discord: ' . $delivery['error'], ['history_id' => $press['history_id'] ?? null]);

            return $this->outcome(true, 'closed_here', 'Closed here, but Discord could not be updated, so your message there still shows when it would have closed.');
        }

        return $this->outcome(true, 'closed', 'Closed. Your message on Discord now says so.');
    }

    /**
     * The member's emergencies that can still be added to, newest first, so
     * the page can show them again after a reload or in another tab.
     *
     * @return array<int, array>
     */
    public function openFor($user): array
    {
        $found = [];

        try {
            $list = Cache::get($this->openListKey($user));

            // Read backwards, so two presses in the same second still sort newest first.
            foreach (is_array($list) ? array_reverse($list, true) : [] as $token => $closesAt) {
                $token = (string) $token;
                $press = ctype_xdigit($token) ? Cache::get($this->detailsKey($token)) : null;

                if ($this->isOpenFor($press, $user)) {
                    $found[] = [$token, $press];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] Could not read the open ping requests: ' . ErrorText::safe($e->getMessage()));

            return [];
        }

        usort($found, fn ($a, $b) => (int) $b[1]['sent_at'] <=> (int) $a[1]['sent_at']);

        return array_map(fn ($pair) => $this->entry($pair[0], $pair[1]), $found);
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
     * @return array{ok: bool, error: ?string, message_id: ?string, retry_in: ?int, http_status: ?int}
     */
    private function post(DiscordWebhook $webhook, array $payload): array
    {
        $url = $webhook->webhook_url;
        $url .= (str_contains($url, '?') ? '&' : '?') . 'wait=true';

        return $this->deliver('post', $url, $payload);
    }

    /**
     * Only the embed goes, so the role in the content is left as it was and
     * Discord doesn't ping anybody again.
     *
     * @return array{ok: bool, error: ?string, message_id: ?string, retry_in: ?int, http_status: ?int}
     */
    private function edit(DiscordWebhook $webhook, string $messageId, array $embed): array
    {
        [$address, $query] = array_pad(explode('?', (string) $webhook->webhook_url, 2), 2, '');

        // A webhook that posts into a thread has to name the thread for an edit too.
        $url = rtrim($address, '/') . '/messages/' . rawurlencode($messageId) . ($query !== '' ? '?' . $query : '');

        return $this->deliver('patch', $url, ['embeds' => [$embed]]);
    }

    /**
     * @return array{ok: bool, error: ?string, message_id: ?string, retry_in: ?int, http_status: ?int}
     */
    private function deliver(string $verb, string $url, array $payload): array
    {
        try {
            $client = new Client([
                'timeout' => self::TIMEOUT,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'http_errors' => false,
            ]);

            $response = $client->$verb($url, ['json' => $payload]);

            if ($response->getStatusCode() === 429) {
                $wait = $this->retryAfter($response);

                if ($wait > 0 && $wait <= self::LONGEST_WAIT) {
                    usleep((int) ceil($wait * 1000000));
                    $response = $client->$verb($url, ['json' => $payload]);
                }
            }

            $status = $response->getStatusCode();
            $json = json_decode((string) $response->getBody(), true);

            if ($status >= 200 && $status < 300) {
                return ['ok' => true, 'error' => null, 'message_id' => isset($json['id']) ? (string) $json['id'] : null, 'retry_in' => null, 'http_status' => $status];
            }

            $error = 'Discord answered ' . $status . (is_string($json['message'] ?? null) ? ': ' . $json['message'] : '');

            return [
                'ok' => false,
                'error' => ErrorText::safe($error),
                'message_id' => null,
                'retry_in' => $status === 429 ? max(1, (int) ceil($this->retryAfter($response))) : null,
                'http_status' => $status,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => ErrorText::safe($e->getMessage()), 'message_id' => null, 'retry_in' => null, 'http_status' => null];
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

    /**
     * What can still be filled in once an emergency is out. Sent from a saved
     * form, that's what the button asks after the press. Sent with no form,
     * nothing has been said yet, so it's every section.
     *
     * @return string[]
     */
    private function openAfter(array $rows, ?string $savedForm): array
    {
        if ($savedForm === null) {
            return array_merge(Sections::savedKeys($rows), Sections::liveKeys($rows));
        }

        return Sections::liveKeys($rows);
    }

    /**
     * What the member needs to add to the message later, kept until it
     * closes and listed under their account, so a reload or another tab finds
     * it again. Written after the post like the history row, and just as
     * unable to undo it.
     *
     * @return array|null the entry as the page shows it
     */
    private function keepOpen(PingRequestButton $button, DiscordWebhook $webhook, $user, ?string $messageId, ?int $historyId, array $rows, array $answers, array $open, ?string $savedForm, array $embed, int $sentAt, int $closesAt): ?array
    {
        if ($messageId === null) {
            return null;
        }

        try {
            $token = bin2hex(random_bytes(20));
            $press = [
                'user_id' => (int) $user->id,
                'webhook_id' => (int) $webhook->id,
                'message_id' => $messageId,
                'history_id' => $historyId,
                'button' => (string) $button->name,
                'saved_form' => $savedForm,
                'rows' => $rows,
                'open' => $open,
                // Only what the button asks. A press can carry any keys at all.
                'answers' => array_intersect_key($answers, array_flip(array_merge(Sections::savedKeys($rows), Sections::liveKeys($rows)))),
                'embed' => $embed,
                'sent_at' => $sentAt,
                'closes_at' => $closesAt,
            ];

            Cache::put($this->detailsKey($token), $press, max(1, $closesAt - time()));
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] Could not keep a ping request open for details: ' . ErrorText::safe($e->getMessage()));

            return null;
        }

        try {
            $this->changeOpenList($user, function (array $list) use ($token, $closesAt) {
                $list[$token] = $closesAt;

                return $list;
            });
        } catch (\Throwable $e) {
            // The page that pressed still has it. Only a reload won't find it.
            Log::warning('[DiscordPings] Could not list a ping request as open: ' . ErrorText::safe($e->getMessage()));
        }

        return $this->entry($token, $press);
    }

    /**
     * One change at a time, so two presses landing together can't each write
     * the list back without the other. Anything that has closed is dropped on
     * the way through.
     */
    private function changeOpenList($user, callable $change): void
    {
        $key = $this->openListKey($user);

        Cache::lock($key . ':lock', 10)->block(3, function () use ($key, $change) {
            $now = time();
            $list = Cache::get($key);
            $list = $change(array_filter(is_array($list) ? $list : [], fn ($closesAt) => is_int($closesAt) && $closesAt > $now));

            if ($list === []) {
                Cache::forget($key);
            } else {
                Cache::put($key, $list, max(1, max($list) - $now));
            }
        });
    }

    /** One open emergency the way the page shows it: what it still asks, and the answers as they stand. */
    private function entry(string $token, array $press): array
    {
        $freeText = ! Sections::hasQuestions($press['rows']);

        return [
            'token' => $token,
            'button' => $press['button'],
            'savedForm' => $press['saved_form'],
            'sentAt' => gmdate('Y-m-d\TH:i:s\Z', (int) $press['sent_at']),
            'closesIn' => max(0, (int) $press['closes_at'] - time()),
            'freeText' => $freeText,
            'lines' => $freeText ? [] : array_values(array_filter(
                Sections::lines($press['rows']),
                fn ($line) => in_array($line['key'], $press['open'], true)
            )),
            'answers' => (object) array_intersect_key($press['answers'], array_flip($press['open'])),
        ];
    }

    /**
     * So a reload shows the answers as they stand, and a close keeps the time
     * of the last update Discord took. The ten minutes don't start again.
     */
    private function keepUpdate(string $token, array $answers, array $embed): void
    {
        try {
            $press = Cache::get($this->detailsKey($token));

            // Closed while the edit was on its way, so it stays closed.
            if (! is_array($press) || (int) ($press['closes_at'] ?? 0) <= time()) {
                return;
            }

            $press['answers'] = $answers;
            $press['embed'] = $embed;
            Cache::put($this->detailsKey($token), $press, (int) $press['closes_at'] - time());
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] Could not keep the answers to a ping request: ' . ErrorText::safe($e->getMessage()));
        }
    }

    private function isOpenFor($press, $user): bool
    {
        return is_array($press)
            && (int) ($press['user_id'] ?? 0) === (int) $user->id
            && (int) ($press['closes_at'] ?? 0) > time();
    }

    private function openListKey($user): string
    {
        return 'discordpings:requests:open:' . (int) $user->id;
    }

    /** History shows what Discord shows. */
    private function followInHistory(?int $historyId, string $message): void
    {
        if ($historyId === null) {
            return;
        }

        try {
            PingHistory::find($historyId)?->update(['message' => $message]);
        } catch (\Throwable $e) {
            Log::error('[DiscordPings] Could not update a ping request in history: ' . ErrorText::safe($e->getMessage()), ['history_id' => $historyId]);
        }
    }

    private function detailsKey(string $token): string
    {
        return 'discordpings:requests:details:' . $token;
    }

    /** Written after the post, and never allowed to get in its way. */
    private function record(PingRequestButton $button, DiscordWebhook $webhook, $user, SenderIdentity $sender, string $body, ?string $savedForm, string $status, ?string $error, ?string $messageId = null): ?int
    {
        try {
            $history = PingHistory::create([
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

            return $history->id !== null ? (int) $history->id : null;
        } catch (\Throwable $e) {
            Log::error('[DiscordPings] Could not record a ping request: ' . ErrorText::safe($e->getMessage()), ['button_id' => $button->id]);

            return null;
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
