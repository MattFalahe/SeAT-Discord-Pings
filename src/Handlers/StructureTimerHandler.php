<?php

namespace DiscordPings\Handlers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use DiscordPings\Jobs\SendStructureAlertJob;
use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\PluginSetting;
use DiscordPings\Models\TacticalEvent;

/**
 * EventBus subscriber for Structure Manager's `structure_manager.timer.*`
 * event family. Ingests timers into discord_tactical_events so they appear on
 * the Broadcasts Calendar, and fires pre-timer reminder pings.
 *
 * Invoked by Manager Core's EventBus via the capability registered in
 * DiscordPingsServiceProvider. Never invoked when Manager Core is absent.
 */
class StructureTimerHandler
{
    /**
     * Highest payload schema_version this handler understands. A higher value
     * means the publisher shipped a breaking change we predate — ignore the
     * event rather than mis-parse it.
     */
    private const SUPPORTED_SCHEMA_VERSION = 1;

    /**
     * EventBus entry point. The signature is fixed by the EventBus dispatch
     * contract: (eventName, publisher, payload). Never throws — a failure here
     * must not poison the publisher's job.
     */
    public function handle(string $eventName, string $publisher, array $payload): void
    {
        try {
            if (! config('discordpings.structure_events.enabled', true)) {
                return;
            }

            $schemaVersion = (int) ($payload['schema_version'] ?? 1);
            if ($schemaVersion > self::SUPPORTED_SCHEMA_VERSION) {
                Log::warning("[DiscordPings] StructureTimerHandler: ignoring {$eventName} — schema_version {$schemaVersion} is newer than supported.");
                return;
            }

            $action = $this->resolveAction($eventName);
            if ($action === null) {
                Log::debug("[DiscordPings] StructureTimerHandler: ignoring unrecognised event '{$eventName}'.");
                return;
            }

            $timerId = (int) ($payload['timer_id'] ?? 0);
            if ($timerId <= 0) {
                Log::warning("[DiscordPings] StructureTimerHandler: {$eventName} missing timer_id, skipped.");
                return;
            }

            // Every event carries the full timer snapshot, so we can always
            // upsert the row regardless of which action fired. Ingest is
            // therefore order-independent and idempotent.
            $event = $this->upsert($publisher, $timerId, $payload, $action);

            switch ($action) {
                case 'dismissed':
                case 'recovered':
                    // Timer is no longer a live concern — drop it off the calendar.
                    $event->update(['status' => 'dismissed']);
                    break;

                case 'elapsed':
                    // Keep it on the calendar but dimmed.
                    $event->update(['status' => 'elapsed', 'is_elapsed' => true]);
                    break;

                case 'upcoming_24h':
                    $this->dispatchPreTimerPings($event, '24h');
                    break;

                case 'upcoming_1h':
                    $this->dispatchPreTimerPings($event, '1h');
                    break;

                // created / updated — already upserted as active; nothing more.
            }
        } catch (\Throwable $e) {
            Log::error('[DiscordPings] StructureTimerHandler failed for ' . $eventName . ': ' . $e->getMessage());
        }
    }

    /**
     * Whitelist the EventBus event name → lifecycle action. Unknown names
     * (a future event flavor) resolve to null and are skipped.
     */
    private function resolveAction(string $eventName): ?string
    {
        return match ($eventName) {
            'structure_manager.timer.created'      => 'created',
            'structure_manager.timer.updated'      => 'updated',
            'structure_manager.timer.dismissed'    => 'dismissed',
            'structure_manager.timer.elapsed'      => 'elapsed',
            'structure_manager.timer.upcoming_24h' => 'upcoming_24h',
            'structure_manager.timer.upcoming_1h'  => 'upcoming_1h',
            'structure_manager.timer.recovered'    => 'recovered',
            default                                => null,
        };
    }

