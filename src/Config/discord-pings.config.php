<?php

return [
    'version' => '1.0.0',
    
    // Rate limiting
    'rate_limit' => [
        'enabled' => true,
        'max_per_minute' => 10,
        'max_per_hour' => 100,
    ],
    
    // Discord settings
    'discord' => [
        'default_color' => '#5865F2',
        'default_username' => 'SeAT Fleet Pings',
        'default_avatar' => 'https://seat-plus.net/img/favicon.png',
        'max_embed_fields' => 25,
        'max_message_length' => 2000,
    ],
    
    // Scheduling
    'scheduling' => [
        'max_scheduled_per_user' => 50,
        'max_repeat_months' => 12,
        'allowed_intervals' => ['hourly', 'daily', 'weekly', 'monthly'],
    ],
    
    // History
    'history' => [
        'retention_days' => 90, // Auto-delete after X days
        'max_resends_per_day' => 20,
    ],
    
    // Default templates
    'templates' => [
        [
            'name' => 'Standard CTA',
            'message' => 'Many hands make light work, the more people join the faster we\'re done!',
            'pap_type' => 'Strategic',
        ],
        [
            'name' => 'Emergency',
            'message' => 'EMERGENCY! Hostiles in staging! All hands on deck NOW!',
            'pap_type' => 'CTA',
        ],
        [
            'name' => 'Mining Op',
            'message' => 'Mining fleet forming! Boosts available. Join for some chill mining.',
            'pap_type' => 'Peacetime',
        ],
        [
            'name' => 'Roam Fleet',
            'message' => 'Roam fleet forming in 30 minutes. Kitchen sink doctrine, bring what you can fly!',
            'pap_type' => 'Peacetime',
        ],
        [
            'name' => 'Strategic Op',
            'message' => 'STRATOP! Maximum numbers needed. This is a critical timer. PAPs will be given.',
            'pap_type' => 'Strategic',
        ],
    ],
];
