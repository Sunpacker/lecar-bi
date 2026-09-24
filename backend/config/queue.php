<?php

return ['default' => env('QUEUE_CONNECTION', 'redis'), 'connections' => ['sync' => ['driver' => 'sync'], 'redis' => ['driver' => 'redis', 'connection' => 'default', 'queue' => env('REDIS_QUEUE', 'default'), 'retry_after' => 120, 'block_for' => null, 'after_commit' => true]], 'failed' => ['driver' => 'database-uuids', 'database' => env('DB_CONNECTION', 'pgsql'), 'table' => 'failed_jobs']];
