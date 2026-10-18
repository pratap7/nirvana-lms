<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain & Path
    |--------------------------------------------------------------------------
    */

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration & Strict Priority Order
    |--------------------------------------------------------------------------
    |
    | High: Real-time payments, OTP, webhook notifications, critical alerts
    | Default: Standard domain events, user actions
    | Notifications: Email, WhatsApp, SMS outbox dispatches
    | Reports: Heavy CSV / Excel export workers (100k row generators)
    | Low: Log pruning, retention cleanup, periodic maintenance
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['high', 'default', 'notifications', 'reports', 'low'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 10,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
        'reports-worker' => [
            'connection' => 'redis',
            'queue' => ['reports'],
            'balance' => 'simple',
            'maxProcesses' => 3,
            'memory' => 512,
            'tries' => 1,
            'timeout' => 600, // 10 minutes for 100k row report streaming
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 20,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'reports-worker' => [
                'maxProcesses' => 5,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
            'reports-worker' => [
                'maxProcesses' => 2,
            ],
        ],
    ],
];
