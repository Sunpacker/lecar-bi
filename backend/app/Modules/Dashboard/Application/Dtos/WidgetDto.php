<?php

namespace App\Modules\Dashboard\Application\Dtos;

final readonly class WidgetDto
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public ?string $id,
        public string $title,
        public string $type,
        public WidgetQueryConfigDto $queryConfig,
        public WidgetGridPositionDto $position,
        public array $options = [],
    ) {}
}
