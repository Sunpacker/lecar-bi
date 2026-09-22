<?php

namespace App\Modules\Workspace\Application\Queries;

use App\Modules\Workspace\Application\Dtos\WorkspaceDto;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;

final readonly class GetAccessibleWorkspacesHandler
{
    public function __construct(private WorkspaceRepositoryInterface $workspaceRepository) {}

    /** @return list<WorkspaceDto> */
    public function handle(GetAccessibleWorkspacesQuery $query): array
    {
        $userId = new UserId($query->userId);
        $workspaces = $this->workspaceRepository->findByUserId($userId);

        return array_map(
            function (Workspace $ws) use ($userId): WorkspaceDto {
                $role = $ws->memberRole($userId);

                return new WorkspaceDto(
                    id: $ws->id()->value(),
                    name: $ws->name(),
                    slug: $ws->slug(),
                    role: $role !== null ? $role->value : 'member',
                );
            },
            $workspaces,
        );
    }
}
