<?php

declare(strict_types=1);

namespace Tests;

use App\Modules\Workspace\Application\Contracts\AuthTokenServiceInterface;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate as a user using Sanctum.
     */
    protected function authenticateUser(string $userId = 'user-1'): UserModel
    {
        $user = new UserModel([
            'id' => $userId,
            'email' => "{$userId}@autobi.internal",
            'name' => "User {$userId}",
        ]);
        $user->exists = true;

        Sanctum::actingAs($user);

        return $user;
    }

    /** Attach Bearer tokens to legacy tests that still provide X-User-Id. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $userId = $server['HTTP_X_USER_ID'] ?? null;
        if ($userId && ! isset($server['HTTP_AUTHORIZATION']) && ! isset($server['HTTP_X_DO_NOT_CONVERT_AUTH'])) {
            $tokenService = app(AuthTokenServiceInterface::class);
            $token = $tokenService->createToken($userId);
            $server['HTTP_AUTHORIZATION'] = "Bearer {$token}";

            $user = new UserModel([
                'id' => $userId,
                'email' => "{$userId}@autobi.internal",
                'name' => "User {$userId}",
            ]);
            $user->exists = true;
            Sanctum::actingAs($user);
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
