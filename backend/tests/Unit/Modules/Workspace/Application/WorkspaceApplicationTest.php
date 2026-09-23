<?php

namespace Tests\Unit\Modules\Workspace\Application;

use App\Modules\Workspace\Application\Commands\ChangeWorkspaceMemberRoleCommand;
use App\Modules\Workspace\Application\Commands\ChangeWorkspaceMemberRoleHandler;
use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Application\Queries\GetAccessibleWorkspacesHandler;
use App\Modules\Workspace\Application\Queries\GetAccessibleWorkspacesQuery;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Application\Queries\GetWorkspaceByIdHandler;
use App\Modules\Workspace\Application\Queries\GetWorkspaceByIdQuery;
use App\Modules\Workspace\Application\Queries\GetWorkspaceMembersHandler;
use App\Modules\Workspace\Application\Queries\GetWorkspaceMembersQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceCapability;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Modules\Workspace\Infrastructure\Persistence\InMemoryWorkspaceMemberReadModel;
use App\Modules\Workspace\Infrastructure\Persistence\InMemoryWorkspaceTransactionManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkspaceApplicationTest extends TestCase
{
    private WorkspaceRepositoryInterface $workspaceRepository;

    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $user1 = new User(new UserId('user-1'), 'elena@autobi.internal', 'Elena Rostova');
        $user2 = new User(new UserId('user-2'), 'dmitry@autobi.internal', 'Dmitry Smirnov');

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Lecar Wholesale', 'lecar-wholesale');
        $ws2->addMember(new UserId('user-2'), MembershipRole::OWNER);

        $workspaces = ['ws-1' => $ws1, 'ws-2' => $ws2];
        $users = ['user-1' => $user1, 'user-2' => $user2];

        $this->workspaceRepository = new class($workspaces) implements WorkspaceRepositoryInterface
        {
            /** @param array<string, Workspace> $items */
            public function __construct(private array $items) {}

            public function findById(WorkspaceId $id): ?Workspace
            {
                return $this->items[$id->value()] ?? null;
            }

            public function findByIdForUpdate(WorkspaceId $id): ?Workspace
            {
                return $this->findById($id);
            }

            public function findByUserId(UserId $userId): array
            {
                return array_values(array_filter($this->items, fn (Workspace $ws) => $ws->hasMember($userId)));
            }

            public function findAll(): array
            {
                return array_values($this->items);
            }

            public function save(Workspace $workspace): void
            {
                $this->items[$workspace->id()->value()] = $workspace;
            }
        };

        $this->userRepository = new class($users) implements UserRepositoryInterface
        {
            /** @param array<string, User> $items */
            public function __construct(private array $items) {}

            public function findById(UserId $id): ?User
            {
                return $this->items[$id->value()] ?? null;
            }

            public function findByEmail(string $email): ?User
            {
                $normalized = strtolower(trim($email));
                foreach ($this->items as $user) {
                    if (strtolower($user->email()) === $normalized) {
                        return $user;
                    }
                }

                return null;
            }

            public function save(User $user): void
            {
                $this->items[$user->id()->value()] = $user;
            }
        };
    }

    #[Test]
    public function guard_allows_authorized_member_and_blocks_cross_workspace(): void
    {
        $guard = new WorkspaceAccessGuard($this->workspaceRepository);

        $workspace = $guard->assertAccess('user-1', 'ws-1');
        self::assertSame('ws-1', $workspace->id()->value());

        $this->expectException(UnauthorizedWorkspaceAccessException::class);
        $guard->assertAccess('user-1', 'ws-2');
    }

    #[Test]
    public function get_accessible_workspaces_returns_only_permitted_workspaces(): void
    {
        $handler = new GetAccessibleWorkspacesHandler($this->workspaceRepository);
        $result = $handler->handle(new GetAccessibleWorkspacesQuery('user-1'));

        self::assertCount(1, $result);
        self::assertSame('ws-1', $result[0]->id);
        self::assertSame('owner', $result[0]->role);
    }

    #[Test]
    public function get_workspace_by_id_returns_workspace_details(): void
    {
        $guard = new WorkspaceAccessGuard($this->workspaceRepository);
        $handler = new GetWorkspaceByIdHandler($guard);

        $workspace = $handler->handle(new GetWorkspaceByIdQuery('user-1', 'ws-1'));
        self::assertSame('ws-1', $workspace->id);
        self::assertSame('AutoParts Retail', $workspace->name);
    }

    #[Test]
    public function get_current_workspace_resolves_default_or_requested(): void
    {
        $guard = new WorkspaceAccessGuard($this->workspaceRepository);
        $handler = new GetCurrentWorkspaceHandler($this->workspaceRepository, $this->userRepository, $guard);

        $current = $handler->handle(new GetCurrentWorkspaceQuery('user-1', null));
        self::assertSame('ws-1', $current->workspace->id);
        self::assertSame('user-1', $current->user->id);
        self::assertContains('workspace.members.manage', $current->workspace->capabilities);
    }

    #[Test]
    public function assert_capability_enforces_required_permissions(): void
    {
        $guard = new WorkspaceAccessGuard($this->workspaceRepository);

        // user-1 is owner of ws-1
        $ws = $guard->assertCapability('user-1', 'ws-1', WorkspaceCapability::WORKSPACE_MEMBERS_MANAGE);
        self::assertSame('ws-1', $ws->id()->value());

        // user-2 is owner of ws-2, but not member of ws-1
        $this->expectException(UnauthorizedWorkspaceAccessException::class);
        $guard->assertCapability('user-2', 'ws-1', WorkspaceCapability::ANALYTICS_VIEW);
    }

    #[Test]
    public function get_workspace_members_returns_members_for_authorized_actor(): void
    {
        $guard = new WorkspaceAccessGuard($this->workspaceRepository);
        $readModel = new InMemoryWorkspaceMemberReadModel(
            $this->workspaceRepository,
            $this->userRepository,
        );

        $handler = new GetWorkspaceMembersHandler($guard, $readModel);
        $members = $handler->handle(new GetWorkspaceMembersQuery(
            actorUserId: 'user-1',
            workspaceId: 'ws-1',
        ));

        self::assertCount(1, $members);
        self::assertSame('user-1', $members[0]->user->id);
        self::assertSame('owner', $members[0]->role);
    }

    #[Test]
    public function change_member_role_updates_role_atomically(): void
    {
        $guard = new WorkspaceAccessGuard($this->workspaceRepository);
        $txManager = new InMemoryWorkspaceTransactionManager;

        // Add a second member to ws-1
        $ws1 = $this->workspaceRepository->findById(new WorkspaceId('ws-1'));
        $ws1->addMember(new UserId('user-2'), MembershipRole::MEMBER);
        $this->workspaceRepository->save($ws1);

        $handler = new ChangeWorkspaceMemberRoleHandler(
            $guard,
            $this->workspaceRepository,
            $this->userRepository,
            $txManager,
        );

        $result = $handler->handle(new ChangeWorkspaceMemberRoleCommand(
            actorUserId: 'user-1',
            workspaceId: 'ws-1',
            targetUserId: 'user-2',
            role: 'viewer',
        ));

        self::assertSame('viewer', $result->role);
        self::assertSame('user-2', $result->user->id);

        $updatedWs = $this->workspaceRepository->findById(new WorkspaceId('ws-1'));
        self::assertSame(MembershipRole::VIEWER, $updatedWs->memberRole(new UserId('user-2')));
    }
}
