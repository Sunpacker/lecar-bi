<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Auth;

use App\Modules\Workspace\Application\Contracts\AuthTokenServiceInterface;

final class InMemoryTokenService implements AuthTokenServiceInterface
{
    /** @var array<string, string> plainTextToken => userId */
    private array $tokens = [];

    public function createToken(string $userId, string $name = 'api'): string
    {
        $token = 'autobi_tok_'.bin2hex(random_bytes(24));
        $this->tokens[$token] = $userId;

        return $token;
    }

    public function resolveUserIdByToken(string $plainTextToken): ?string
    {
        return $this->tokens[$plainTextToken] ?? null;
    }

    public function revokeToken(string $plainTextToken): void
    {
        unset($this->tokens[$plainTextToken]);
    }

    public function revokeAllUserTokens(string $userId): void
    {
        foreach ($this->tokens as $token => $ownerId) {
            if ($ownerId === $userId) {
                unset($this->tokens[$token]);
            }
        }
    }
}
