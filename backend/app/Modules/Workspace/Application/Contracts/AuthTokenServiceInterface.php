<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Contracts;

interface AuthTokenServiceInterface
{
    public function createToken(string $userId, string $name = 'api'): string;

    public function resolveUserIdByToken(string $plainTextToken): ?string;

    public function revokeToken(string $plainTextToken): void;

    public function revokeAllUserTokens(string $userId): void;
}
