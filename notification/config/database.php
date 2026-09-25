<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', ':memory:'),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'notification'),
            'username' => env('DB_USERNAME', 'notification'),
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
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
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
        'integration_events' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('INTEGRATION_EVENTS_REDIS_DB', 2),
            'timeout' => (float) env('REDIS_TIMEOUT', 3.0),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 3.0),
        ],
    ],
];
