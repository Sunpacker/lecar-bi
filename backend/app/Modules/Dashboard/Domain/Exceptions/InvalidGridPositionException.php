<?php

namespace App\Modules\Dashboard\Domain\Exceptions;

use DomainException;

final class InvalidGridPositionException extends DomainException
{
    public static function forCoordinates(int $x, int $y, int $w, int $h, string $reason): self
    {
        return new self("Invalid widget grid position (x: {$x}, y: {$y}, w: {$w}, h: {$h}): {$reason}");
    }
}
