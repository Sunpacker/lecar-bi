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

    /**
     * Intercept test calls to route legacy /api/v1/ tests to /api/v2/ and attach Bearer tokens.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $isHealthOrMetrics = str_starts_with($uri, '/api/v1/health') || str_starts_with($uri, '/api/v1/metrics');
        $isExplicitV1Test = isset($server['HTTP_X_EXPLICIT_V1']) || str_contains($this->name(), 'v1');

        if (str_starts_with($uri, '/api/v1/') && ! $isHealthOrMetrics && ! $isExplicitV1Test) {
            $uri = '/api/v2/'.substr($uri, strlen('/api/v1/'));
        }

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
