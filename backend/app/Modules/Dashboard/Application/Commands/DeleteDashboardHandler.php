<?php

namespace App\Modules\Dashboard\Application\Commands;

use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;

final readonly class DeleteDashboardHandler
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
    ) {}

    public function handle(DeleteDashboardCommand $command): void
    {
        $dashboardId = new DashboardId($command->dashboardId);
        $dashboard = $this->dashboardRepository->findById($dashboardId);

        if ($dashboard === null) {
            throw DashboardNotFoundException::forId($command->dashboardId);
        }

        if ($dashboard->workspaceId() !== $command->workspaceId) {
            throw UnauthorizedWorkspaceAccessException::forUserAndWorkspace('current-user', $command->workspaceId);
        }

        $this->dashboardRepository->delete($dashboardId);
    }
}
