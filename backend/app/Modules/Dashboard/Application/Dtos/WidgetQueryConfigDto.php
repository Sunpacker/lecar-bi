<?php

namespace App\Modules\Dashboard\Application\Dtos;

final readonly class WidgetQueryConfigDto
{
    public function __construct(
        public string $dataset,
        public string $metric,
        public ?string $dimension = null,
        public ?string $dateRange = null,
    ) {}
}
