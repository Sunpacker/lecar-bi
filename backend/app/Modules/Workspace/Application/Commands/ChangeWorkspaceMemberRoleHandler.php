<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

use App\Modules\Workspace\Application\Contracts\WorkspaceTransactionManagerInterface;
use App\Modules\Workspace\Application\Dtos\UserDto;
use App\Modules\Workspace\Application\Dtos\WorkspaceMemberDto;
use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceMemberNotFoundException;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\WorkspaceCapability;
use App\Modules\Workspace\Domain\WorkspaceId;
use InvalidArgumentException;

final readonly class ChangeWorkspaceMemberRoleHandler
{
    public function __construct(
        private WorkspaceAccessGuard $accessGuard,
        private WorkspaceRepositoryInterface $workspaceRepository,
        private UserRepositoryInterface $userRepository,
        private WorkspaceTransactionManagerInterface $transactionManager,
    ) {}

    public function handle(ChangeWorkspaceMemberRoleCommand $command): WorkspaceMemberDto
    {
        $this->accessGuard->assertCapability(
            $command->actorUserId,
            $command->workspaceId,
            WorkspaceCapability::WORKSPACE_MEMBERS_MANAGE,
        );

        $newRole = MembershipRole::tryFrom($command->role);
        if ($newRole === null) {
            throw new InvalidArgumentException("Invalid role '{$command->role}'.");
        }

        return $this->transactionManager->transaction(function () use ($command, $newRole): WorkspaceMemberDto {
            $workspaceId = new WorkspaceId($command->workspaceId);
            $workspace = $this->workspaceRepository->findByIdForUpdate($workspaceId);

            if ($workspace === null) {
                throw new WorkspaceMemberNotFoundException($workspaceId, new UserId($command->targetUserId));
            }

            $targetUserId = new UserId($command->targetUserId);
            if (! $workspace->hasMember($targetUserId)) {
                throw new WorkspaceMemberNotFoundException($workspaceId, $targetUserId);
            }

            $workspace->changeMemberRole($targetUserId, $newRole);
            $this->workspaceRepository->save($workspace);

            $targetUser = $this->userRepository->findById($targetUserId);
            $userDto = $targetUser !== null
                ? new UserDto($targetUser->id()->value(), $targetUser->email(), $targetUser->name())
                : new UserDto($command->targetUserId, '', '');

            return new WorkspaceMemberDto(
                user: $userDto,
                role: $newRole->value,
            );
        });
    }
}
