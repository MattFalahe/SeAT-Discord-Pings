<?php

namespace DiscordPings\Http\Controllers;

use Carbon\Carbon;
use DiscordPings\Helpers\DiscordHelper;
use DiscordPings\Models\DiscordChannel;
use DiscordPings\Models\DiscordRole;
use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\PapType;
use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PluginSetting;
use DiscordPings\Models\ScheduledPing;
use DiscordPings\Models\StagingLocation;
use DiscordPings\Models\TacticalEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-only diagnostic surface. Intentionally NOT linked from the sidebar
 * or Help & Documentation — admins reach it via /discord-pings/diagnostic.
 * Hides troubleshooting clutter from daily ops while keeping it one URL
 * away when needed.
 *
 * Tabs follow the standard from feedback_plugin_diagnostic_standard.md:
 * Tier 1 universal (Health / Master Test / System Validation / Settings
 * Health / Data Integrity) plus the must-have Tier 3 Broadcast Trace.
 */
class DiagnosticController extends Controller
{
    public function index(Request $request)
    {
        try {
            $activeTab = $request->input('diag_tab', 'health');

            $healthChecks     = $this->buildHealthChecks();
            $masterTest       = $this->buildMasterTest();
            $systemValidation = $this->buildSystemValidation();
            $settingsHealth   = $this->buildSettingsHealth();
            $dataIntegrity    = $this->buildDataIntegrity();

            // Broadcast Trace lazy: only resolve when an id is supplied.
            $traceId     = $request->input('trace_id');
            $traceKind   = $request->input('trace_kind', 'scheduled');
            $broadcastTrace = $traceId
                ? $this->buildBroadcastTrace($traceKind, (int) $traceId)
                : null;

            return view('discordpings::diagnostic.index', compact(
                'activeTab',
                'healthChecks', 'masterTest', 'systemValidation',
                'settingsHealth', 'dataIntegrity', 'broadcastTrace',
                'traceId', 'traceKind'
            ));

        } catch (\Throwable $e) {
            Log::error('Discord Pings diagnostic error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load diagnostic. Check logs.');
        }
    }

    /**
     * Tab 1 — Health Checks. Cheap aggregate stats + integration-detect
     * flags. Eyeball-the-state surface. No expensive queries.
     */
    private function buildHealthChecks(): array
    {
        $tacticalAvailable = Schema::hasTable('discord_tactical_events');
        $settingsAvailable = Schema::hasTable('discord_settings');
        $miningColumn      = Schema::hasColumn('discord_webhooks', 'receives_mining_alerts');

        return [
            'webhooks_total'            => DiscordWebhook::count(),
            'webhooks_active'           => DiscordWebhook::where('is_active', true)->count(),
            'webhooks_with_alerts'      => DiscordWebhook::where('receives_structure_alerts', true)->count(),
            'webhooks_with_mining'      => $miningColumn ? DiscordWebhook::where('receives_mining_alerts', true)->count() : 0,
            'webhooks_corp_scoped'      => DiscordWebhook::whereNotNull('corporation_id')->count(),
            'roles_total'               => DiscordRole::count(),
            'channels_total'            => DiscordChannel::count(),
            'stagings_total'            => StagingLocation::count(),
            'pap_types_total'           => PapType::count(),
            'history_24h'               => PingHistory::where('created_at', '>=', Carbon::now()->subDay())->count(),
            'history_failed_24h'        => PingHistory::where('created_at', '>=', Carbon::now()->subDay())->where('status', 'failed')->count(),
            'history_rate_limited_24h'  => PingHistory::where('created_at', '>=', Carbon::now()->subDay())->where('status', 'rate_limited')->count(),
            'scheduled_active'          => ScheduledPing::where('is_active', true)->count(),
            'scheduled_due_now'         => ScheduledPing::due()->count(),
            'tactical_events_total'     => $tacticalAvailable ? TacticalEvent::count() : 0,
            'tactical_events_active'    => $tacticalAvailable ? TacticalEvent::active()->count() : 0,
            'tactical_events_mining'    => $tacticalAvailable ? TacticalEvent::active()->where('category_group', 'mining')->count() : 0,
            'tactical_events_24h'       => $tacticalAvailable ? TacticalEvent::where('last_seen_at', '>=', Carbon::now()->subDay())->count() : 0,
            'mc_installed'              => class_exists('\ManagerCore\Services\EventBus'),
            'sm_installed'              => class_exists('\StructureManager\Services\TimerEventPublisher'),
            'mm_installed'              => class_exists('\MiningManager\Services\Events\MoonExtractionEventPublisher'),
            'fitting_plugin'            => DiscordHelper::detectFittingDoctrineClass(),
            'master_toggle'             => $settingsAvailable ? PluginSetting::getBool('structure_alerts_enabled', true) : true,
            'mining_master_toggle'      => $settingsAvailable ? PluginSetting::getBool('mining_alerts_enabled', true) : true,
            'plugin_version'            => config('discordpings.version', 'unknown'),
        ];
    }

