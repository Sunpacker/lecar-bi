<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'options' => [
                PDO::ATTR_TIMEOUT => (int) env('DB_TIMEOUT', 5),
            ],
        ],
    ],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => [
            'parameters' => [
                'timeout' => (float) env('REDIS_TIMEOUT', 3.0),
                'read_write_timeout' => (float) env('REDIS_READ_TIMEOUT', 3.0),
            ],
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
            'timeout' => (float) env('REDIS_TIMEOUT', 3.0),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 3.0),
        ],
        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
            'timeout' => (float) env('REDIS_TIMEOUT', 3.0),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 3.0),
        ],
        // Dedicated connection for integration events / outbox publisher.
        // Isolated from queues and cache to avoid cross-contamination.
        'outbox' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('OUTBOX_REDIS_DB', 2),
            'timeout' => (float) env('REDIS_TIMEOUT', 3.0),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 3.0),
        ],
    ],
];
