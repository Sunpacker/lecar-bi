<?php

namespace Tests\Feature\Modules\Workspace;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class WorkspaceAccessBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);

        $user1 = new User(new UserId('user-1'), 'elena@autobi.internal', 'Elena Rostova');
        $user2 = new User(new UserId('user-2'), 'dmitry@autobi.internal', 'Dmitry Smirnov');
        $userRepo->save($user1);
        $userRepo->save($user2);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws1);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Lecar Wholesale', 'lecar-wholesale');
        $ws2->addMember(new UserId('user-2'), MembershipRole::OWNER);
        $wsRepo->save($ws2);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/workspaces');
        $response->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_user_can_only_list_accessible_workspaces(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')->getJson('/api/v1/workspaces');

        $response->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', 'ws-1');
    }

    public function test_user_can_view_own_workspace(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')->getJson('/api/v1/workspaces/ws-1');

        $response->assertOk()
            ->assertJsonPath('id', 'ws-1')
            ->assertJsonPath('name', 'AutoParts Retail');
    }

    public function test_cross_workspace_access_is_forbidden(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')->getJson('/api/v1/workspaces/ws-2');

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_non_existent_workspace_returns_404(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')->getJson('/api/v1/workspaces/ws-nonexistent');

        $response->assertStatus(404)
            ->assertJson([
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_current_workspace_endpoint_respects_boundary(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-2')
            ->getJson('/api/v1/workspaces/current');

        $response->assertStatus(403);
    }

    public function test_profile_me_returns_current_user_profile(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('id', 'user-1')
            ->assertJsonPath('email', 'elena@autobi.internal')
            ->assertJsonPath('name', 'Elena Rostova');
    }
}
