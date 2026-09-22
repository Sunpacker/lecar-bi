<?php

namespace App\Modules\Workspace\Application\Guards;

use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;

final readonly class WorkspaceAccessGuard
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository,
    ) {}

    public function assertAccess(string $userId, string $workspaceId): Workspace
    {
        $workspace = $this->workspaceRepository->findById(new WorkspaceId($workspaceId));

        if ($workspace === null) {
            throw WorkspaceNotFoundException::forId($workspaceId);
        }

        $user = new UserId($userId);

        if (! $workspace->hasMember($user)) {
            throw UnauthorizedWorkspaceAccessException::forUserAndWorkspace($userId, $workspaceId);
        }

        return $workspace;
    }
}
