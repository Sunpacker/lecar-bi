<?php

namespace App\Modules\Workspace\Infrastructure\Persistence\InMemory;

use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;

final class InMemoryWorkspaceRepository implements WorkspaceRepositoryInterface
{
    /** @var array<string, Workspace> */
    private array $workspaces = [];

    public function findById(WorkspaceId $id): ?Workspace
    {
        return $this->workspaces[$id->value()] ?? null;
    }

    /** @return list<Workspace> */
    public function findByUserId(UserId $userId): array
    {
        return array_values(array_filter(
            $this->workspaces,
            fn (Workspace $ws) => $ws->hasMember($userId),
        ));
    }

    /** @return list<Workspace> */
    public function findAll(): array
    {
        return array_values($this->workspaces);
    }

    public function save(Workspace $workspace): void
    {
        $this->workspaces[$workspace->id()->value()] = $workspace;
    }
}
