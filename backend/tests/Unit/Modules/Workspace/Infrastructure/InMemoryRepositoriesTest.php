<?php

namespace Tests\Unit\Modules\Workspace\Infrastructure;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Modules\Workspace\Infrastructure\Persistence\InMemory\InMemoryUserRepository;
use App\Modules\Workspace\Infrastructure\Persistence\InMemory\InMemoryWorkspaceRepository;
use App\Modules\Workspace\Infrastructure\Persistence\InMemoryWorkspaceMemberReadModel;
use App\Modules\Workspace\Infrastructure\Persistence\InMemoryWorkspaceTransactionManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryRepositoriesTest extends TestCase
{
    #[Test]
    public function in_memory_repositories_persist_and_query_entities(): void
    {
        $userRepo = new InMemoryUserRepository;
        $wsRepo = new InMemoryWorkspaceRepository;

        $user = new User(new UserId('u-1'), 'test@autobi.local', 'Test User');
        $userRepo->save($user);

        self::assertSame($user, $userRepo->findById(new UserId('u-1')));
        self::assertNull($userRepo->findById(new UserId('u-missing')));

        $workspace = new Workspace(new WorkspaceId('ws-1'), 'Test WS', 'test-ws');
        $workspace->addMember(new UserId('u-1'), MembershipRole::OWNER);
        $wsRepo->save($workspace);

        self::assertSame($workspace, $wsRepo->findById(new WorkspaceId('ws-1')));
        self::assertSame($workspace, $wsRepo->findByIdForUpdate(new WorkspaceId('ws-1')));
        self::assertCount(1, $wsRepo->findByUserId(new UserId('u-1')));
        self::assertEmpty($wsRepo->findByUserId(new UserId('u-2')));
    }

    #[Test]
    public function in_memory_member_read_model_reads_members(): void
    {
        $userRepo = new InMemoryUserRepository;
        $wsRepo = new InMemoryWorkspaceRepository;

        $user1 = new User(new UserId('u-1'), 'owner@autobi.local', 'Owner User');
        $user2 = new User(new UserId('u-2'), 'viewer@autobi.local', 'Viewer User');
        $userRepo->save($user1);
        $userRepo->save($user2);

        $workspace = new Workspace(new WorkspaceId('ws-1'), 'Test WS', 'test-ws');
        $workspace->addMember(new UserId('u-1'), MembershipRole::OWNER);
        $workspace->addMember(new UserId('u-2'), MembershipRole::VIEWER);
        $wsRepo->save($workspace);

        $readModel = new InMemoryWorkspaceMemberReadModel($wsRepo, $userRepo);
        $members = $readModel->getMembers(new WorkspaceId('ws-1'));

        self::assertCount(2, $members);
        self::assertSame('u-1', $members[0]->user->id);
        self::assertSame('owner', $members[0]->role);
        self::assertSame('u-2', $members[1]->user->id);
        self::assertSame('viewer', $members[1]->role);
    }

    #[Test]
    public function in_memory_transaction_manager_executes_callback(): void
    {
        $txManager = new InMemoryWorkspaceTransactionManager;
        $result = $txManager->transaction(fn () => 'executed');
        self::assertSame('executed', $result);
    }
}
