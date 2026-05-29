<?php

return [
    'version' => '2.0.0',
    
    // App name for Discord webhooks
    'app_name' => 'SeAT Broadcast',
    
    // Default webhook settings
    'default_embed_color' => '#5865F2',
    'default_username' => 'SeAT Broadcast',
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
            'enabled' => true,
            'link_doctrines' => true,
        ],
    ],

    // Structure timer integration (Manager Core EventBus).
    // When Manager Core and Structure Manager are installed, SeAT Broadcast
    // subscribes to structure_manager.timer.* events: structure timers and
    // fleet ops appear on the Broadcasts Calendar, and webhooks flagged to
    // receive structure alerts get pre-timer reminder pings (T-24h, T-1h).
    'structure_events' => [
        // Master switch for the EventBus subscription and calendar ingest.
        'enabled' => true,
        // Days to keep resolved (dismissed / elapsed) timers before the
        // cleanup job prunes them.
        'retention_days' => 14,
        // Default form-up lead time in minutes. When an FC clicks
        // "Schedule formup ping" from a tactical event (Calendar modal or
        // FC Opportunities board), the scheduled time is pre-filled to
        // (timer eve_time minus this offset). Operator-overridable via the
        // Settings > Structure Timers tab.
        'formup_offset_minutes' => 30,
    ],

    // Mining extraction integration (Manager Core EventBus + Mining Manager).
    // When all three of Manager Core, Mining Manager (v2.0.1+) and SeAT
    // Broadcast are installed, SeAT Broadcast subscribes to the
    // mining.extraction_* event family: moon extractions appear as a
    // distinct ⛏️ mining category on the Broadcasts Calendar and FC
    // Opportunities board, and webhooks flagged "Receive mining extraction
    // alerts" get a single pre-expiry reminder ping (T-2h before the window
    // closes).
    'mining_events' => [
        // Master switch for the EventBus subscription and calendar ingest.
        'enabled' => true,
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
