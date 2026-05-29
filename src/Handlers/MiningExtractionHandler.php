<?php

namespace DiscordPings\Handlers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use DiscordPings\Jobs\SendMiningAlertJob;
use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\PluginSetting;
use DiscordPings\Models\TacticalEvent;

/**
 * EventBus subscriber for Mining Manager's `mining.extraction_*` event family.
 * Ingests moon-extraction windows into discord_tactical_events with
 * category_group='mining' so they appear on the Broadcasts Calendar and on
 * the FC Opportunities board.
 *
 * Lifecycle mapping:
 *   - extraction_ready    → upsert as status='active' (48h fleet-able window opens)
 *   - extraction_unstable → keep active, fire pre-expiry alert (T-2h)
 *   - extraction_expired  → flip to status='elapsed' (window closed)
 *
 * Key differences from StructureTimerHandler:
 *   - eve_time on the calendar row = window_closes_at (the actual deadline),
 *     not chunk_arrival_time. FCs care about WHEN the mining window ends,
 *     not when it opened (the 48h window is plenty of lead time).
 *   - Only ONE pre-event alert stage (unstable / T-2h), not the two-stage
 *     T-24h + T-1h structure-timer ladder. Mining ops are 2-day events with
 *     many fleets welcome over the window — the unstable alert is a final
 *     "form up now before it expires" reminder.
 *   - Uses the receives_mining_alerts webhook flag, NOT receives_structure_alerts.
 *     Mining audience (industry channel) is typically distinct from defense
 *     fleet audience.
 *   - Severity stays 'info' through ready, bumps to 'warning' for unstable
 *     (capital-safety phase, last call), stays 'warning' for expired.
 *
 * Invoked by Manager Core's EventBus via the capability registered in
 * DiscordPingsServiceProvider. Never invoked when Manager Core is absent.
 */
class MiningExtractionHandler
{
    /**
     * Highest payload schema_version this handler understands. A higher value
     * means the publisher shipped a breaking change we predate.
     */
    private const SUPPORTED_SCHEMA_VERSION = 1;

    /**
     * EventBus entry point. The signature is fixed by the EventBus dispatch
     * contract: (eventName, publisher, payload). Never throws.
     */
    public function handle(string $eventName, string $publisher, array $payload): void
    {
        try {
            if (! config('discordpings.mining_events.enabled', true)) {
                return;
            }

            $schemaVersion = (int) ($payload['schema_version'] ?? 1);
            if ($schemaVersion > self::SUPPORTED_SCHEMA_VERSION) {
                Log::warning("[DiscordPings] MiningExtractionHandler: ignoring {$eventName} — schema_version {$schemaVersion} is newer than supported.");
                return;
            }

            $stage = $this->resolveStage($eventName);
            if ($stage === null) {
                Log::debug("[DiscordPings] MiningExtractionHandler: ignoring unrecognised event '{$eventName}'.");
                return;
            }

            $extractionId = (int) ($payload['extraction_id'] ?? 0);
            if ($extractionId <= 0) {
                Log::warning("[DiscordPings] MiningExtractionHandler: {$eventName} missing extraction_id, skipped.");
                return;
            }

            // Every event carries the full extraction snapshot. Upsert is
            // therefore order-independent and idempotent.
            $event = $this->upsert($publisher, $extractionId, $payload, $stage);

            switch ($stage) {
                case 'ready':
                    // Window has opened; calendar entry is now active.
                    // No ping at this stage — 48h is plenty of lead time
                    // and a "ready" ping would be premature noise.
                    break;

                case 'unstable':
                    // Final 2h before expiry. Fire the single pre-expiry alert.
                    $this->dispatchPreExpiryAlerts($event);
                    break;

                case 'expired':
                    // Window has closed; drop off the active board.
                    $event->update(['status' => 'elapsed', 'is_elapsed' => true]);
                    break;
            }
        } catch (\Throwable $e) {
            Log::error('[DiscordPings] MiningExtractionHandler failed for ' . $eventName . ': ' . $e->getMessage());
        }
    }

    /**
     * Whitelist the EventBus event name → lifecycle stage. Unknown names
     * (a future event flavor) resolve to null and are skipped.
     */
    private function resolveStage(string $eventName): ?string
    {
        return match ($eventName) {
            'mining.extraction_ready'    => 'ready',
            'mining.extraction_unstable' => 'unstable',
            'mining.extraction_expired'  => 'expired',
            default                      => null,
        };
    }

