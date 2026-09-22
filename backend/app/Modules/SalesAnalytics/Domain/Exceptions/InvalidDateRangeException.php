<?php

namespace App\Modules\SalesAnalytics\Domain\Exceptions;

use DomainException;

final class InvalidDateRangeException extends DomainException
{
    public static function inverted(string $from, string $to): self
    {
        return new self("Invalid date range: '{$from}' cannot be after '{$to}'.");
    }
}
