<?php

namespace DiscordPings\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Suggests Discord roles operators can pick from when adding a new role record
 * on the Settings > Discord Roles tab.
 *
 * UNLIKE SM / MM's resolver of the same name, this one deliberately does NOT
 * query the `discord_roles` table — SeAT Broadcast owns that table, it IS the
 * destination of the Add Role form, so pulling from it would be circular and
 * could nudge operators into creating duplicate records. The picker here is
 * strictly a way to short-cut "I have to open Discord, enable Developer Mode,
 * right-click the role, copy ID, switch tabs, paste" when a connector plugin
 * already has the roles catalogued.
 *
 * Sources queried (in priority order — first sighting wins on dedup):
 *
 *   - seat_connector_sets             (the SeAT Connector framework; the
 *                                      table is shared by warlof/seat-connector
 *                                      AND zenobio93's maintained fork, and
 *                                      populated by whichever Discord driver
 *                                      is installed — warlof/seat-discord-connector
 *                                      or its zenobio93 fork). Rows with
 *                                      connector_type='discord' are Discord
 *                                      roles synced from the live guild. We
 *                                      don't care which vendor is installed
 *                                      because the schema is identical.
 *
 *   - warlof_discord_connector_roles  (legacy warlof-only standalone install,
 *     or discord_connector_roles       pre-framework era)
 *
 * Detection is TABLE-based (not class_exists) for the same reason SM/MM do it
 * that way: SeAT plugins ship via composer/tarball/manual and a package may be
 * installed without its classes being autoloadable under our namespace
 * resolution. If the table is present, its roles contribute to the picker.
 *
 * Roles whose Discord snowflake already exists in this plugin's own
 * `discord_roles` table are filtered out of the picker — operators shouldn't
 * be offered roles they've already added (the resulting duplicate-snowflake
 * record would be confusing and break dedupe across plugins).
 *
 * Returns [] from listAvailableRoles() when no connector provider is detected,
 * so the UI falls back to the always-functional manual input.
 */
class DiscordRoleResolver
{
    public const PROVIDER_SEAT_CONNECTOR = 'seat-connector';
    public const PROVIDER_WARLOF_DISCORD = 'warlof-discord';

    /**
     * Priority order when the same Discord snowflake appears in multiple
     * connector sources — first match wins, later ones just tagged as
     * additional 'sources'.
     */
    private const SOURCE_PRIORITY = [
        self::PROVIDER_SEAT_CONNECTOR,   // modern, actively maintained
        self::PROVIDER_WARLOF_DISCORD,   // legacy
    ];

    /**
     * Provider tables to probe in priority order, with a list of
     * possible legacy table names for the second provider.
     */
    private const PROVIDER_TABLES = [
        self::PROVIDER_SEAT_CONNECTOR => ['seat_connector_sets'],
        self::PROVIDER_WARLOF_DISCORD => ['warlof_discord_connector_roles', 'discord_connector_roles'],
    ];

    /**
     * Return every connector provider whose table is present. Empty array
     * means no picker is available — UI falls back to manual input only.
     *
     * @return array<int, string>
     */
    public static function detectAvailableProviders(): array
    {
        $providers = [];

        foreach (self::SOURCE_PRIORITY as $provider) {
            foreach (self::PROVIDER_TABLES[$provider] as $table) {
                if (Schema::hasTable($table)) {
                    $providers[] = $provider;
                    break;
                }
            }
        }

        return $providers;
    }

    /**
     * True when at least one connector provider table is detected.
     */
    public static function isAvailable(): bool
    {
        return ! empty(self::detectAvailableProviders());
    }

