<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\ProductionSafetyCheck;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProductionSafetyCheckTest extends TestCase
{
    public function test_allows_any_config_in_non_production(): void
    {
        $this->expectNotToPerformAssertions();

        ProductionSafetyCheck::check(false, [
            'app_debug' => true,
            'app_key' => '',
            'db_password' => 'autobi',
        ]);
    }

    public function test_fails_in_production_if_debug_is_enabled(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG must be false in production');

        ProductionSafetyCheck::check(true, [
            'app_debug' => true,
            'app_key' => 'base64:'.base64_encode('valid-production-key-32-chars-long!'),
            'db_password' => 'SuperSecretStrongProdPass987!',
        ]);
    }

    public function test_fails_in_production_if_app_key_is_missing_or_demo(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY must be a valid, strong production key');

        ProductionSafetyCheck::check(true, [
            'app_debug' => false,
            'app_key' => 'base64:DEMO_KEY_CHANGE_ME',
            'db_password' => 'SuperSecretStrongProdPass987!',
        ]);
    }

    public function test_fails_in_production_if_db_password_is_insecure_default(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database password cannot be empty or a default insecure demo password');

        ProductionSafetyCheck::check(true, [
            'app_debug' => false,
            'app_key' => 'base64:'.base64_encode('valid-production-key-32-chars-long!'),
            'db_password' => 'autobi',
        ]);
    }

    public function test_passes_with_safe_production_config(): void
    {
        $this->expectNotToPerformAssertions();

        ProductionSafetyCheck::check(true, [
            'app_debug' => false,
            'app_key' => 'base64:'.base64_encode('valid-production-key-32-chars-long!'),
            'db_password' => 'X9#kLm9@1vQp7$2zWq8',
        ]);
    }
}
