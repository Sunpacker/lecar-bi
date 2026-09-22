<?php

namespace App\Modules\Dashboard\Application\Commands;

final readonly class DeleteDashboardCommand
{
    public function __construct(
        public string $workspaceId,
        public string $dashboardId,
    ) {}
}
