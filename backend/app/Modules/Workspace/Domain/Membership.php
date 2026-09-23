<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain;

final readonly class Membership
{
    public function __construct(
        private WorkspaceId $workspaceId,
        private UserId $userId,
        private MembershipRole $role,
    ) {}

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function role(): MembershipRole
    {
        return $this->role;
    }

    public function changeRole(MembershipRole $newRole): self
    {
        if ($this->role === $newRole) {
            return $this;
        }

        return new self($this->workspaceId, $this->userId, $newRole);
    }
}
