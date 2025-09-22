<?php

return [
    [
        'name' => 'discord-pings',
        'label' => 'Discord Pings',
        'permission' => 'discord.pings.send',
        'highlight_view' => 'discord-pings',
        'route' => 'discord.pings.view',
        'icon' => 'fas fa-bullhorn',
    ],
    [
        'name' => 'discord-pings-scheduled',
        'label' => 'Scheduled Pings',
        'permission' => 'discord.pings.scheduled.view',
        'highlight_view' => 'discord-pings',
        'route' => 'discord.pings.scheduled.index',
        'icon' => 'fas fa-clock',
    ],
    [
        'name' => 'discord-pings-history',
        'label' => 'Ping History',
        'permission' => 'discord.pings.history.view',
        'highlight_view' => 'discord-pings',
        'route' => 'discord.pings.history.index',
        'icon' => 'fas fa-history',
    ],
    [
        'name' => 'discord-pings-webhooks',
        'label' => 'Discord Webhooks',
        'permission' => 'discord.pings.webhooks.manage',
        'highlight_view' => 'discord-pings',
        'route' => 'discord.pings.webhooks.index',
        'icon' => 'fas fa-link',
    ],
];