    /**
     * Tab 2 — Master Test. Runs real validation checks and reports
     * pass/warn/fail. The "is this plugin healthy" deep diagnostic.
     */
    private function buildMasterTest(): array
    {
        $checks = [];

        // Required tables exist
        $requiredTables = [
            'discord_webhooks', 'discord_ping_histories', 'discord_scheduled_pings',
            'discord_tactical_events', 'discord_settings', 'discord_roles',
            'discord_channels', 'discord_staging_locations', 'discord_pap_types',
        ];
        foreach ($requiredTables as $t) {
            $exists = Schema::hasTable($t);
            $checks[] = [
                'name'   => "Table {$t}",
                'status' => $exists ? 'ok' : 'error',
                'detail' => $exists ? 'present' : 'MISSING — restart the SeAT stack so migrations run',
            ];
        }

        // formup_offset_minutes within range
        $offset = (int) PluginSetting::getValue(
            'formup_offset_minutes',
            (int) config('discordpings.structure_events.formup_offset_minutes', 30)
        );
        $checks[] = [
            'name'   => 'formup_offset_minutes in valid range (5-720)',
            'status' => ($offset >= 5 && $offset <= 720) ? 'ok' : 'warn',
            'detail' => "{$offset} minutes",
        ];

        // No scheduled pings stuck overdue
        $stuckOverdue = ScheduledPing::due()
            ->where('scheduled_at', '<', Carbon::now()->subMinutes(5))
            ->count();
        $checks[] = [
            'name'   => 'Cron processing scheduled pings',
            'status' => $stuckOverdue === 0 ? 'ok' : 'error',
            'detail' => $stuckOverdue === 0
                ? 'No scheduled pings overdue by > 5 minutes'
                : "{$stuckOverdue} scheduled ping(s) overdue by > 5 minutes — check Laravel scheduler / cron / queue worker",
        ];

        // MC integration (only if MC installed)
        if (class_exists('\ManagerCore\Services\EventBus')) {
            // Structure timer subscription
            $hasStructureSub = false;
            try {
                $hasStructureSub = Schema::hasTable('manager_core_event_subscriptions')
                    && DB::table('manager_core_event_subscriptions')
                        ->where('subscriber_plugin', 'seat-discord-pings')
                        ->where('event_pattern', 'structure_manager.timer.*')
                        ->exists();
            } catch (\Throwable $e) {
                // fall through
            }
            $checks[] = [
                'name'   => 'MC EventBus subscription: structure timers',
                'status' => $hasStructureSub ? 'ok' : 'warn',
                'detail' => $hasStructureSub
                    ? 'structure_manager.timer.* row exists in manager_core_event_subscriptions'
                    : 'No structure_manager.timer.* subscription found — restart the SeAT stack so the service provider re-registers',
            ];

            // Mining extraction subscription (only meaningful if MM publisher exists)
            $hasMiningSub = false;
            try {
                $hasMiningSub = Schema::hasTable('manager_core_event_subscriptions')
                    && DB::table('manager_core_event_subscriptions')
                        ->where('subscriber_plugin', 'seat-discord-pings')
                        ->where('event_pattern', 'mining.extraction_*')
                        ->exists();
            } catch (\Throwable $e) {
                // fall through
            }
            if (class_exists('\MiningManager\Services\Events\MoonExtractionEventPublisher')) {
                $checks[] = [
                    'name'   => 'MC EventBus subscription: mining extractions',
                    'status' => $hasMiningSub ? 'ok' : 'warn',
                    'detail' => $hasMiningSub
                        ? 'mining.extraction_* row exists in manager_core_event_subscriptions'
                        : 'No mining.extraction_* subscription found — restart the SeAT stack so the service provider re-registers',
                ];
            } else {
                $checks[] = [
                    'name'   => 'MC EventBus subscription: mining extractions',
                    'status' => 'info',
                    'detail' => 'Mining Manager v2.0.1+ not installed — mining extraction subscription is inert (this is fine if MM is not wanted)',
                ];
            }

            $hasRegistry = false;
            try {
                $hasRegistry = Schema::hasTable('manager_core_plugin_registry')
                    && DB::table('manager_core_plugin_registry')
                        ->where('plugin_name', 'seat-discord-pings')
                        ->exists();
            } catch (\Throwable $e) {
                // fall through
            }
            $checks[] = [
                'name'   => 'MC plugin registry self-registration',
                'status' => $hasRegistry ? 'ok' : 'warn',
                'detail' => $hasRegistry
                    ? 'seat-discord-pings has a manager_core_plugin_registry row (PluginBridge::registerSelf fired)'
                    : 'No registry row — MC will show plugin as "Offline / Last Seen Never" on its diagnostic',
            ];
        } else {
            $checks[] = [
                'name'   => 'Manager Core integration',
                'status' => 'info',
                'detail' => 'Manager Core not installed — integration features inactive (this is fine if MC is not wanted)',
            ];
        }

        // Mining-alerts flag sanity: if MM is installed AND mining alerts are
        // enabled AND no webhook is flagged for mining, the integration is
        // active-but-silent. Warn so operators notice the orphan config.
        if (class_exists('\MiningManager\Services\Events\MoonExtractionEventPublisher')
            && Schema::hasColumn('discord_webhooks', 'receives_mining_alerts')
            && PluginSetting::getBool('mining_alerts_enabled', true)
        ) {
            $miningWebhookCount = DiscordWebhook::where('receives_mining_alerts', true)
                ->where('is_active', true)
                ->count();
            $checks[] = [
                'name'   => 'Mining alerts have a delivery target',
                'status' => $miningWebhookCount > 0 ? 'ok' : 'warn',
                'detail' => $miningWebhookCount > 0
                    ? "{$miningWebhookCount} active webhook(s) flagged for mining alerts"
                    : 'Mining Manager is installed and the master switch is ON, but no webhook is flagged "Receive mining extraction alerts" — alerts will fire to nobody',
            ];
        }

        // Webhook URL encryption
        $rawHistorySample = null;
        try {
            $rawHistorySample = DB::table('discord_webhooks')->whereNotNull('webhook_url')->value('webhook_url');
        } catch (\Throwable $e) {
            // ignore
        }
        if ($rawHistorySample !== null) {
            $looksEncrypted = is_string($rawHistorySample) && strlen($rawHistorySample) > 100 && ! str_contains($rawHistorySample, 'discord.com/api/webhooks/');
            $checks[] = [
                'name'   => 'Webhook URLs encrypted at rest',
                'status' => $looksEncrypted ? 'ok' : 'warn',
                'detail' => $looksEncrypted
                    ? 'Raw webhook_url column appears encrypted (no plain URL visible)'
                    : 'Webhook URL looks unencrypted in the DB — run migration 000012 to backfill, then verify',
            ];
        }

        $passed   = collect($checks)->where('status', 'ok')->count();
        $warnings = collect($checks)->where('status', 'warn')->count();
        $failures = collect($checks)->where('status', 'error')->count();

        return compact('checks', 'passed', 'warnings', 'failures');
    }

