<?php

namespace App\Modules\Workspace\Application\Queries;

use App\Modules\Workspace\Application\Dtos\CurrentWorkspaceDto;
use App\Modules\Workspace\Application\Dtos\UserDto;
use App\Modules\Workspace\Application\Dtos\WorkspaceDto;
use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Domain\Exceptions\UserNotFoundException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\UserId;

final readonly class GetCurrentWorkspaceHandler
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository,
        private UserRepositoryInterface $userRepository,
        private WorkspaceAccessGuard $accessGuard,
    ) {}

    public function handle(GetCurrentWorkspaceQuery $query): CurrentWorkspaceDto
    {
        $user = $this->userRepository->findById(new UserId($query->userId));

        if ($user === null) {
            throw UserNotFoundException::forId($query->userId);
        }

        if ($query->requestedWorkspaceId !== null && $query->requestedWorkspaceId !== '') {
            $workspace = $this->accessGuard->assertAccess($query->userId, $query->requestedWorkspaceId);
        } else {
            $workspaces = $this->workspaceRepository->findByUserId(new UserId($query->userId));
            if (empty($workspaces)) {
                throw new WorkspaceNotFoundException("No accessible workspaces found for user '{$query->userId}'.");
            }
            $workspace = $workspaces[0];
        }

        $userId = new UserId($query->userId);
        $role = $workspace->memberRole($userId);
        $capabilities = $role !== null
            ? array_map(fn ($cap) => $cap->value, $role->capabilities())
            : [];

        return new CurrentWorkspaceDto(
            user: new UserDto($user->id()->value(), $user->email(), $user->name()),
            workspace: new WorkspaceDto(
                id: $workspace->id()->value(),
                name: $workspace->name(),
                slug: $workspace->slug(),
                role: $role !== null ? $role->value : 'member',
                capabilities: $capabilities,
            ),
        );
    }
}
