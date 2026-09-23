<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Workspace;

use App\Modules\Workspace\Domain\Exceptions\LastWorkspaceOwnerException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceMemberNotFoundException;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\WorkspaceMemberModel;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\WorkspaceModel;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Repositories\EloquentWorkspaceRepository;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;
use UnexpectedValueException;

final class WorkspaceRolePersistenceTest extends TestCase
{
    #[Test]
    public function eloquent_repository_decodes_all_valid_roles(): void
    {
        $repo = new EloquentWorkspaceRepository;
        $reflection = new ReflectionClass($repo);
        $toDomain = $reflection->getMethod('toDomain');
        $toDomain->setAccessible(true);

        $model = new WorkspaceModel([
            'id' => 'ws-1',
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
        ]);
        $model->id = 'ws-1';

        $ownerMember = new WorkspaceMemberModel([
            'workspace_id' => 'ws-1',
            'user_id' => 'user-1',
            'role' => 'owner',
        ]);
        $regularMember = new WorkspaceMemberModel([
            'workspace_id' => 'ws-1',
            'user_id' => 'user-2',
            'role' => 'member',
        ]);
        $viewerMember = new WorkspaceMemberModel([
            'workspace_id' => 'ws-1',
            'user_id' => 'user-3',
            'role' => 'viewer',
        ]);

        $model->setRelation('members', collect([$ownerMember, $regularMember, $viewerMember]));

        /** @var Workspace $workspace */
        $workspace = $toDomain->invoke($repo, $model);

        self::assertSame('ws-1', $workspace->id()->value());
        self::assertSame(MembershipRole::OWNER, $workspace->memberRole(new UserId('user-1')));
        self::assertSame(MembershipRole::MEMBER, $workspace->memberRole(new UserId('user-2')));
        self::assertSame(MembershipRole::VIEWER, $workspace->memberRole(new UserId('user-3')));
        self::assertSame(1, $workspace->ownerCount());
    }

    #[Test]
    public function eloquent_repository_throws_on_unknown_persisted_role(): void
    {
        $repo = new EloquentWorkspaceRepository;
        $reflection = new ReflectionClass($repo);
        $toDomain = $reflection->getMethod('toDomain');
        $toDomain->setAccessible(true);

        $model = new WorkspaceModel([
            'id' => 'ws-1',
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
        ]);
        $model->id = 'ws-1';

        $invalidMember = new WorkspaceMemberModel([
            'workspace_id' => 'ws-1',
            'user_id' => 'user-bad',
            'role' => 'superadmin',
        ]);

        $model->setRelation('members', collect([$invalidMember]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Invalid membership role 'superadmin'");
        $toDomain->invoke($repo, $model);
    }

    #[Test]
    public function last_owner_protection_prevents_workspace_without_owner(): void
    {
        $workspace = new Workspace(
            id: new WorkspaceId('ws-test'),
            name: 'Test WS',
            slug: 'test-ws',
        );

        $u1 = new UserId('u-1');
        $u2 = new UserId('u-2');
        $workspace->addMember($u1, MembershipRole::OWNER);
        $workspace->addMember($u2, MembershipRole::MEMBER);

        $this->expectException(LastWorkspaceOwnerException::class);
        $workspace->changeMemberRole($u1, MembershipRole::VIEWER);
    }

    #[Test]
    public function non_member_role_change_throws_exception(): void
    {
        $workspace = new Workspace(
            id: new WorkspaceId('ws-test'),
            name: 'Test WS',
            slug: 'test-ws',
        );

        $this->expectException(WorkspaceMemberNotFoundException::class);
        $workspace->changeMemberRole(new UserId('u-ghost'), MembershipRole::OWNER);
    }
}
