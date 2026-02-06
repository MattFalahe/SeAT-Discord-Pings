<?php

namespace MattFalahe\Seat\DiscordPings;

use Seat\Services\AbstractSeatPlugin;
use MattFalahe\Seat\DiscordPings\Console\Commands\ProcessScheduledPings;
use MattFalahe\Seat\DiscordPings\Console\Commands\CleanupPingHistory;
use MattFalahe\Seat\DiscordPings\Console\Commands\SetupPermissionsCommand;

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
                SetupPermissionsCommand::class,
            ]);
        }

        // Add publications
        $this->add_publications();
        
        // Add database seeders
        $this->add_database_seeders();
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
        return 'Discord Pings';
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
