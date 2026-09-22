<?php

namespace App\Modules\Dashboard\Application\Dtos;

final readonly class DashboardSummaryDto
{
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $title,
        public ?string $description,
        public int $widgetCount,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
