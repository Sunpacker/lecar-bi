<?php

namespace App\Modules\Dashboard\Domain;

use App\Modules\Dashboard\Domain\Exceptions\InvalidGridPositionException;

final readonly class WidgetGridPosition
{
    public function __construct(
        public int $x,
        public int $y,
        public int $w,
        public int $h,
    ) {
        if ($this->x < 0 || $this->x > 11) {
            throw InvalidGridPositionException::forCoordinates($this->x, $this->y, $this->w, $this->h, 'x coordinate must be between 0 and 11.');
        }

        if ($this->w < 1 || $this->w > 12) {
            throw InvalidGridPositionException::forCoordinates($this->x, $this->y, $this->w, $this->h, 'width must be between 1 and 12.');
        }

        if ($this->x + $this->w > 12) {
            throw InvalidGridPositionException::forCoordinates($this->x, $this->y, $this->w, $this->h, 'x + width exceeds 12-column grid limit.');
        }

        if ($this->y < 0) {
            throw InvalidGridPositionException::forCoordinates($this->x, $this->y, $this->w, $this->h, 'y coordinate cannot be negative.');
        }

        if ($this->h < 1 || $this->h > 24) {
            throw InvalidGridPositionException::forCoordinates($this->x, $this->y, $this->w, $this->h, 'height must be between 1 and 24.');
        }
    }
}
