<?php

namespace App\Modules\Workspace\Domain\Exceptions;

use DomainException;

final class WorkspaceNotFoundException extends DomainException
{
    public static function forId(string $id): self
    {
        return new self("Workspace '{$id}' not found.");
    }
}
