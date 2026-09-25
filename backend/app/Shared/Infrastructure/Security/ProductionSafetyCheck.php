<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use RuntimeException;

final class ProductionSafetyCheck
{
    private const KNOWN_INSECURE_PASSWORDS = [
        'autobi',
        'notification',
        'password',
        'password123',
        'root',
        'admin',
        'secret',
    ];

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException
     */
    public static function check(bool $isProduction, array $config): void
    {
        if (! $isProduction) {
            return;
        }

        // 1. APP_DEBUG must be false in production
        $debug = $config['app_debug'] ?? false;
        if ($debug === true || $debug === 'true' || $debug === 1 || $debug === '1') {
            throw new RuntimeException('Production safety violation: APP_DEBUG must be false in production environment.');
        }

        // 2. APP_KEY must be set and cannot be empty or a demo placeholder
        $appKey = (string) ($config['app_key'] ?? '');
        if ($appKey === '' || str_contains(strtolower($appKey), 'demo') || str_contains(strtolower($appKey), 'change_me')) {
            throw new RuntimeException('Production safety violation: APP_KEY must be a valid, strong production key.');
        }

        // 3. Database password must not be a known insecure default
        $dbPassword = (string) ($config['db_password'] ?? '');
        if ($dbPassword === '' || in_array(strtolower($dbPassword), self::KNOWN_INSECURE_PASSWORDS, true)) {
            throw new RuntimeException('Production safety violation: Database password cannot be empty or a default insecure demo password in production.');
        }
    }
}