    /**
     * Tab 3 — System Validation. Verify hardcoded constants + required
     * dependencies. Distinct from Master Test which checks runtime state.
     */
    private function buildSystemValidation(): array
    {
        $items = [];

        // PHP version (composer.json requires ^8.0)
        $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
        $items[] = [
            'name'   => 'PHP version >= 8.0',
            'value'  => PHP_VERSION,
            'status' => $phpOk ? 'ok' : 'error',
        ];

        // Required SeAT classes
        foreach ([
            'Seat\Services\AbstractSeatPlugin'        => 'required SeAT plugin base class',
            'Seat\Web\Models\Acl\Role'                => 'required SeAT ACL Role model',
            'Seat\Services\Seeding\AbstractScheduleSeeder' => 'required SeAT schedule seeder',
        ] as $class => $note) {
            $present = class_exists($class);
            $items[] = [
                'name'   => $class,
                'value'  => $present ? 'present' : 'MISSING',
                'status' => $present ? 'ok' : 'error',
                'note'   => $note,
            ];
        }

        // Optional integrations
        foreach ([
            '\ManagerCore\Services\EventBus'                              => 'Manager Core EventBus (enables structure-timer + mining-extraction ingest plus pre-event pings)',
            '\ManagerCore\Services\PluginBridge'                          => 'Manager Core PluginBridge (capability registry)',
            '\ManagerCore\Topics'                                         => 'Manager Core Topics facade (used by Pings for publishing pings.* events)',
            '\StructureManager\Services\TimerEventPublisher'              => 'Structure Manager event publisher (source of structure_manager.timer.* events)',
            '\MiningManager\Services\Events\MoonExtractionEventPublisher' => 'Mining Manager event publisher (source of mining.extraction_* events, requires MM v2.0.1+)',
            'GuzzleHttp\Client'                                           => 'Guzzle HTTP client (required for Discord delivery)',
        ] as $class => $note) {
            $present = class_exists($class);
            $items[] = [
                'name'   => $class,
                'value'  => $present ? 'present' : 'absent',
                'status' => $class === 'GuzzleHttp\Client' ? ($present ? 'ok' : 'error') : 'info',
                'note'   => $note,
            ];
        }

        // Plugin version label
        $items[] = [
            'name'   => 'Plugin version (config)',
            'value'  => config('discordpings.version', 'unknown'),
            'status' => 'info',
            'note'   => 'discordpings.config.php :: version',
        ];

        return $items;
    }

