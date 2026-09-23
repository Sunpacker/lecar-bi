<?php

namespace App\Modules\Dashboard\Application\Commands;

use App\Modules\Dashboard\Application\Dtos\DashboardFiltersDto;

final readonly class CreateSavedViewCommand
{
    public function __construct(
        public string $workspaceId,
        public string $dashboardId,
        public string $name,
        public DashboardFiltersDto $filters,
        public bool $isDefault = false,
    ) {}
}
