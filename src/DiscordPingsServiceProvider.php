<?php

namespace DiscordPings;

use Seat\Services\AbstractSeatPlugin;
use DiscordPings\Console\Commands\ProcessScheduledPings;
use DiscordPings\Console\Commands\CleanupPingHistory;

class DiscordPingsServiceProvider extends AbstractSeatPlugin
{
    public function boot()
    {
        // Check if routes are cached before loading
        if (!$this->app->routesAreCached()) {
            include __DIR__ . '/Http/routes.php';
        }
        
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang/', 'discordpings');
        $this->loadViewsFrom(__DIR__ . '/resources/views/', 'discordpings');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations/');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessScheduledPings::class,
                CleanupPingHistory::class,
            ]);
        }

        // Add publications
        $this->add_publications();
        
        // Add database seeders
        $this->add_database_seeders();

        // Subscribe to Structure Manager timer events via Manager Core (optional).
        $this->registerStructureTimerSubscription();

        // Subscribe to Mining Manager extraction lifecycle events via Manager
        // Core (optional). Shares the same registerSelf bootstrap as the
        // timer subscription, so we only need to declare the capability +
        // subscribe pattern here.
        $this->registerMiningExtractionSubscription();
    }

    /**
     * Add content which must be published.
     */
    private function add_publications()
    {
        $this->publishes([
            __DIR__ . '/Config/discordpings.config.php' => config_path('discordpings.php'),
        ], ['config', 'seat']);

        $this->publishes([
            __DIR__ . '/resources/assets' => public_path('vendor/discordpings'),
        ], ['public', 'seat']);
    }

    /**
     * Register database seeders
     */
    private function add_database_seeders()
    {
        $this->publishes([
            __DIR__ . '/database/seeders/' => database_path('seeders/'),
        ], ['seeders', 'seat']);
    }

    /**
     * Subscribe to Structure Manager's `structure_manager.timer.*` events
     * through Manager Core's EventBus. Ingested timers surface on the
     * Broadcasts Calendar and fire pre-timer reminder pings.
     *
     * Entirely optional: when Manager Core is not installed this is a silent
     * no-op and the plugin behaves exactly as a standalone webhook sender.
     */
    private function registerStructureTimerSubscription()
    {
        if (! config('discordpings.structure_events.enabled', true)) {
            return;
        }

        if (! class_exists('\\ManagerCore\\Services\\PluginBridge')
            || ! class_exists('\\ManagerCore\\Services\\EventBus')) {
            return;
        }

        try {
            $bridge = $this->app->make(\ManagerCore\Services\PluginBridge::class);

            // Self-register with the plugin bridge so MC's diagnostic shows
            // us as a live integration (state, last_seen_at, etc.). Plugins
            // not in MC's hardcoded `compatible_plugins` config need this —
            // without it MC sees the subscription + capability but classifies
            // the plugin "Offline / Last Seen: Never". method_exists-guarded
            // for MC versions that predate registerSelf.
            if (method_exists($bridge, 'registerSelf')) {
                $bridge->registerSelf('seat-discord-pings', [
                    'name'    => 'SeAT Broadcast',
                    'class'   => \DiscordPings\DiscordPingsServiceProvider::class,
                    'package' => 'mattfalahe/seat-discord-pings',
                    'icon'    => 'fa-bullhorn',
                ]);
            }

            // Expose the handler as a PluginBridge capability so Manager Core's
            // EventBus can dispatch to it through the standard capability path.
            $bridge->registerCapability(
                'seat-discord-pings',
                'structure.timer.ingest',
                function (string $eventName, string $publisher, array $payload) {
                    app(\DiscordPings\Handlers\StructureTimerHandler::class)
                        ->handle($eventName, $publisher, $payload);
                }
            );

            // Persistent wildcard subscription — updateOrCreate-backed inside
            // Manager Core, so registering on every boot is safe.
            $eventBus = $this->app->make(\ManagerCore\Services\EventBus::class);
            $eventBus->subscribe(
                'seat-discord-pings',
                'structure_manager.timer.*',
                'structure.timer.ingest',
                ['queued' => false, 'priority' => 0]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                '[DiscordPings] Structure timer EventBus subscription failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Subscribe to Mining Manager's `mining.extraction_*` events through
     * Manager Core's EventBus. Ingested extractions surface on the
     * Broadcasts Calendar and FC Opportunities board as the `mining`
     * category, and fire a single pre-expiry alert (T-2h) to webhooks
     * flagged with `receives_mining_alerts`.
     *
     * Entirely optional: when Manager Core is not installed (or Mining
     * Manager isn't publishing) this is a silent no-op.
     */
    private function registerMiningExtractionSubscription()
    {
        if (! config('discordpings.mining_events.enabled', true)) {
            return;
        }

        if (! class_exists('\\ManagerCore\\Services\\PluginBridge')
            || ! class_exists('\\ManagerCore\\Services\\EventBus')) {
            return;
        }

        try {
            $bridge = $this->app->make(\ManagerCore\Services\PluginBridge::class);

            $bridge->registerCapability(
                'seat-discord-pings',
                'mining.extraction.ingest',
                function (string $eventName, string $publisher, array $payload) {
                    app(\DiscordPings\Handlers\MiningExtractionHandler::class)
                        ->handle($eventName, $publisher, $payload);
                }
            );

            $eventBus = $this->app->make(\ManagerCore\Services\EventBus::class);
            $eventBus->subscribe(
                'seat-discord-pings',
                'mining.extraction_*',
                'mining.extraction.ingest',
                ['queued' => false, 'priority' => 0]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                '[DiscordPings] Mining extraction EventBus subscription failed: ' . $e->getMessage()
            );
        }
    }

    public function register()
    {
        // Register sidebar configuration with correct path
        $this->mergeConfigFrom(__DIR__ . '/Config/Menu/package.sidebar.php', 'package.sidebar');
        
        // Register permissions
        $this->registerPermissions(__DIR__ . '/Config/Permissions/discordpings.permissions.php', 'discordpings');
        
        // Register config
        $this->mergeConfigFrom(__DIR__.'/Config/discordpings.config.php', 'discordpings');
    }

    public function getName(): string
    {
        return 'SeAT Broadcast';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/MattFalahe/seat-discord-pings';
    }

    public function getPackagistPackageName(): string
    {
        return 'seat-discord-pings';
    }

    public function getPackagistVendorName(): string
    {
        return 'mattfalahe';
    }
}
