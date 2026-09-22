<?php

namespace Tests\Unit\Modules\Workspace\Infrastructure;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Modules\Workspace\Infrastructure\Persistence\InMemory\InMemoryUserRepository;
use App\Modules\Workspace\Infrastructure\Persistence\InMemory\InMemoryWorkspaceRepository;
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
        self::assertCount(1, $wsRepo->findByUserId(new UserId('u-1')));
        self::assertEmpty($wsRepo->findByUserId(new UserId('u-2')));
    }
}
