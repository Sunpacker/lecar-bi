<?php

use Monolog\Handler\StreamHandler;
use NotificationService\Shared\Infrastructure\Logging\JsonLogFormatter;

return [
    'default' => env('LOG_CHANNEL', 'stderr'),
    'channels' => [
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => JsonLogFormatter::class,
            'with' => ['stream' => 'php://stderr'],
        ],
        'stack' => [
            'driver' => 'stack',
            'channels' => ['stderr'],
            'ignore_exceptions' => false,
        ],
    ],
];
