<?php

namespace App\Modules\Dashboard\Application\Commands;

use App\Modules\Dashboard\Application\Dtos\DashboardFiltersDto;

final readonly class UpdateSavedViewCommand
{
    public function __construct(
        public string $workspaceId,
        public string $dashboardId,
        public string $viewId,
        public string $name,
        public DashboardFiltersDto $filters,
        public bool $isDefault = false,
    ) {}
}
