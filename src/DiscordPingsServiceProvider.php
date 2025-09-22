<?php

namespace MattFalahe\Seat\DiscordPings;

use Seat\Services\AbstractSeatPlugin;
use MattFalahe\Seat\DiscordPings\Jobs\SendScheduledPing;

class DiscordPingsServiceProvider extends AbstractSeatPlugin
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->addRoutes();
        $this->addViews();
        $this->addMigrations();
        $this->addTranslations();
        $this->addPermissions();
        $this->addMenu();
        $this->registerScheduledJobs();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/discord-pings.config.php', 'discord-pings'
        );
    }

    /**
     * Add routes
     */
    private function addRoutes()
    {
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
    }

    /**
     * Add views
     */
    private function addViews()
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'discord-pings');
    }

    /**
     * Add migrations
     */
    private function addMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
     * Add translations
     */
    private function addTranslations()
    {
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'discord-pings');
    }

    /**
     * Add permissions
     */
    private function addPermissions()
    {
        $this->registerPermissions(__DIR__ . '/Config/Permissions/discord-pings.permissions.php', 'discord-pings');
    }

    /**
     * Add menu items
     */
    private function addMenu()
    {
        // Register menu configuration
        $this->mergeMenuFrom(__DIR__ . '/Config/Menu/discord-pings.menu.php');
    }

    /**
     * Register scheduled jobs
     */
    private function registerScheduledJobs()
    {
        $schedule = app()->make(\Illuminate\Console\Scheduling\Schedule::class);
        $schedule->job(new SendScheduledPing)->everyMinute();
    }

    /**
     * Get the name of the plugin
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Discord Pings';
    }

    /**
     * Get the packagist vendor/package name
     *
     * @return string
     */
    public function getPackagistPackageName(): string
    {
        return 'mattfalahe/seat-discord-pings';
    }

    /**
     * Get the version of the plugin
     *
     * @return string
     */
    public function getVersion(): string
    {
        return config('discord-pings.config.version');
    }
}
