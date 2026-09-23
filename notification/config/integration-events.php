<?php

return [
    'stream_name' => env('INTEGRATION_STREAM_NAME', 'autobi.integration-events'),
    'group_name' => env('INTEGRATION_GROUP_NAME', 'notification-service-v1'),
    'consumer_name' => env('INTEGRATION_CONSUMER_NAME', 'notification-worker-1'),
    'batch_size' => (int) env('INTEGRATION_BATCH_SIZE', 10),
    'block_timeout_ms' => (int) env('INTEGRATION_BLOCK_TIMEOUT_MS', 2000),
    'stale_idle_ms' => (int) env('INTEGRATION_STALE_IDLE_MS', 60000),
    'dead_letter_stream' => env('INTEGRATION_DEAD_LETTER_STREAM', 'autobi.integration-events.dead-letter'),
    'redis_connection' => env('INTEGRATION_REDIS_CONNECTION', 'integration_events'),
];
