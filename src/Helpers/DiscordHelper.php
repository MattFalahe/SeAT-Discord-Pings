<?php

namespace DiscordPings\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use DiscordPings\Models\PingHistory;
use DiscordPings\Services\BroadcastEventPublisher;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class DiscordHelper
{
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 10,
        ]);
    }

    /**
     * Send a ping to Discord
     */
    public function sendPing($webhook, $data, $user)
    {
        if ($limited = $this->checkRateLimit($webhook, $user, $data)) {
            return $limited;
        }

        try {
            // Build the payload
            $payload = $this->buildPayload($data, $user, $webhook);

            // Send to Discord
            $response = $this->client->post($webhook->webhook_url, [
                'json' => $payload
            ]);

            // Log to history
            $history = PingHistory::create([
                'webhook_id' => $webhook->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'message' => $data['message'],
                'fields' => $this->extractFields($data),
                'webhook_response' => json_decode($response->getBody(), true),
                'status' => 'sent',
                'discord_message_id' => $response->getHeader('X-Message-Id')[0] ?? null,
            ]);

            // Publish to Manager Core EventBus (no-op without MC) so subscribers
            // like HR Manager can track active FC broadcast activity.
            $this->publishBroadcastSentEvent($webhook, $user, $data, $history);

            return ['success' => true, 'status' => 'sent', 'history_id' => $history->id];

        } catch (RequestException $e) {
            Log::error('Discord ping send error', [
                'webhook_id' => $webhook->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            // Log failure
            PingHistory::create([
                'webhook_id' => $webhook->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'message' => $data['message'],
                'fields' => $this->extractFields($data),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Build Discord payload
     */
    private function buildPayload($data, $user, $webhook)
    {
        $payload = [];

        // Add mention if needed
        if (isset($data['mention_type']) && $data['mention_type'] !== 'none') {
            switch ($data['mention_type']) {
                case 'everyone':
                    $payload['content'] = '@everyone';
                    break;
                case 'here':
                    $payload['content'] = '@here';
                    break;
                case 'role':
                case 'custom':
                    $payload['content'] = $data['custom_mention'] ?? '';
                    break;
            }
        }

        // Use the provided embed color, or fall back to webhook default
        $embedColor = $data['embed_color'] ?? $webhook->embed_color ?? '#5865F2';
        
        // Remove # from color for Discord
        $embedColor = str_replace('#', '', $embedColor);

        // Determine embed title and icon based on embed type
        $embedType = $data['embed_type'] ?? 'fleet';
        switch ($embedType) {
            case 'announcement':
                $embedTitle = '📣 Announcement';
                break;
            case 'message':
                $embedTitle = '💬 Message';
                break;
            case 'prepping':
                $embedTitle = '‼️ PREPING ‼️';
                break;
            case 'fleet':
            default:
                $embedTitle = '📢 Fleet Broadcast';
                break;
        }

        // Build embed
        $embed = [
            'title' => $embedTitle,
            'description' => $data['message'],
            'color' => hexdec($embedColor),
            'fields' => [],
            'footer' => [
                'text' => sprintf(
                    'This was a broadcast from %s to discord at %s EVE',
                    $user->name,
                    Carbon::now()->utc()->format('Y-m-d H:i:s.u')
                ),
            ],
            'timestamp' => Carbon::now()->utc()->toIso8601String()
        ];

        // Add optional fields
        $fieldMappings = [
            'fc_name' => ['name' => '👤 FC Name', 'inline' => true],
            'formup_location' => ['name' => '📍 Formup Location', 'inline' => true],
            'pap_type' => ['name' => '🎯 PAP Type', 'inline' => true],
        ];
        
        foreach ($fieldMappings as $key => $field) {
            if (!empty($data[$key])) {
                $embed['fields'][] = [
                    'name' => $field['name'],
                    'value' => $data[$key],
                    'inline' => $field['inline']
                ];
            }
        }
        
        // Handle doctrine field
        if (!empty($data['doctrine']) || !empty($data['doctrine_name'])) {
            $doctrineValue = $data['doctrine'] ?? $data['doctrine_name'];
            
            // If we have a URL, create a markdown link
            if (!empty($data['doctrine_url']) && !empty($data['doctrine_name'])) {
                $doctrineValue = "[{$data['doctrine_name']}]({$data['doctrine_url']})";
            }
            
            $embed['fields'][] = [
                'name' => '🚀 Doctrine',
                'value' => $doctrineValue,
                'inline' => false
            ];
        }
        
        // Handle comms/channel - intelligently combine them
        $channelInfo = [];
        
        // Check if we have a Discord channel selected
        $hasDiscordChannel = !empty($data['channel_url']) || !empty($data['channel_mention']);
        
        // If we have a Discord channel selected, use that
        if ($hasDiscordChannel && !empty($data['channel_mention'])) {
            // Use only the channel mention (which is already a clickable link in Discord)
            $channelInfo[] = $data['channel_mention'];
        } elseif (!empty($data['comms'])) {
            // If no Discord channel is selected but comms is filled, use comms
            $channelInfo[] = $data['comms'];
        }
        
        // Add combined comms/channel field if we have any info
        if (!empty($channelInfo)) {
            $embed['fields'][] = [
                'name' => '🎧 Comms / Channel',
                'value' => implode("\n", $channelInfo),
                'inline' => false
            ];
        }

        $payload['embeds'] = [$embed];

        // Add username and avatar
        $payload['username'] = config('discordpings.app_name', 'SeAT Broadcast');
        
        return $payload;
    }

    /**
     * Enforce per-webhook rate limits from config. Returns a failure payload
     * (and writes a history row) when the caller should abort the send.
     */
    private function checkRateLimit($webhook, $user, array $data): ?array
    {
        if (! config('discordpings.rate_limit.enabled', true)) {
            return null;
        }

        $maxMin  = (int) config('discordpings.rate_limit.max_per_minute', 10);
        $maxHour = (int) config('discordpings.rate_limit.max_per_hour', 100);
        $minKey  = 'discordpings:min:' . $webhook->id;
        $hourKey = 'discordpings:hour:' . $webhook->id;

        if (RateLimiter::tooManyAttempts($minKey, $maxMin)) {
            return $this->recordRateLimited($webhook, $user, $data,
                sprintf('Rate limit: %d/min reached, retry in %ds',
                    $maxMin, RateLimiter::availableIn($minKey)));
        }

        if (RateLimiter::tooManyAttempts($hourKey, $maxHour)) {
            return $this->recordRateLimited($webhook, $user, $data,
                sprintf('Rate limit: %d/hour reached, retry in %ds',
                    $maxHour, RateLimiter::availableIn($hourKey)));
        }

        RateLimiter::hit($minKey, 60);
        RateLimiter::hit($hourKey, 3600);
        return null;
    }

    private function recordRateLimited($webhook, $user, array $data, string $msg): array
    {
        PingHistory::create([
            'webhook_id'    => $webhook->id,
            'user_id'       => $user->id,
            'user_name'     => $user->name,
            'message'       => $data['message'] ?? '',
            'fields'        => $this->extractFields($data),
            'status'        => 'rate_limited',
            'error_message' => $msg,
        ]);

        Log::warning('Discord Pings: rate limit hit', [
            'webhook_id' => $webhook->id,
            'detail'     => $msg,
        ]);

        return ['success' => false, 'status' => 'rate_limited', 'error' => $msg];
    }

    /**
     * Extract fields from data
     */
    private function extractFields($data)
    {
        $fields = [];
        $fieldKeys = ['fc_name', 'formup_location', 'pap_type', 'comms', 'doctrine', 'channel_url',
                      'channel_mention', 'doctrine_name', 'doctrine_url', 'embed_color', 'embed_type'];

        foreach ($fieldKeys as $key) {
            if (isset($data[$key]) && !empty($data[$key])) {
                $fields[$key] = $data[$key];
            }
        }

        return $fields;
    }

    /**
     * Publish a pings.broadcast.sent event to Manager Core's EventBus.
     * Silent no-op without MC. Safely swallows all errors so a publish
     * failure can never break a successful broadcast.
     */
    private function publishBroadcastSentEvent($webhook, $user, array $data, $history): void
    {
        try {
            $message = is_string($data['message'] ?? null) ? $data['message'] : '';
            $summary = mb_strlen($message) > 200
                ? mb_substr($message, 0, 197) . '...'
                : $message;

            BroadcastEventPublisher::publishBroadcastSent([
                'user_id'           => (int) ($user->id ?? 0),
                'user_name'         => (string) ($user->name ?? 'system'),
                'webhook_id'        => (int) $webhook->id,
                'webhook_name'      => (string) ($webhook->name ?? ''),
                'corporation_id'    => isset($webhook->corporation_id) && $webhook->corporation_id !== null
                    ? (int) $webhook->corporation_id
                    : null,
                'broadcast_type'    => (string) ($data['embed_type'] ?? 'message'),
                'mention_type'      => (string) ($data['mention_type'] ?? 'none'),
                'message_summary'   => $summary,
                'history_id'        => (int) ($history->id ?? 0),
                'is_scheduled'      => (bool) ($data['_is_scheduled'] ?? false),
                'is_structure_alert'=> (bool) ($data['_is_structure_alert'] ?? false),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                '[DiscordPings] publishBroadcastSentEvent failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Detect which seat-fitting plugin is installed (if any). Returns the
     * fully-qualified Doctrine model class name, or null if neither variant
     * is present.
     */
    public static function detectFittingDoctrineClass(): ?string
    {
        foreach ([
            'CryptaTech\\Seat\\Fitting\\Models\\Doctrine',
            'Denngarr\\Seat\\Fitting\\Models\\Doctrine',
        ] as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }
        return null;
    }

    /**
     * List all fitting doctrines from whichever seat-fitting plugin is
     * installed. Returns an empty array if none is present or the query
     * fails (table missing, permissions, etc.).
     */
    public static function listFittingDoctrines(): array
    {
        $class = static::detectFittingDoctrineClass();
        if (! $class) {
            return [];
        }

        try {
            return $class::orderBy('name')->get()->all();
        } catch (\Throwable $e) {
            Log::info('Discord Pings: could not load doctrines from ' . $class . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Look up a single fitting doctrine by id across whichever seat-fitting
     * plugin is installed.
     */
    public static function findFittingDoctrine(int $id): ?object
    {
        $class = static::detectFittingDoctrineClass();
        if (! $class) {
            return null;
        }

        try {
            return $class::find($id);
        } catch (\Throwable $e) {
            Log::info('Discord Pings: could not find doctrine ' . $id . ' in ' . $class . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Test a webhook
     */
    public function testWebhook($webhook)
    {
        $testData = [
            'message' => 'This is a test ping from SeAT Broadcast plugin.',
            'fc_name' => 'Test FC',
            'formup_location' => 'Test System',
            'pap_type' => 'Strategic',
            'comms' => 'Test Comms Channel',
            'doctrine' => 'Test Ships',
            'embed_color' => '#00FF00',
            'embed_type' => 'fleet'
        ];

        $testUser = (object) [
            'id' => 0,
            'name' => 'System Test'
        ];

        return $this->sendPing($webhook, $testData, $testUser);
    }

    /**
     * Send a pre-timer structure alert to a webhook. Builds a timer-specific
     * embed, honors the configured per-webhook rate limits, and logs a
     * PingHistory row for auditability.
     */
    public function sendStructureTimerAlert($webhook, $event, string $stage): array
    {
        $user = (object) ['id' => 0, 'name' => 'Structure Timer Alert'];
        $data = [
            'message' => $this->stageLabel($stage) . ' reminder: ' . ($event->title ?: 'Structure timer'),
        ];

        if ($limited = $this->checkRateLimit($webhook, $user, $data)) {
            return $limited;
        }

        try {
            $payload = $this->buildStructureAlertPayload($event, $stage);

            $response = $this->client->post($webhook->webhook_url, [
                'json' => $payload,
            ]);

            PingHistory::create([
                'webhook_id'         => $webhook->id,
                'user_id'            => 0,
                'user_name'          => 'Structure Timer Alert',
                'message'            => $data['message'],
                'fields'             => $this->structureAlertFields($event, $stage),
                'webhook_response'   => json_decode($response->getBody(), true),
                'status'             => 'sent',
                'discord_message_id' => $response->getHeader('X-Message-Id')[0] ?? null,
            ]);

            return ['success' => true];

        } catch (RequestException $e) {
            Log::error('Discord structure alert send error', [
                'webhook_id'        => $webhook->id,
                'tactical_event_id' => $event->id,
                'error'             => $e->getMessage(),
            ]);

            PingHistory::create([
                'webhook_id'    => $webhook->id,
                'user_id'       => 0,
                'user_name'     => 'Structure Timer Alert',
                'message'       => $data['message'],
                'fields'        => $this->structureAlertFields($event, $stage),
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the Discord embed payload for a structure timer alert.
     */
    private function buildStructureAlertPayload($event, string $stage): array
    {
        $severityColors = [
            'critical' => 0xDC3545,
            'warning'  => 0xFFC107,
            'info'     => 0x17A2B8,
        ];
        $color = $severityColors[$event->severity] ?? $severityColors['info'];

        $fields = [];

        if (!empty($event->system_name)) {
            $system = $event->system_name;
            if ($event->system_security !== null) {
                $system .= ' (' . number_format((float) $event->system_security, 1) . ')';
            }
            $fields[] = ['name' => '🗺️ System', 'value' => $system, 'inline' => true];
        }

        if (!empty($event->event_type)) {
            $fields[] = [
                'name'   => '🏷️ Type',
                'value'  => ucwords(str_replace(['_', '-'], ' ', $event->event_type)),
                'inline' => true,
            ];
        }

        if ($event->eve_time) {
            $fields[] = [
                'name'   => '⏰ Timer (EVE)',
                'value'  => $event->eve_time->format('Y-m-d H:i') . ' EVE',
                'inline' => false,
            ];
        }

        if (!empty($event->owner_corporation_name)) {
            $fields[] = ['name' => '🚩 Owner', 'value' => $event->owner_corporation_name, 'inline' => true];
        }

        if (!empty($event->attacker_corporation_name)) {
            $fields[] = ['name' => '⚔️ Attacker', 'value' => $event->attacker_corporation_name, 'inline' => true];
        }

        if (!empty($event->notes)) {
            $fields[] = ['name' => '📝 Notes', 'value' => Str::limit($event->notes, 256), 'inline' => false];
        }

        $description = $event->title ?: 'Structure timer';
        if (!empty($event->url)) {
            $description .= "\n[View in Structure Manager](" . $event->url . ')';
        }

        $embed = [
            'title'       => '⏳ Structure Timer Reminder (' . $this->stageLabel($stage) . ')',
            'description' => $description,
            'color'       => $color,
            'fields'      => $fields,
            'footer'      => [
                'text' => sprintf(
                    'Relayed by %s at %s EVE',
                    config('discordpings.app_name', 'SeAT Broadcast'),
                    Carbon::now()->utc()->format('Y-m-d H:i:s')
                ),
            ],
            'timestamp'   => Carbon::now()->utc()->toIso8601String(),
        ];

        return [
            'username' => config('discordpings.app_name', 'SeAT Broadcast'),
            'embeds'   => [$embed],
        ];
    }

    /**
     * Compact field set persisted on the PingHistory row for a structure alert.
     */
    private function structureAlertFields($event, string $stage): array
    {
        return array_filter([
            'alert_stage'    => $this->stageLabel($stage),
            'structure_name' => $event->structure_name,
            'system_name'    => $event->system_name,
            'event_type'     => $event->event_type,
            'severity'       => $event->severity,
            'eve_time'       => $event->eve_time ? $event->eve_time->toIso8601String() : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Human label for a pre-timer ping stage.
     */
    private function stageLabel(string $stage): string
    {
        return $stage === '24h' ? 'T-24h' : 'T-1h';
    }

    /**
     * Send a single pre-expiry mining-extraction alert (T-2h) to one webhook.
     * Reuses the rate-limit + PingHistory logging machinery as the structure
     * timer alert path. Always logs a PingHistory row for auditability.
     *
     * Called from SendMiningAlertJob, which is dispatched once per opted-in
     * webhook by MiningExtractionHandler on the mining.extraction_unstable
     * event. The handler's pinged_1h_at latch already guarantees one dispatch
     * per extraction; nothing in this path re-issues.
     */
    public function sendMiningExtractionAlert($webhook, $event): array
    {
        $user = (object) ['id' => 0, 'name' => 'Mining Extraction Alert'];
        $data = [
            'message' => 'Mining extraction T-2h: ' . ($event->title ?: 'Moon extraction'),
        ];

        if ($limited = $this->checkRateLimit($webhook, $user, $data)) {
            return $limited;
        }

        try {
            $payload = $this->buildMiningAlertPayload($event);

            $response = $this->client->post($webhook->webhook_url, [
                'json' => $payload,
            ]);

            PingHistory::create([
                'webhook_id'         => $webhook->id,
                'user_id'            => 0,
                'user_name'          => 'Mining Extraction Alert',
                'message'            => $data['message'],
                'fields'             => $this->miningAlertFields($event),
                'webhook_response'   => json_decode($response->getBody(), true),
                'status'             => 'sent',
                'discord_message_id' => $response->getHeader('X-Message-Id')[0] ?? null,
            ]);

            return ['success' => true];

        } catch (RequestException $e) {
            Log::error('Discord mining alert send error', [
                'webhook_id'        => $webhook->id,
                'tactical_event_id' => $event->id,
                'error'             => $e->getMessage(),
            ]);

            PingHistory::create([
                'webhook_id'    => $webhook->id,
                'user_id'       => 0,
                'user_name'     => 'Mining Extraction Alert',
                'message'       => $data['message'],
                'fields'        => $this->miningAlertFields($event),
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the Discord embed payload for a mining-extraction T-2h alert.
     * Warning-yellow embed (capital safety phase = warning severity).
     */
    private function buildMiningAlertPayload($event): array
    {
        $color = 0xFFC107; // warning yellow — final 2h before expiry

        $fields = [];
        $payloadData = is_array($event->payload) ? $event->payload : [];

        if (! empty($event->structure_name)) {
            $fields[] = ['name' => '🏭 Refinery', 'value' => $event->structure_name, 'inline' => true];
        }

        if (! empty($payloadData['moon_name'])) {
            $fields[] = ['name' => '🌑 Moon', 'value' => $payloadData['moon_name'], 'inline' => true];
        }

        if ($event->eve_time) {
            $fields[] = [
                'name'   => '⏰ Window closes (EVE)',
                'value'  => $event->eve_time->format('Y-m-d H:i') . ' EVE',
                'inline' => false,
            ];
        }

        if (! empty($payloadData['is_jackpot'])) {
            $fields[] = ['name' => '⭐ Jackpot', 'value' => '+100% ore variants — full value', 'inline' => false];
        }

        if (! empty($payloadData['estimated_value']) && (float) $payloadData['estimated_value'] > 0) {
            $fields[] = [
                'name'   => '💰 Estimated value',
                'value'  => number_format((float) $payloadData['estimated_value'], 0) . ' ISK',
                'inline' => true,
            ];
        }

        $description = 'This extraction has 2 hours of fleet-able time remaining before the window closes.';

        $embed = [
            'title'       => '⛏️ Mining Extraction T-2h',
            'description' => $description,
            'color'       => $color,
            'fields'      => $fields,
            'footer'      => [
                'text' => sprintf(
                    'Relayed by %s at %s EVE',
                    config('discordpings.app_name', 'SeAT Broadcast'),
                    Carbon::now()->utc()->format('Y-m-d H:i:s')
                ),
            ],
            'timestamp'   => Carbon::now()->utc()->toIso8601String(),
        ];

        return [
            'username' => config('discordpings.app_name', 'SeAT Broadcast'),
            'embeds'   => [$embed],
        ];
    }

    /**
     * Compact field set persisted on the PingHistory row for a mining alert.
     */
    private function miningAlertFields($event): array
    {
        $payloadData = is_array($event->payload) ? $event->payload : [];

        return array_filter([
            'alert_stage'    => 'T-2h',
            'category'       => 'mining',
            'structure_name' => $event->structure_name,
            'moon_name'      => $payloadData['moon_name'] ?? null,
            'is_jackpot'     => ! empty($payloadData['is_jackpot']) ? 'yes' : null,
            'eve_time'       => $event->eve_time ? $event->eve_time->toIso8601String() : null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
