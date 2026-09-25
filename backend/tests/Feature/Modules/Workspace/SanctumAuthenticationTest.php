<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Workspace;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class SanctumAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $user1 = new User(new UserId('user-1'), 'elena@autobi.internal', 'Elena Rostova');
        $userRepo->save($user1);

        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);
        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws1);
    }

    public function test_spoofing_x_user_id_does_not_grant_access(): void
    {
        // When X-Do-Not-Convert-Auth is set, TestCase doesn't synthesize a token
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Do-Not-Convert-Auth' => '1',
        ])->getJson('/api/v2/workspaces');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_request_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v2/workspaces');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_login_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/v2/auth/login', [
            'email' => 'elena@autobi.internal',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'email', 'name'],
                'token',
            ])
            ->assertJsonPath('user.email', 'elena@autobi.internal');

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // Can access protected route with Bearer token
        $protectedResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v2/workspaces');

        $protectedResponse->assertStatus(200);
    }

    public function test_request_with_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token-12345')
            ->getJson('/api/v2/workspaces');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_logout_revokes_token_and_invalidates_session(): void
    {
        // 1. Login to get token
        $loginResponse = $this->postJson('/api/v2/auth/login', [
            'email' => 'elena@autobi.internal',
            'password' => 'password123',
        ]);
        $token = $loginResponse->json('token');

        // 2. Token works
        $accessResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v2/workspaces');
        $accessResponse->assertStatus(200);

        // 3. Logout with token
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v2/auth/logout');
        $logoutResponse->assertStatus(204);

        // 4. Token is now revoked and rejected
        $postLogoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v2/workspaces');
        $postLogoutResponse->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_v1_protected_routes_return_410(): void
    {
        $response = $this->withHeader('X-Explicit-V1', '1')
            ->getJson('/api/v1/workspaces');
        $response->assertStatus(410);

        $loginResponse = $this->withHeader('X-Explicit-V1', '1')
            ->postJson('/api/v1/auth/login');
        $loginResponse->assertStatus(410);
    }

    public function test_v1_health_routes_remain_functional(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200);

        $liveResponse = $this->getJson('/api/v1/health/live');
        $liveResponse->assertStatus(200);

        $readyResponse = $this->getJson('/api/v1/health/ready');
        $readyResponse->assertStatus(200);
    }
}
