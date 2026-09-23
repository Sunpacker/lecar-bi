<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Persistence;

use App\Modules\Workspace\Application\Contracts\WorkspaceMemberReadModelInterface;
use App\Modules\Workspace\Application\Dtos\UserDto;
use App\Modules\Workspace\Application\Dtos\WorkspaceMemberDto;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\WorkspaceId;

final class InMemoryWorkspaceMemberReadModel implements WorkspaceMemberReadModelInterface
{
    /** @var array<string, list<WorkspaceMemberDto>> */
    private array $explicitMembers = [];

    public function __construct(
        private readonly ?WorkspaceRepositoryInterface $workspaceRepository = null,
        private readonly ?UserRepositoryInterface $userRepository = null,
    ) {}

    /**
     * @param  list<WorkspaceMemberDto>  $members
     */
    public function setMembers(WorkspaceId $workspaceId, array $members): void
    {
        $this->explicitMembers[$workspaceId->value()] = $members;
    }

    /**
     * @return list<WorkspaceMemberDto>
     */
    public function getMembers(WorkspaceId $workspaceId): array
    {
        if (isset($this->explicitMembers[$workspaceId->value()])) {
            return $this->explicitMembers[$workspaceId->value()];
        }

        if ($this->workspaceRepository !== null && $this->userRepository !== null) {
            $workspace = $this->workspaceRepository->findById($workspaceId);
            if ($workspace === null) {
                return [];
            }

            $members = [];
            foreach ($workspace->memberships() as $membership) {
                $user = $this->userRepository->findById($membership->userId());
                if ($user !== null) {
                    $members[] = new WorkspaceMemberDto(
                        user: new UserDto(
                            id: $user->id()->value(),
                            email: $user->email(),
                            name: $user->name(),
                        ),
                        role: $membership->role()->value,
                    );
                }
            }

            return $members;
        }

        return [];
    }
}