    /**
     * Upsert the discord_tactical_events row from the extraction payload, keyed
     * on the publisher + its own extraction id (the stable identity).
     *
     * eve_time = window_closes_at (the actual fleet deadline). When the
     * extraction expires we flip status separately.
     */
    private function upsert(string $publisher, int $extractionId, array $payload, string $stage): TacticalEvent
    {
        $eveTime = $this->parseDate($payload['window_closes_at'] ?? null);

        // For ready / unstable: still an active opportunity. For expired:
        // the caller flips status to 'elapsed' immediately after, but we
        // still upsert with 'active' here so any catch-up event for a
        // stage we missed doesn't accidentally undismiss it. The expired
        // case is handled by the switch in handle().
        $status = $stage === 'expired' ? 'elapsed' : 'active';

        // Severity tier: info during fleet-able window; warning during unstable
        // (the capital-safety phase, last call); warning when expired.
        $severity = $stage === 'ready' ? 'info' : 'warning';

        $attributes = [
            'last_event_id'             => $payload['event_id'] ?? null,
            'event_type'                => 'moon_extraction',
            'category_group'            => 'mining',
            'severity'                  => $severity,
            'title'                     => $this->buildTitle($payload),
            'eve_time'                  => $eveTime,
            'is_manual'                 => false,
            'is_elapsed'                => $stage === 'expired',
            'structure_id'              => $payload['structure_id'] ?? null,
            'structure_name'            => $payload['structure_name'] ?? null,
            'structure_type'            => null,
            'system_id'                 => null,
            'system_name'               => null,
            'system_security'           => null,
            'owner_corporation_name'    => null,
            'attacker_corporation_name' => null,
            'corporation_id'            => $payload['corporation_id'] ?? null,
            'role_id'                   => $payload['role_id'] ?? null,
            // Mining Manager v2.0.1+ includes the per-extraction detail page
            // URL in the payload. Empty on older MM versions or if the route
            // couldn't be resolved — the modal/board just hide the deep link.
            'url'                       => $payload['url'] ?? null,
            'notes'                     => $this->buildNotes($payload),
            'payload'                   => $payload,
            'status'                    => $status,
            'last_seen_at'              => Carbon::now(),
        ];

        return TacticalEvent::updateOrCreate(
            ['source_plugin' => $publisher, 'external_timer_id' => $extractionId],
            $attributes
        );
    }

    /**
     * Fire pre-expiry alerts (T-2h, single stage) to every webhook opted into
     * mining alerts. The pinged_1h_at latch is reused (we only ever ping once
     * per extraction) and claimed atomically to prevent double-fires from
     * replayed events.
     */
    private function dispatchPreExpiryAlerts(TacticalEvent $event): void
    {
        // Operator opt-out — mining alerts can be switched off in
        // Settings > Structure Timers (the tab covers all EventBus alerts).
        if (! PluginSetting::getBool('mining_alerts_enabled', true)) {
            return;
        }

        // Reuse the pinged_1h_at column as the single-alert latch for mining
        // (mining only has one alert stage, not the 24h/1h ladder of structure
        // timers). Atomic claim: only the delivery that flips NULL → now()
        // dispatches.
        $claimed = TacticalEvent::where('id', $event->id)
            ->whereNull('pinged_1h_at')
            ->update(['pinged_1h_at' => Carbon::now()]);

        if ($claimed === 0) {
            return; // another delivery already handled this alert
        }

        $eventCorpId = $event->corporation_id;
        $webhooks = DiscordWebhook::query()
            ->where('is_active', true)
            ->where('receives_mining_alerts', true)
            ->where(function ($q) use ($eventCorpId) {
                $q->whereNull('corporation_id');
                if ($eventCorpId !== null) {
                    $q->orWhere('corporation_id', $eventCorpId);
                }
            })
            ->get();

        foreach ($webhooks as $webhook) {
            SendMiningAlertJob::dispatch($webhook->id, $event->id);
        }
    }

    /**
     * Build a concise calendar title. Prefers the moon name (this is what
     * miners think of the op as); falls back to the structure name; then
     * a generic label.
     */
    private function buildTitle(array $payload): string
    {
        $moon = trim((string) ($payload['moon_name'] ?? ''));
        if ($moon !== '') {
            $jackpot = ! empty($payload['is_jackpot']) ? ' ⭐' : '';
            return $moon . $jackpot;
        }

        $structure = trim((string) ($payload['structure_name'] ?? ''));
        if ($structure !== '') {
            return $structure;
        }

        return 'Moon Extraction';
    }

    /**
     * Build a human-readable notes blob shown in the calendar modal.
     */
    private function buildNotes(array $payload): string
    {
        $lines = [];

        if (! empty($payload['structure_name'])) {
            $lines[] = 'Refinery: ' . $payload['structure_name'];
        }

        $opens  = $this->parseDate($payload['window_opens_at'] ?? null);
        $closes = $this->parseDate($payload['window_closes_at'] ?? null);
        if ($opens && $closes) {
            $lines[] = 'Window: ' . $opens->format('Y-m-d H:i') . ' → ' . $closes->format('Y-m-d H:i') . ' EVE';
        } elseif ($closes) {
            $lines[] = 'Closes: ' . $closes->format('Y-m-d H:i') . ' EVE';
        }

        if (! empty($payload['is_jackpot'])) {
            $lines[] = '⭐ JACKPOT chunk (+100% ore variants)';
        }

        if (! empty($payload['estimated_value'])) {
            $value = (float) $payload['estimated_value'];
            if ($value > 0) {
                $lines[] = 'Estimated value: ' . number_format($value, 0) . ' ISK';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Parse an ISO8601 timestamp safely. Returns null on missing/malformed input.
     */
    private function parseDate(?string $iso): ?Carbon
    {
        if (! $iso) {
            return null;
        }
        try {
            return Carbon::parse($iso);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