    /**
     * Tab 4 — Settings Health. List every plugin setting with its current
     * value, default, and source. Catches drift between config defaults
     * and DB overrides.
     */
    private function buildSettingsHealth(): array
    {
        $rows = [];

        // Config-only settings
        $rows[] = ['key' => 'structure_events.enabled',         'value' => config('discordpings.structure_events.enabled', true) ? 'true' : 'false', 'default' => 'true', 'source' => 'config file',          'note' => 'Structure-timer EventBus subscription master switch (config-level)'];
        $rows[] = ['key' => 'structure_events.retention_days',  'value' => config('discordpings.structure_events.retention_days', 14),                'default' => 14,     'source' => 'config file',          'note' => 'Days to keep resolved tactical events'];
        $rows[] = ['key' => 'structure_events.formup_offset_minutes (default)', 'value' => config('discordpings.structure_events.formup_offset_minutes', 30), 'default' => 30, 'source' => 'config file', 'note' => 'Default form-up lead time (overridable below)'];
        $rows[] = ['key' => 'mining_events.enabled',            'value' => config('discordpings.mining_events.enabled', true) ? 'true' : 'false',    'default' => 'true', 'source' => 'config file',          'note' => 'Mining-extraction EventBus subscription master switch (config-level)'];
        $rows[] = ['key' => 'history_retention_days',           'value' => config('discordpings.history_retention_days', 90),                          'default' => 90,     'source' => 'config file',          'note' => 'Days to keep PingHistory rows'];
        $rows[] = ['key' => 'rate_limit.enabled',               'value' => config('discordpings.rate_limit.enabled', true) ? 'true' : 'false',         'default' => 'true', 'source' => 'config file',          'note' => 'Per-webhook rate limiter'];
        $rows[] = ['key' => 'rate_limit.max_per_minute',        'value' => config('discordpings.rate_limit.max_per_minute', 10),                       'default' => 10,     'source' => 'config file',          'note' => 'Per-webhook per-minute cap'];
        $rows[] = ['key' => 'rate_limit.max_per_hour',          'value' => config('discordpings.rate_limit.max_per_hour', 100),                        'default' => 100,    'source' => 'config file',          'note' => 'Per-webhook per-hour cap'];
        $rows[] = ['key' => 'max_scheduled_per_user',           'value' => config('discordpings.max_scheduled_per_user', 50),                          'default' => 50,     'source' => 'config file',          'note' => 'Per-user cap on active scheduled pings'];
        $rows[] = ['key' => 'app_name',                         'value' => config('discordpings.app_name', 'SeAT Broadcast'),                          'default' => 'SeAT Broadcast', 'source' => 'config file', 'note' => 'Discord embed username'];

        // DB overrides
        $rows[] = ['key' => 'structure_alerts_enabled (DB)',      'value' => PluginSetting::getBool('structure_alerts_enabled', true) ? 'true' : 'false', 'default' => 'true', 'source' => 'discord_settings table', 'note' => 'Structure-timer master pings toggle (UI editable on Settings > Structure Timers)'];
        $rows[] = ['key' => 'mining_alerts_enabled (DB)',         'value' => PluginSetting::getBool('mining_alerts_enabled', true) ? 'true' : 'false',    'default' => 'true', 'source' => 'discord_settings table', 'note' => 'Mining-extraction master alerts toggle (UI editable on Settings > Structure Timers)'];
        $rows[] = ['key' => 'formup_offset_minutes (DB override)', 'value' => PluginSetting::getValue('formup_offset_minutes', '(inherits config)'),     'default' => 'inherits config', 'source' => 'discord_settings table', 'note' => 'UI override on Settings > Structure Timers tab'];

        return $rows;
    }

