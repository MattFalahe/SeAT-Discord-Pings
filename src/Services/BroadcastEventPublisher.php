<?php

namespace DiscordPings\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Publishes SeAT Broadcast events to Manager Core's EventBus.
 *
 * Mirrors the pattern used by Structure Manager's TimerEventPublisher:
 *   - Guarded by class_exists so the plugin remains MC-optional.
 *   - schema_version = 1; bump only on breaking payload changes.
 *   - event_id is per-publish (random uuid prefixed with "pings-evt-").
 *   - source_plugin is always "seat-discord-pings".
 *
 * Subscribers (e.g. HR Manager tracking active FC activity) receive a
 * fn(string $eventName, string $publisher, array $payload): void callback.
 *
 * Event catalog (v1):
 *   - pings.broadcast.sent    : a successful Discord broadcast completed
 *   - pings.formup.scheduled  : an FC scheduled a ping correlated with a tactical event
 */
class BroadcastEventPublisher
{
    public const SCHEMA_VERSION = 1;
    public const SOURCE_PLUGIN  = 'seat-discord-pings';

    /**
     * Convenience wrapper for the broadcast-sent event.
     */
    public static function publishBroadcastSent(array $context): bool
    {
        return self::publish('broadcast.sent', $context);
    }

    /**
     * Convenience wrapper for the formup-scheduled event.
     */
    public static function publishFormupScheduled(array $context): bool
    {
        return self::publish('formup.scheduled', $context);
    }

    /**
     * Publish an arbitrary pings.* event to the EventBus.
     *
     * @param string $eventType  e.g. "broadcast.sent" → "pings.broadcast.sent"
     * @param array  $context    payload-specific keys merged into the envelope
     * @return bool true if published; false if MC absent or publish failed
     */
    public static function publish(string $eventType, array $context): bool
    {
        // MC is optional — silently no-op when EventBus is unavailable.
        if (! class_exists('\\ManagerCore\\Services\\EventBus')) {
            return false;
        }

        $eventName = 'pings.' . $eventType;

        $envelope = array_merge($context, [
            // Pinned fields — overwrite any caller-supplied values.
            'source_plugin'  => self::SOURCE_PLUGIN,
            'schema_version' => self::SCHEMA_VERSION,
            'event_id'       => 'pings-evt-' . (string) Str::uuid(),
            'event_type'     => $eventType,
            'timestamp'      => Carbon::now()->utc()->toIso8601String(),
        ]);

        try {
            app(\ManagerCore\Services\EventBus::class)->publishSanitized(
                $eventName,
                self::SOURCE_PLUGIN,
                $envelope
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning(
                '[DiscordPings] BroadcastEventPublisher: failed to publish '
                . $eventName . ': ' . $e->getMessage()
            );

            return false;
        }
    }
}
