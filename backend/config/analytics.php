<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics Cache Enabled
    |--------------------------------------------------------------------------
    | Controls whether selective versioned caching is enabled for analytics read models.
    */
    'cache_enabled' => (bool) env('ANALYTICS_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Analytics Cache Schema Version
    |--------------------------------------------------------------------------
    | Incremented when DTO shapes or serialized payload structures change.
    */
    'schema_version' => (int) env('ANALYTICS_CACHE_SCHEMA_VERSION', 1),

    /*
    |--------------------------------------------------------------------------
    | Default TTLs (in seconds)
    |--------------------------------------------------------------------------
    | Short TTLs bound memory and mitigate emergency stale scenarios.
    */
    'default_ttl' => 120,
    'filter_ttl' => 300,

    'ttls' => [
        'sales_overview' => 120,
        'sales_filters' => 300,
        'inventory_summary' => 120,
        'inventory_filters' => 300,
        'abc_xyz_summary' => 120,
        'supplier_overview' => 120,
        'supplier_filters' => 300,
    ],
];
