<?php

namespace App\Modules\Dashboard\Application\Dtos;

final readonly class SavedViewDto
{
    public function __construct(
        public string $id,
        public string $dashboardId,
        public string $name,
        public DashboardFiltersDto $filters,
        public bool $isDefault,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}
}
