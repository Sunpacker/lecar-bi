<?php

namespace App\Modules\Dashboard\Application\Dtos;

final readonly class DashboardDetailDto
{
    /**
     * @param  list<WidgetDto>  $widgets
     */
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $title,
        public ?string $description,
        public array $widgets,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
