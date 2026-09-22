<?php

namespace App\Modules\Workspace\Domain\Exceptions;

use DomainException;

final class InvalidCredentialsException extends DomainException
{
    public static function create(): self
    {
        return new self('Invalid email or password.');
    }
}
