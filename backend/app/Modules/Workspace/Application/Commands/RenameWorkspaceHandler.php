<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

use App\Modules\Workspace\Application\Dtos\WorkspaceDto;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\WorkspaceId;

final readonly class RenameWorkspaceHandler
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository,
    ) {}

    public function handle(RenameWorkspaceCommand $command): WorkspaceDto
    {
        $workspace = $this->workspaceRepository->findById(new WorkspaceId($command->workspaceId));
        if ($workspace === null) {
            throw new WorkspaceNotFoundException($command->workspaceId);
        }

        $workspace->rename($command->name);
        $this->workspaceRepository->save($workspace);

        return new WorkspaceDto(
            id: $workspace->id()->value(),
            name: $workspace->name(),
            slug: $workspace->slug(),
            role: 'owner',
        );
    }
}
