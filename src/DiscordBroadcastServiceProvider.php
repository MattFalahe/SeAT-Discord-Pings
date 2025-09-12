<?php

namespace YourName\Seat\DiscordBroadcast;

use Seat\Services\AbstractSeatPlugin;
use YourName\Seat\DiscordBroadcast\Jobs\SendScheduledBroadcast;

class DiscordBroadcastServiceProvider extends AbstractSeatPlugin
{
    public function boot()
    {
        $this->addRoutes();
        $this->addViews();
        $this->addMigrations();
        $this->addTranslations();
        $this->addPermissions();
        $this->addMenuItem();
        
        // Register scheduled job
        $this->registerScheduledJobs();
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/discord-broadcast.config.php', 'discord-broadcast'
        );
    }

    private function addRoutes()
    {
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
    }

    private function addViews()
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'discord-broadcast');
    }

    private function addMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    private function addTranslations()
    {
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'discord-broadcast');
    }

    private function addPermissions()
    {
        $this->registerPermissions(__DIR__ . '/Config/Permissions/discord-broadcast.permissions.php', 'discord-broadcast');
    }

    private function addMenuItem()
    {
        $this->mergeMenuItems([
            'tools' => [
                'discord_broadcast' => [
                    'name' => 'Discord Broadcast',
                    'icon' => 'fas fa-bullhorn',
                    'route_segment' => 'discord-broadcast',
                    'entries' => [
                        [
                            'name' => 'Send Broadcast',
                            'icon' => 'fas fa-paper-plane',
                            'route' => 'discord.broadcast.view',
                            'permission' => 'discord.broadcast.send'
                        ],
                        [
                            'name' => 'Scheduled',
                            'icon' => 'fas fa-clock',
                            'route' => 'discord.scheduled.index',
                            'permission' => 'discord.scheduled.view'
                        ],
                        [
                            'name' => 'History',
                            'icon' => 'fas fa-history',
                            'route' => 'discord.history.index',
                            'permission' => 'discord.history.view'
                        ],
                        [
                            'name' => 'Webhooks',
                            'icon' => 'fas fa-link',
                            'route' => 'discord.webhooks.index',
                            'permission' => 'discord.webhooks.manage'
                        ]
                    ]
                ]
            ]
        ]);
    }

    private function registerScheduledJobs()
    {
        $schedule = app()->make(\Illuminate\Console\Scheduling\Schedule::class);
        $schedule->job(new SendScheduledBroadcast)->everyMinute();
    }

    public function getName(): string
    {
        return 'Discord Broadcast';
    }

    public function getPackagistPackageName(): string
    {
        return 'yourname/seat-discord-broadcast';
    }

    public function getVersion(): string
    {
        return config('discord-broadcast.config.version');
    }
}