    /**
     * Tab 5 — Data Integrity. Row counts per table + orphan / consistency
     * checks. Catches schema drift, FK breakage, and stale data.
     */
    private function buildDataIntegrity(): array
    {
        $tables = [];
        foreach ([
            'discord_webhooks'           => 'Webhooks',
            'discord_ping_histories'     => 'Broadcast history',
            'discord_scheduled_pings'    => 'Scheduled pings',
            'discord_tactical_events'    => 'Tactical events (ingested)',
            'discord_settings'           => 'UI settings (key-value)',
            'discord_roles'              => 'Discord roles',
            'discord_channels'           => 'Discord channels',
            'discord_staging_locations'  => 'Staging locations',
            'discord_pap_types'          => 'PAP types',
            'discord_webhook_roles'      => 'Webhook ↔ role pivot',
            'discord_pings_templates'    => 'Ping templates',
        ] as $table => $label) {
            $exists = Schema::hasTable($table);
            $rows   = null;
            if ($exists) {
                try {
                    $rows = DB::table($table)->count();
                } catch (\Throwable $e) {
                    $rows = null;
                }
            }
            $tables[] = [
                'table'  => $table,
                'label'  => $label,
                'exists' => $exists,
                'rows'   => $rows,
            ];
        }

        $issues = [];

        // Orphan: history rows pointing at deleted webhooks (FK has
        // onDelete=cascade, so this should always be 0 in practice).
        if (Schema::hasTable('discord_ping_histories') && Schema::hasTable('discord_webhooks')) {
            try {
                $orphans = DB::table('discord_ping_histories')
                    ->leftJoin('discord_webhooks', 'discord_ping_histories.webhook_id', '=', 'discord_webhooks.id')
                    ->whereNull('discord_webhooks.id')
                    ->count();
                if ($orphans > 0) {
                    $issues[] = ['severity' => 'warn', 'detail' => "{$orphans} discord_ping_histories rows reference deleted webhooks (expected 0 — FK has onDelete cascade)"];
                }
            } catch (\Throwable $e) {
                // skip — table might be in an odd state
            }
        }

        // Stale resolved tactical events past retention.
        if (Schema::hasTable('discord_tactical_events')) {
            $retention = (int) config('discordpings.structure_events.retention_days', 14);
            try {
                $stale = TacticalEvent::whereIn('status', ['dismissed', 'elapsed'])
                    ->where('updated_at', '<', Carbon::now()->subDays(max(1, $retention)))
                    ->count();
                if ($stale > 0) {
                    $issues[] = ['severity' => 'info', 'detail' => "{$stale} resolved tactical events past the {$retention}-day retention window — will be pruned by the next discordpings:cleanup-history run"];
                }
            } catch (\Throwable $e) {
                // skip
            }
        }

        // Per-category tactical event breakdown (informational, helps
        // operators see which integrations are actively ingesting data).
        if (Schema::hasTable('discord_tactical_events')) {
            try {
                $categoryCounts = TacticalEvent::active()
                    ->selectRaw('category_group, COUNT(*) as cnt')
                    ->groupBy('category_group')
                    ->pluck('cnt', 'category_group')
                    ->toArray();
                $categoryDetail = collect($categoryCounts)
                    ->map(fn ($cnt, $cat) => ($cat ?: 'uncategorized') . ': ' . $cnt)
                    ->implode(', ');
                if (! empty($categoryDetail)) {
                    $issues[] = ['severity' => 'info', 'detail' => "Active tactical events by category: {$categoryDetail}"];
                }
            } catch (\Throwable $e) {
                // skip
            }
        }

        // Stale mining ops still flagged active past their eve_time. The
        // mining.extraction_expired event from MM should flip these to
        // 'elapsed'. If a backlog builds up, either the MM scanner cron
        // isn't running or the MC EventBus isn't delivering.
        if (Schema::hasTable('discord_tactical_events')) {
            try {
                $staleMining = TacticalEvent::where('category_group', 'mining')
                    ->where('status', 'active')
                    ->whereNotNull('eve_time')
                    ->where('eve_time', '<', Carbon::now()->subHours(3))
                    ->count();
                if ($staleMining > 0) {
                    $issues[] = ['severity' => 'warn', 'detail' => "{$staleMining} mining extraction(s) still flagged 'active' more than 3 hours past their window-closes time — Mining Manager's scan-extraction-events cron (or the MC EventBus delivery) may be stalled"];
                }
            } catch (\Throwable $e) {
                // skip
            }
        }

        // Failed scheduled pings still flagged active (after the audit fix
        // these should auto-deactivate; a build-up suggests cron is wedged).
        if (Schema::hasTable('discord_scheduled_pings')) {
            try {
                $stuckActive = ScheduledPing::due()
                    ->where('scheduled_at', '<', Carbon::now()->subMinutes(15))
                    ->count();
                if ($stuckActive > 0) {
                    $issues[] = ['severity' => 'error', 'detail' => "{$stuckActive} scheduled pings overdue by > 15 minutes still flagged active — Laravel scheduler / queue worker may be down"];
                }
            } catch (\Throwable $e) {
                // skip
            }
        }

        return compact('tables', 'issues');
    }

