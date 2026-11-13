<?php

// Build entries dynamically so MC-dependent menu items only appear when
// Manager Core is present. The route itself still exists either way —
// anyone with a deep-link bookmark gets the empty-state page that invites
// them to install it.
$entries = [
    [
        'name'  => 'Ping Requests',
        'label' => 'discordpings::menu.ping_requests',
        'icon'  => 'fas fa-hand-paper',
        'route' => 'discordpings.requests',
        'permission' => 'discordpings.line_member',
    ],
    [
        'name'  => 'Send Ping',
        'label' => 'discordpings::menu.send_ping',
        'icon'  => 'fas fa-paper-plane',
        'route' => 'discordpings.send',
        'permission' => 'discordpings.send',
    ],
    [
        'name'  => 'History',
        'label' => 'discordpings::menu.history',
        'icon'  => 'fas fa-history',
        'route' => 'discordpings.history',
        'permission' => 'discordpings.view_history',
    ],
    // Scheduled + Calendar + FC Opportunities are all "FC tier" surfaces —
    // gated by discordpings.send. Tier 1 users see only their own pings;
    // tier 2 (manage_scheduled) users see everyone's. Both tiers see the
    // global "📡 N scheduled" count on FC Opportunities (the coordination
    // signal), which is by design — operators avoid duplicate effort even
    // when they can't see the actual underlying pings.
    [
        'name'  => 'Scheduled',
        'label' => 'discordpings::menu.scheduled',
        'icon'  => 'fas fa-clock',
        'route' => 'discordpings.scheduled',
        'permission' => 'discordpings.send',
    ],
    [
        'name'  => 'Calendar',
        'label' => 'discordpings::menu.calendar',
        'icon'  => 'fas fa-calendar-alt',
        'route' => 'discordpings.scheduled.calendar',
        'permission' => 'discordpings.send',
    ],
];

// FC Opportunities — shown whenever Manager Core is installed. The board's
// ops arrive through MC's EventBus: structure timers from Structure
// Manager, moon extractions from Mining Manager, either one being enough.
// Without MC the entry stays hidden. NOTE: if the install runs
// `php artisan config:cache`, clear it after installing MC so the new
// entry appears.
if (class_exists('\ManagerCore\Services\EventBus')) {
    $entries[] = [
        'name'  => 'FC Opportunities',
        'label' => 'discordpings::menu.opportunities',
        'icon'  => 'fas fa-bullseye',
        'route' => 'discordpings.opportunities',
        'permission' => 'discordpings.send',
    ];
}

$entries = array_merge($entries, [
    [
        'name'  => 'Templates',
        'label' => 'discordpings::menu.templates',
        'icon'  => 'fas fa-file-alt',
        'route' => 'discordpings.templates',
        'permission' => 'discordpings.manage_templates',
    ],
    [
        'name'  => 'Settings',
        'label' => 'discordpings::menu.discord_config',
        'icon'  => 'fas fa-cog',
        'route' => 'discordpings.config',
        'permission' => 'discordpings.manage_webhooks',
    ],
    [
        'name'  => 'Help',
        'label' => 'discordpings::menu.help',
        'icon'  => 'fas fa-question-circle',
        'route' => 'discordpings.help',
        'permission' => 'discordpings.view',
    ],
]);

return [
    'discordpings' => [
        'name'          => 'Discord Pings',
        'label'         => 'discordpings::menu.main_level',
        'plural'        => true,
        'icon'          => 'fas fa-bullhorn',
        'route_segment' => 'discord-pings',
        'permission'    => 'discordpings.view',
        'entries'       => $entries,
    ]
];
