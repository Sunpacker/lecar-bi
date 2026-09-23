<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain\Exceptions;

use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\WorkspaceId;
use DomainException;

final class WorkspaceMemberNotFoundException extends DomainException
{
    public function __construct(
        private readonly WorkspaceId $workspaceId,
        private readonly UserId $userId,
    ) {
        parent::__construct(
            "User '{$userId->value()}' is not a member of workspace '{$workspaceId->value()}'."
        );
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }
}