    /**
     * Upsert the discord_tactical_events row from the event payload, keyed on
     * the publisher + its own timer id (the stable identity across events).
     */
    private function upsert(string $publisher, int $timerId, array $payload, string $action): TacticalEvent
    {
        $eveTime = null;
        if (! empty($payload['eve_time'])) {
            try {
                $eveTime = Carbon::parse($payload['eve_time']);
            } catch (\Throwable $e) {
                $eveTime = null;
            }
        }

        $attributes = [
            'last_event_id'             => $payload['event_id'] ?? null,
            'event_type'                => $payload['event_type'] ?? null,
            'category_group'            => $payload['category_group'] ?? null,
            'severity'                  => $payload['severity'] ?? 'info',
            'title'                     => $this->buildTitle($payload),
            'eve_time'                  => $eveTime,
            'is_manual'                 => (bool) ($payload['is_manual'] ?? false),
            'is_elapsed'                => (bool) ($payload['is_elapsed'] ?? false),
            'structure_id'              => $payload['structure_id'] ?? null,
            'structure_name'            => $payload['structure_name'] ?? null,
            'structure_type'            => $payload['structure_type'] ?? null,
            'system_id'                 => $payload['system_id'] ?? null,
            'system_name'               => $payload['system_name'] ?? null,
            'system_security'           => isset($payload['system_security']) ? (float) $payload['system_security'] : null,
            'owner_corporation_name'    => $payload['owner_corporation_name'] ?? null,
            'attacker_corporation_name' => $payload['attacker_corporation_name'] ?? null,
            'corporation_id'            => $payload['corporation_id'] ?? null,
            'role_id'                   => $payload['role_id'] ?? null,
            'url'                       => $payload['url'] ?? null,
            'notes'                     => $payload['notes'] ?? null,
            'payload'                   => $payload,
            'last_seen_at'              => Carbon::now(),
        ];

        // created / updated re-assert an active timer (e.g. an elapsed timer
        // whose time was pushed back into the future). dismissed / elapsed /
        // recovered have their status set by the caller right after this.
        if (in_array($action, ['created', 'updated'], true)) {
            $attributes['status'] = 'active';
        }

        return TacticalEvent::updateOrCreate(
            ['source_plugin' => $publisher, 'external_timer_id' => $timerId],
            $attributes
        );
    }

    /**
     * Fire pre-timer reminder pings for a stage ('24h' or '1h') to every
     * webhook opted into structure alerts. The latch column is claimed
     * atomically so a replayed event cannot double-ping.
     */
    private function dispatchPreTimerPings(TacticalEvent $event, string $stage): void
    {
        // Operator opt-out — pre-timer pings can be switched off in
        // Settings > Structure Timers without un-flagging each webhook.
        // The calendar still ingests timers regardless.
        if (! PluginSetting::getBool('structure_alerts_enabled', true)) {
            return;
        }

        $latchColumn = $stage === '24h' ? 'pinged_24h_at' : 'pinged_1h_at';

        // Atomic claim — only the delivery that flips NULL → now() dispatches.
        $claimed = TacticalEvent::where('id', $event->id)
            ->whereNull($latchColumn)
            ->update([$latchColumn => Carbon::now()]);

        if ($claimed === 0) {
            return; // another delivery already handled this stage
        }

        // Corp-scoped routing: a webhook with corporation_id = NULL receives
        // events from ANY corp (backwards-compat default + org-wide channels).
        // A webhook with a specific corporation_id only receives events whose
        // payload corporation_id matches. Events with NO corp scope (global)
        // only reach NULL-corp webhooks.
        $eventCorpId = $event->corporation_id;
        $webhooks = DiscordWebhook::query()
            ->where('is_active', true)
            ->where('receives_structure_alerts', true)
            ->where(function ($q) use ($eventCorpId) {
                $q->whereNull('corporation_id');
                if ($eventCorpId !== null) {
                    $q->orWhere('corporation_id', $eventCorpId);
                }
            })
            ->get();

        foreach ($webhooks as $webhook) {
            SendStructureAlertJob::dispatch($webhook->id, $event->id, $stage);
        }
    }

    /**
     * Build a concise calendar title. Prefers the operator-given structure
     * name; falls back to a humanized event type, then a generic label.
     */
    private function buildTitle(array $payload): string
    {
        $structure = trim((string) ($payload['structure_name'] ?? ''));
        if ($structure !== '') {
            return $structure;
        }

        $typeLabel = $this->humanizeEventType($payload['event_type'] ?? null);
        if ($typeLabel !== '') {
            return $typeLabel;
        }

        return 'Structure Timer';
    }

    private function humanizeEventType(?string $eventType): string
    {
        if (! $eventType) {
            return '';
        }

        return ucwords(str_replace(['_', '-'], ' ', $eventType));
    }
}
