<?php

namespace App\Modules\Workspace\Domain\Exceptions;

use DomainException;

final class UserNotFoundException extends DomainException
{
    public static function forId(string $id): self
    {
        return new self("User '{$id}' not found.");
    }
}
