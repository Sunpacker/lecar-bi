<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain\Exceptions;

use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\WorkspaceId;
use DomainException;

final class LastWorkspaceOwnerException extends DomainException
{
    public function __construct(
        private readonly WorkspaceId $workspaceId,
        private readonly UserId $userId,
    ) {
        parent::__construct(
            "Cannot change role of user '{$userId->value()}' because workspace '{$workspaceId->value()}' must have at least one owner."
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
