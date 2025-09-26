<?php

return [
    'version' => '1.0.2',
    
    // Default webhook settings
    'default_embed_color' => '#5865F2',
    'default_username' => 'SeAT Fleet Pings',
    'default_avatar' => null,
    
    // Rate limiting
    'rate_limit' => [
        'enabled' => true,
        'max_per_minute' => 10,
        'max_per_hour' => 100,
    ],
    
    // History retention
    'history_retention_days' => 90,
    
    // Scheduling
    'max_scheduled_per_user' => 50,
    
    // Integrations
    'integrations' => [
        'seat_fitting' => [
            'enabled' => true, // Set to false to disable seat-fitting integration
            'link_doctrines' => true, // Create clickable links to doctrine fittings
        ],
    ],
    
    // Templates
    'default_templates' => [
        'standard_cta' => 'Many hands make light work, the more people join the faster we\'re done!',
        'emergency' => 'EMERGENCY! Hostiles in staging! All hands on deck NOW!',
        'mining_op' => 'Mining fleet forming! Boosts available. Join for some chill mining.',
        'roam_fleet' => 'Roam fleet forming in 30 minutes. Kitchen sink doctrine, bring what you can fly!',
        'strategic_op' => 'STRATOP! Maximum numbers needed. This is a critical timer. PAPs will be given.',
    ],
];
