<?php

namespace App\Modules\Workspace\Domain\Exceptions;

use DomainException;

final class UnauthorizedWorkspaceAccessException extends DomainException
{
    public static function forUserAndWorkspace(string $userId, string $workspaceId): self
    {
        return new self("User '{$userId}' does not have access to workspace '{$workspaceId}'.");
    }
}
