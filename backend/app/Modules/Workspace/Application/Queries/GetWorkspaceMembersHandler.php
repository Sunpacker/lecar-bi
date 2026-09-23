<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Queries;

use App\Modules\Workspace\Application\Contracts\WorkspaceMemberReadModelInterface;
use App\Modules\Workspace\Application\Dtos\WorkspaceMemberDto;
use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Domain\WorkspaceCapability;
use App\Modules\Workspace\Domain\WorkspaceId;

final readonly class GetWorkspaceMembersHandler
{
    public function __construct(
        private WorkspaceAccessGuard $accessGuard,
        private WorkspaceMemberReadModelInterface $memberReadModel,
    ) {}

    /**
     * @return list<WorkspaceMemberDto>
     */
    public function handle(GetWorkspaceMembersQuery $query): array
    {
        $this->accessGuard->assertCapability(
            $query->actorUserId,
            $query->workspaceId,
            WorkspaceCapability::WORKSPACE_MEMBERS_MANAGE,
        );

        return $this->memberReadModel->getMembers(new WorkspaceId($query->workspaceId));
    }
}
