<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Nexus - Queue Workers
    |--------------------------------------------------------------------------
    |
    | This configuration defines the queue workers that should be spawned
    | when running the nexus:work command. Each worker can have its own
    | configuration for queues, timeouts, retries, and process count.
    |
    | To generate this configuration automatically, run:
    | php artisan nexus:configure
    |
    */

    'workers' => [
        'default' => [
            'queue' => 'default',
            'connection' => env('QUEUE_CONNECTION', 'database'),
            'tries' => 3,
            'timeout' => 60,
            'sleep' => 3,
            'memory' => 128,
            'processes' => 2,
            'max_jobs' => 1000,
            'max_time' => 3600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the environment in which the queue manager will
    | run. This affects the process names and can be used for monitoring.
    |
    */

    'environment' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Process Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used for naming the worker processes. This helps
    | with process identification and monitoring in production environments.
    |
    */

    'prefix' => env('NEXUS_PREFIX', 'nexus'),

    /*
    |--------------------------------------------------------------------------
    | Auto Restart
    |--------------------------------------------------------------------------
    |
    | When this is enabled, workers will automatically restart when they
    | receive a restart signal, helping with deployments and updates.
    |
    */

    'auto_restart' => true,

    /*
    |--------------------------------------------------------------------------
    | Restart Signal File
    |--------------------------------------------------------------------------
    |
    | This file path is checked for modifications to trigger worker restarts.
    | Laravel's queue:restart command updates this file's timestamp.
    |
    */

    'restart_signal_file' => storage_path('framework/cache/laravel-queue-restart'),

];
