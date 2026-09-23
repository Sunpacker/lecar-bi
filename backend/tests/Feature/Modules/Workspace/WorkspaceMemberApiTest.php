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

final class WorkspaceMemberApiTest extends TestCase
{
    private WorkspaceRepositoryInterface $wsRepo;

    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepo = $this->app->make(UserRepositoryInterface::class);
        $this->wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);

        $owner = new User(new UserId('owner-1'), 'owner@autobi.internal', 'Owner One');
        $member = new User(new UserId('member-1'), 'member@autobi.internal', 'Member One');
        $viewer = new User(new UserId('viewer-1'), 'viewer@autobi.internal', 'Viewer One');
        $stranger = new User(new UserId('stranger-1'), 'stranger@autobi.internal', 'Stranger One');

        $this->userRepo->save($owner);
        $this->userRepo->save($member);
        $this->userRepo->save($viewer);
        $this->userRepo->save($stranger);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'Main Workspace', 'main-ws');
        $ws1->addMember(new UserId('owner-1'), MembershipRole::OWNER);
        $ws1->addMember(new UserId('member-1'), MembershipRole::MEMBER);
        $ws1->addMember(new UserId('viewer-1'), MembershipRole::VIEWER);
        $this->wsRepo->save($ws1);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Other Workspace', 'other-ws');
        $ws2->addMember(new UserId('stranger-1'), MembershipRole::OWNER);
        $this->wsRepo->save($ws2);
    }

    public function test_owner_can_list_workspace_members(): void
    {
        $response = $this->withHeader('X-User-Id', 'owner-1')
            ->getJson('/api/v1/workspaces/ws-1/members');

        $response->assertOk()
            ->assertJsonCount(3, 'items')
            ->assertJsonStructure([
                'items' => [
                    '*' => [
                        'user' => ['id', 'email', 'name'],
                        'role',
                    ],
                ],
            ]);
    }

    public function test_member_and_viewer_cannot_list_workspace_members(): void
    {
        $this->withHeader('X-User-Id', 'member-1')
            ->getJson('/api/v1/workspaces/ws-1/members')
            ->assertStatus(403)
            ->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);

        $this->withHeader('X-User-Id', 'viewer-1')
            ->getJson('/api/v1/workspaces/ws-1/members')
            ->assertStatus(403)
            ->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);
    }

    public function test_cross_workspace_member_list_is_forbidden(): void
    {
        $this->withHeader('X-User-Id', 'stranger-1')
            ->getJson('/api/v1/workspaces/ws-1/members')
            ->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_owner_can_change_member_role(): void
    {
        $response = $this->withHeader('X-User-Id', 'owner-1')
            ->patchJson('/api/v1/workspaces/ws-1/members/member-1/role', [
                'role' => 'viewer',
            ]);

        $response->assertOk()
            ->assertJson([
                'member' => [
                    'user' => [
                        'id' => 'member-1',
                        'email' => 'member@autobi.internal',
                    ],
                    'role' => 'viewer',
                ],
            ]);

        $ws = $this->wsRepo->findById(new WorkspaceId('ws-1'));
        self::assertSame(MembershipRole::VIEWER, $ws->memberRole(new UserId('member-1')));
    }

    public function test_same_role_assignment_is_idempotent(): void
    {
        $response = $this->withHeader('X-User-Id', 'owner-1')
            ->patchJson('/api/v1/workspaces/ws-1/members/member-1/role', [
                'role' => 'member',
            ]);

        $response->assertOk()
            ->assertJsonPath('member.role', 'member');
    }

    public function test_demoting_last_owner_returns_409(): void
    {
        $response = $this->withHeader('X-User-Id', 'owner-1')
            ->patchJson('/api/v1/workspaces/ws-1/members/owner-1/role', [
                'role' => 'member',
            ]);

        $response->assertStatus(409)
            ->assertJson(['code' => 'LAST_WORKSPACE_OWNER']);
    }

    public function test_changing_role_of_non_existent_member_returns_404(): void
    {
        $response = $this->withHeader('X-User-Id', 'owner-1')
            ->patchJson('/api/v1/workspaces/ws-1/members/unknown-user/role', [
                'role' => 'viewer',
            ]);

        $response->assertStatus(404)
            ->assertJson(['code' => 'WORKSPACE_MEMBER_NOT_FOUND']);
    }

    public function test_member_cannot_change_roles(): void
    {
        $response = $this->withHeader('X-User-Id', 'member-1')
            ->patchJson('/api/v1/workspaces/ws-1/members/viewer-1/role', [
                'role' => 'owner',
            ]);

        $response->assertStatus(403)
            ->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);
    }

    public function test_invalid_role_returns_422(): void
    {
        $response = $this->withHeader('X-User-Id', 'owner-1')
            ->patchJson('/api/v1/workspaces/ws-1/members/member-1/role', [
                'role' => 'superuser',
            ]);

        $response->assertStatus(422);
    }
}
