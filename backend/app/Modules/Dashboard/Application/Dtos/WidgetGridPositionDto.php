<?php

namespace App\Modules\Dashboard\Application\Dtos;

final readonly class WidgetGridPositionDto
{
    public function __construct(
        public int $x,
        public int $y,
        public int $w,
        public int $h,
    ) {}
}
