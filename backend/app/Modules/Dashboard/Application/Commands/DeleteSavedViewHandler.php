<?php

namespace App\Modules\Dashboard\Application\Commands;

use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Exceptions\SavedViewNotFoundException;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Repositories\SavedViewRepositoryInterface;
use App\Modules\Dashboard\Domain\SavedViewId;

final readonly class DeleteSavedViewHandler
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
        private SavedViewRepositoryInterface $savedViewRepository,
    ) {}

    public function handle(DeleteSavedViewCommand $command): void
    {
        $dashboardId = new DashboardId($command->dashboardId);
        $dashboard = $this->dashboardRepository->findById($dashboardId);

        if ($dashboard === null || $dashboard->workspaceId() !== $command->workspaceId) {
            throw new DashboardNotFoundException("Dashboard not found: {$command->dashboardId}");
        }

        $viewId = new SavedViewId($command->viewId);
        $savedView = $this->savedViewRepository->findById($viewId);

        if ($savedView === null || ! $savedView->dashboardId()->equals($dashboardId)) {
            throw new SavedViewNotFoundException("Saved view not found: {$command->viewId}");
        }

        $this->savedViewRepository->delete($viewId);
    }
}
