<?php

return [
    'discord' => [
        'label' => 'Discord Pings',
        'permissions' => [
            'pings' => [
                'label' => 'Pings',
                'permissions' => [
                    'send' => [
                        'label' => 'Send Pings',
                        'description' => 'Allow sending pings to Discord'
                    ],
                    'send.multiple' => [
                        'label' => 'Send to Multiple Webhooks',
                        'description' => 'Allow sending pings to multiple webhooks simultaneously'
                    ],
                ],
            ],
            'scheduled' => [
                'label' => 'Scheduled Pings',
                'permissions' => [
                    'view' => [
                        'label' => 'View Scheduled',
                        'description' => 'View scheduled pings'
                    ],
                    'create' => [
                        'label' => 'Create Scheduled',
                        'description' => 'Create new scheduled pings'
                    ],
                    'delete' => [
                        'label' => 'Delete Scheduled',
                        'description' => 'Delete scheduled pings'
                    ],
                ],
            ],
            'history' => [
                'label' => 'Ping History',
                'permissions' => [
                    'view' => [
                        'label' => 'View History',
                        'description' => 'View ping history'
                    ],
                    'view.all' => [
                        'label' => 'View All History',
                        'description' => 'View ping history from all users'
                    ],
                ],
            ],
            'webhooks' => [
                'label' => 'Webhook Management',
                'permissions' => [
                    'manage' => [
                        'label' => 'Manage Webhooks',
                        'description' => 'Create, edit, and delete Discord webhooks'
                    ],
                ],
            ],
        ],
    ],
];
