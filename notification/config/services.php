<?php

declare(strict_types=1);

return [
    'notification' => [
        'shared_secret' => env('NOTIFICATION_SHARED_SECRET', 'test-notification-secret-key-12345'),
    ],
];
