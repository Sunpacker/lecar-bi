<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain\Exceptions;

use DomainException;

final class AlertNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Alert with ID '{$id}' not found.");
    }
}
