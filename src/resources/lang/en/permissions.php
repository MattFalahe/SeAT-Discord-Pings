<?php

return [
    'view_label' => 'View SeAT Broadcast',
    'view_description' => 'Baseline access to the SeAT Broadcast plugin (sidebar visibility). All other capability permissions require this plus their specific grant.',
    
    'send_label' => 'Send Discord Pings',
    'send_description' => 'Send pings to Discord channels (manual + scheduled). Includes access to the Scheduled Broadcasts list, Calendar, and FC Opportunities planner — but limited to the user\'s OWN scheduled pings. The Fleet Coordinator tier ("Manage All Scheduled Pings") adds visibility and control across everyone\'s.',
    
    'send_multiple_label' => 'Send to Multiple Webhooks',
    'send_multiple_description' => 'Send pings to multiple Discord channels simultaneously',
    
    'manage_webhooks_label' => 'Manage Webhooks',
    'manage_webhooks_description' => 'Create, edit, and delete Discord webhooks',
    
    'view_history_label' => 'View Ping History',
    'view_history_description' => 'View your own ping history',
    
    'view_all_history_label' => 'View All History',
    'view_all_history_description' => 'View ping history from all users',
    
    'manage_scheduled_label' => 'Manage All Scheduled Pings',
    'manage_scheduled_description' => 'Fleet Coordinator tier. Adds the ability to see, edit, and delete OTHER users\' scheduled pings (the per-user list/calendar views become full-org views), plus the bulk-clear operations on the Scheduled Broadcasts page. Regular FCs with just the Send Pings permission already manage their own scheduled pings without this.',

    'manage_templates_label' => 'Manage Templates',
    'manage_templates_description' => 'Create, edit, and delete your own broadcast templates',

    'manage_global_templates_label' => 'Manage Global Templates',
    'manage_global_templates_description' => 'Create and manage global broadcast templates visible to all users',

    'admin_label' => 'Plugin Admin',
    'admin_description' => 'Access the SeAT Broadcast diagnostic page (Settings > Help & Documentation does NOT list it; reach via /discord-pings/diagnostic). Admin-only operational surface for troubleshooting webhook delivery, event subscriptions, and integration health.',
];