    /**
     * Tab 6 — Broadcast Trace. The plugin-specific Tier 3 tab. Pick a
     * scheduled ping or tactical event and walk its dispatch pipeline,
     * showing what would happen (and what already did).
     */
    private function buildBroadcastTrace(string $kind, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        if ($kind === 'tactical') {
            return $this->traceTacticalEvent($id);
        }

        return $this->traceScheduledPing($id);
    }

    private function traceScheduledPing(int $id): array
    {
        $ping = ScheduledPing::with('webhook')->find($id);
        if (! $ping) {
            return ['kind' => 'scheduled', 'id' => $id, 'found' => false];
        }

        $steps = [];
        $steps[] = ['label' => 'Row found',             'status' => 'ok',    'detail' => "discord_scheduled_pings#{$ping->id} created " . optional($ping->created_at)->diffForHumans()];
        $steps[] = ['label' => 'Webhook resolved',      'status' => $ping->webhook ? 'ok' : 'error', 'detail' => $ping->webhook ? "discord_webhooks#{$ping->webhook->id} ({$ping->webhook->name})" : 'webhook_id points at a deleted webhook'];
        $steps[] = ['label' => 'is_active flag',        'status' => $ping->is_active ? 'ok' : 'info',  'detail' => $ping->is_active ? 'true (eligible for dispatch)' : 'false (already sent / deactivated)'];
        $steps[] = ['label' => 'scheduled_at',          'status' => 'info',  'detail' => (string) $ping->scheduled_at . ' UTC'];
        $steps[] = ['label' => 'repeat_interval',       'status' => 'info',  'detail' => $ping->repeat_interval ?: 'one-time'];
        $steps[] = ['label' => 'tactical_event_id link', 'status' => 'info', 'detail' => $ping->tactical_event_id ? "linked to discord_tactical_events#{$ping->tactical_event_id}" : 'manual broadcast (not linked to an op)'];

        $isDue = $ping->is_active
            && $ping->scheduled_at <= Carbon::now()
            && (! $ping->repeat_until || $ping->repeat_until >= Carbon::now());
        $steps[] = ['label' => 'Currently in due() scope?', 'status' => $isDue ? 'ok' : 'info', 'detail' => $isDue ? 'yes — would be picked up by the next cron tick' : 'no'];

        // Recent history rows attributed to this ping (by user_id + webhook_id heuristic, since
        // history doesn't carry a scheduled_ping_id FK).
        $recentHistory = PingHistory::where('webhook_id', $ping->webhook_id)
            ->where('user_id', $ping->user_id)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'status', 'created_at', 'error_message']);

        return [
            'kind'    => 'scheduled',
            'id'      => $id,
            'found'   => true,
            'ping'    => $ping,
            'steps'   => $steps,
            'history' => $recentHistory,
        ];
    }

    private function traceTacticalEvent(int $id): array
    {
        if (! Schema::hasTable('discord_tactical_events')) {
            return ['kind' => 'tactical', 'id' => $id, 'found' => false];
        }

        $event = TacticalEvent::with('scheduledPings')->find($id);
        if (! $event) {
            return ['kind' => 'tactical', 'id' => $id, 'found' => false];
        }

        $isMining = $event->category_group === 'mining';

        $steps = [];
        $steps[] = ['label' => 'Row found',          'status' => 'ok',   'detail' => "discord_tactical_events#{$event->id} ingested " . optional($event->created_at)->diffForHumans()];
        $steps[] = ['label' => 'source_plugin',      'status' => 'info', 'detail' => $event->source_plugin];
        $steps[] = ['label' => 'category_group',     'status' => 'info', 'detail' => ($event->category_group ?: 'uncategorized') . ($isMining ? ' (mining lifecycle — different render + alert pipeline)' : '')];
        $steps[] = ['label' => $isMining ? 'extraction_id (MM)' : 'external_timer_id',  'status' => 'info', 'detail' => (string) $event->external_timer_id];
        $steps[] = ['label' => 'event_type',         'status' => 'info', 'detail' => $event->event_type . ($event->is_manual ? ' (manual op)' : ' (auto-detected)')];
        $steps[] = ['label' => 'severity',           'status' => 'info', 'detail' => $event->severity];
        $steps[] = ['label' => 'status',             'status' => $event->status === 'active' ? 'ok' : 'info', 'detail' => $event->status];
        $steps[] = ['label' => $isMining ? 'window_closes_at (eve_time)' : 'eve_time',           'status' => 'info', 'detail' => $event->eve_time ? (string) $event->eve_time . ' UTC' : '(none)'];
        $steps[] = ['label' => 'corporation_id (visibility)', 'status' => 'info', 'detail' => $event->corporation_id ? (string) $event->corporation_id : 'global (all corps see it)'];
        $steps[] = ['label' => 'last_seen_at',       'status' => 'info', 'detail' => $event->last_seen_at ? (string) $event->last_seen_at . ' UTC' : '(never)'];

        // Pre-event ping latches. Mining ops only use pinged_1h_at (as the
        // single T-2h alert latch); structure timers use both T-24h and T-1h.
        if ($isMining) {
            $steps[] = ['label' => 'T-2h alert latch (pinged_1h_at column)', 'status' => 'info', 'detail' => $event->pinged_1h_at ? "fired " . (string) $event->pinged_1h_at : 'not yet fired'];
        } else {
            $steps[] = ['label' => 'T-24h ping latch',   'status' => 'info', 'detail' => $event->pinged_24h_at ? "fired " . (string) $event->pinged_24h_at : 'not yet fired'];
            $steps[] = ['label' => 'T-1h ping latch',    'status' => 'info', 'detail' => $event->pinged_1h_at ? "fired " . (string) $event->pinged_1h_at : 'not yet fired'];
        }

        // Webhooks that WOULD receive an alert for this event right now.
        // Branches on category_group so mining ops check the mining flag.
        $eventCorpId = $event->corporation_id;
        $miningCol   = Schema::hasColumn('discord_webhooks', 'receives_mining_alerts');

        if ($isMining && $miningCol) {
            $candidates = DiscordWebhook::where('is_active', true)
                ->where('receives_mining_alerts', true)
                ->where(function ($q) use ($eventCorpId) {
                    $q->whereNull('corporation_id');
                    if ($eventCorpId !== null) {
                        $q->orWhere('corporation_id', $eventCorpId);
                    }
                })
                ->get(['id', 'name', 'corporation_id']);
        } else {
            $candidates = DiscordWebhook::where('is_active', true)
                ->where('receives_structure_alerts', true)
                ->where(function ($q) use ($eventCorpId) {
                    $q->whereNull('corporation_id');
                    if ($eventCorpId !== null) {
                        $q->orWhere('corporation_id', $eventCorpId);
                    }
                })
                ->get(['id', 'name', 'corporation_id']);
        }

        // Linked scheduled pings (formup broadcasts)
        $linked = $event->scheduledPings;

        return [
            'kind'       => 'tactical',
            'id'         => $id,
            'found'      => true,
            'event'      => $event,
            'isMining'   => $isMining,
            'steps'      => $steps,
            'candidates' => $candidates,
            'linked'     => $linked,
        ];
    }
}
