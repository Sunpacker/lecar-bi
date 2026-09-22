<?php

namespace Tests\Unit\Modules\Workspace\Application;

use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Application\Queries\GetAccessibleWorkspacesHandler;
use App\Modules\Workspace\Application\Queries\GetAccessibleWorkspacesQuery;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Application\Queries\GetWorkspaceByIdHandler;
use App\Modules\Workspace\Application\Queries\GetWorkspaceByIdQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
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

            public function findByUserId(UserId $userId): array
            {
                return array_values(array_filter($this->items, fn (Workspace $ws) => $ws->hasMember($userId)));
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
    }
}
