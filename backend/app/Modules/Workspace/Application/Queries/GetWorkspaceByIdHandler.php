<?php

namespace App\Modules\Workspace\Application\Queries;

use App\Modules\Workspace\Application\Dtos\WorkspaceDto;
use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Domain\UserId;

final readonly class GetWorkspaceByIdHandler
{
    public function __construct(private WorkspaceAccessGuard $accessGuard) {}

    public function handle(GetWorkspaceByIdQuery $query): WorkspaceDto
    {
        $workspace = $this->accessGuard->assertAccess($query->userId, $query->workspaceId);
        $userId = new UserId($query->userId);

        $role = $workspace->memberRole($userId);

        return new WorkspaceDto(
            id: $workspace->id()->value(),
            name: $workspace->name(),
            slug: $workspace->slug(),
            role: $role !== null ? $role->value : 'member',
        );
    }
}
