<?php

namespace App\Modules\Dashboard\Application\Queries;

final readonly class GetSavedViewsByDashboardQuery
{
    public function __construct(
        public string $workspaceId,
        public string $dashboardId,
    ) {}
}
