<?php

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
}
