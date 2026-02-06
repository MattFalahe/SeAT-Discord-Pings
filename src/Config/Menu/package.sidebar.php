<?php

return [
    'discordpings' => [
        'name'          => 'Discord Pings',
        'label'         => 'discordpings::menu.main_level',
        'plural'        => true,
        'icon'          => 'fas fa-bullhorn',
        'route_segment' => 'discord-pings',
        'permission'    => 'discordpings.view',
        'entries'       => [
            [
                'name'  => 'Send Ping',
                'label' => 'discordpings::menu.send_ping',
                'icon'  => 'fas fa-paper-plane',
                'route' => 'discordpings.send',
                'permission' => 'discordpings.send',
            ],
            [
                'name'  => 'Discord Config',
                'label' => 'discordpings::menu.discord_config',
                'icon'  => 'fab fa-discord',
                'route' => 'discordpings.config',
                'permission' => 'discordpings.manage_webhooks',
            ],
            [
                'name'  => 'History',
                'label' => 'discordpings::menu.history',
                'icon'  => 'fas fa-history',
                'route' => 'discordpings.history',
                'permission' => 'discordpings.view_history',
            ],
            [
                'name'  => 'Scheduled',
                'label' => 'discordpings::menu.scheduled',
                'icon'  => 'fas fa-clock',
                'route' => 'discordpings.scheduled',
                'permission' => 'discordpings.manage_scheduled',
            ],
            [
                'name'  => 'Calendar',
                'label' => 'discordpings::menu.calendar',
                'icon'  => 'fas fa-calendar-alt',
                'route' => 'discordpings.scheduled.calendar',
                'permission' => 'discordpings.manage_scheduled',
            ],
            [
                'name'  => 'Templates',
                'label' => 'discordpings::menu.templates',
                'icon'  => 'fas fa-file-alt',
                'route' => 'discordpings.templates',
                'permission' => 'discordpings.manage_templates',
            ],
            [
                'name'  => 'Help',
                'label' => 'discordpings::menu.help',
                'icon'  => 'fas fa-question-circle',
                'route' => 'discordpings.help',
                'permission' => 'discordpings.view',
            ],
        ]
    ]
];