    /**
     * Return every connector-sourced Discord role NOT already present in this
     * plugin's `discord_roles` table, merged + deduped across providers.
     *
     * Shape per role:
     *   [
     *     'id'             => '1227722401236652123',    // Discord snowflake
     *     'name'           => 'Corp Member',
     *     'mention_format' => '<@&1227722401236652123>',
     *     'source'         => 'seat-connector',          // primary (richest) source
     *     'sources'        => ['seat-connector'],        // every source the role appeared in
     *   ]
     *
     * @return array<int, array>
     */
    public static function listAvailableRoles(): array
    {
        $providers = self::detectAvailableProviders();
        if (empty($providers)) {
            return [];
        }

        $existing = self::existingSnowflakes();
        $merged   = [];

        foreach ($providers as $provider) {
            try {
                $rows = match ($provider) {
                    self::PROVIDER_SEAT_CONNECTOR => self::rolesFromSeatConnector(),
                    self::PROVIDER_WARLOF_DISCORD => self::rolesFromWarlof(),
                    default                       => [],
                };
            } catch (\Throwable $e) {
                Log::warning('[DiscordPings] DiscordRoleResolver: failed listing roles from ' . $provider . ': ' . $e->getMessage());
                continue;
            }

            foreach ($rows as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id === '' || isset($existing[$id])) {
                    // Skip empty IDs + skip anything already in our own table
                    continue;
                }

                if (! isset($merged[$id])) {
                    $row['source']  = $provider;
                    $row['sources'] = [$provider];
                    $merged[$id]    = $row;
                } else {
                    // Already seen via a higher-priority provider; just note the additional source
                    $merged[$id]['sources'][] = $provider;
                }
            }
        }

        // Sort alphabetically by name for predictable picker order
        $result = array_values($merged);
        usort($result, fn ($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return $result;
    }

    /**
     * Short human-friendly label for a single provider — used in source badges
     * on the picker UI.
     */
    public static function providerShortLabel(string $provider): string
    {
        return match ($provider) {
            // Generic label — the seat_connector_sets table is populated
            // by either warlof/seat-connector OR zenobio93's maintained fork,
            // we can't tell which from the schema (it's identical) and we
            // don't need to.
            self::PROVIDER_SEAT_CONNECTOR => 'SeAT Connector',
            self::PROVIDER_WARLOF_DISCORD => 'Warlof Discord (legacy)',
            default                       => $provider,
        };
    }

    /**
     * Aggregate label for the UI banner when picker is shown, e.g.
     * "SeAT Connector + Warlof Discord (legacy)".
     */
    public static function providerLabel(): string
    {
        $providers = self::detectAvailableProviders();
        if (empty($providers)) {
            return 'Manual input only';
        }

        return implode(' + ', array_map(fn ($p) => self::providerShortLabel($p), $providers));
    }

    // ---- Provider-specific queries ----

    /**
     * warlof/seat-connector — Discord rows from seat_connector_sets.
     */
    private static function rolesFromSeatConnector(): array
    {
        $rows = DB::table('seat_connector_sets')
            ->where('connector_type', 'discord')
            ->orderBy('name')
            ->get(['connector_id', 'name']);

        return $rows->map(function ($r) {
            return [
                'id'             => (string) $r->connector_id,
                'name'           => (string) $r->name,
                'mention_format' => '<@&' . $r->connector_id . '>',
            ];
        })->all();
    }

    /**
     * Legacy warlof tables — schema is fuzzy (different versions used
     * different column names), so adapt to whatever's there.
     */
    private static function rolesFromWarlof(): array
    {
        foreach (self::PROVIDER_TABLES[self::PROVIDER_WARLOF_DISCORD] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $idCol   = in_array('discord_id', $columns) ? 'discord_id' : 'id';
            $nameCol = in_array('name', $columns) ? 'name' : $idCol;

            $rows = DB::table($table)
                ->orderBy($nameCol)
                ->get([$idCol . ' as role_id', $nameCol . ' as name']);

            return $rows->map(function ($r) {
                return [
                    'id'             => (string) $r->role_id,
                    'name'           => (string) $r->name,
                    'mention_format' => '<@&' . $r->role_id . '>',
                ];
            })->all();
        }

        return [];
    }

    /**
     * Snowflakes already present in this plugin's own `discord_roles` table —
     * used to filter the picker so we don't offer roles operators already
     * created. Returns a snowflake-keyed lookup for O(1) presence checks.
     *
     * @return array<string, true>
     */
    private static function existingSnowflakes(): array
    {
        try {
            if (! Schema::hasTable('discord_roles')) {
                return [];
            }
            return DB::table('discord_roles')
                ->whereNotNull('role_id')
                ->pluck('role_id')
                ->mapWithKeys(fn ($id) => [(string) $id => true])
                ->all();
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] DiscordRoleResolver: failed reading existing snowflakes: ' . $e->getMessage());
            return [];
        }
    }
}
