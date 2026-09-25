<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Auth;

use App\Modules\Workspace\Application\Contracts\AuthTokenServiceInterface;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\PersonalAccessToken;

final class SanctumTokenService implements AuthTokenServiceInterface
{
    public function createToken(string $userId, string $name = 'api'): string
    {
        $userModel = UserModel::find($userId) ?? new UserModel(['id' => $userId]);
        $userModel->exists = true;

        return $userModel->createToken($name)->plainTextToken;
    }

    public function resolveUserIdByToken(string $plainTextToken): ?string
    {
        $token = PersonalAccessToken::findToken($plainTextToken);
        if ($token === null) {
            return null;
        }

        // Check expiration if configured
        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            $token->delete();

            return null;
        }

        return (string) $token->tokenable_id;
    }

    public function revokeToken(string $plainTextToken): void
    {
        $token = PersonalAccessToken::findToken($plainTextToken);
        $token?->delete();
    }

    public function revokeAllUserTokens(string $userId): void
    {
        PersonalAccessToken::where('tokenable_id', $userId)->delete();
    }
}
