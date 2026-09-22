<?php

namespace App\Modules\Dashboard\Domain\Exceptions;

use DomainException;

final class DashboardNotFoundException extends DomainException
{
    public static function forId(string $id): self
    {
        return new self("Dashboard with id '{$id}' was not found.");
    }
}
