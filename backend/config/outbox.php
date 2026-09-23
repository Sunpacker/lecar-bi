<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Outbox Publisher Configuration
    |--------------------------------------------------------------------------
    */

    // Redis Stream name where integration events are published.
    'stream_name' => env('OUTBOX_STREAM_NAME', 'autobi.integration-events'),

    // Redis database index for integration events (separate from default/queue/cache).
    'redis_db' => (int) env('OUTBOX_REDIS_DB', 2),

    // Maximum number of messages to publish in a single batch.
    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),

    // Seconds after which a 'processing' message is considered stale and unlocked.
    'stale_lock_timeout_seconds' => (int) env('OUTBOX_STALE_LOCK_SECONDS', 600),

    // Maximum number of delivery attempts before marking a message as 'failed'.
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 10),
];
