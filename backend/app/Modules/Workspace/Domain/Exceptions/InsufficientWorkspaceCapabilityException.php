<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain\Exceptions;

use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\WorkspaceCapability;
use App\Modules\Workspace\Domain\WorkspaceId;
use DomainException;

final class InsufficientWorkspaceCapabilityException extends DomainException
{
    public function __construct(
        private readonly WorkspaceId $workspaceId,
        private readonly UserId $userId,
        private readonly WorkspaceCapability $capability,
    ) {
        parent::__construct(
            "User '{$userId->value()}' lacks capability '{$capability->value}' in workspace '{$workspaceId->value()}'."
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

    public function capability(): WorkspaceCapability
    {
        return $this->capability;
    }
}
