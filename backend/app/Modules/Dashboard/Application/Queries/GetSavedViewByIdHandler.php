<?php

namespace App\Modules\Dashboard\Application\Queries;

use App\Modules\Dashboard\Application\Dtos\DashboardFiltersDto;
use App\Modules\Dashboard\Application\Dtos\SavedViewDto;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Exceptions\SavedViewNotFoundException;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Repositories\SavedViewRepositoryInterface;
use App\Modules\Dashboard\Domain\SavedViewId;
use DateTimeImmutable;

final readonly class GetSavedViewByIdHandler
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
        private SavedViewRepositoryInterface $savedViewRepository,
    ) {}

    public function handle(GetSavedViewByIdQuery $query): SavedViewDto
    {
        $dashboardId = new DashboardId($query->dashboardId);
        $dashboard = $this->dashboardRepository->findById($dashboardId);

        if ($dashboard === null || $dashboard->workspaceId() !== $query->workspaceId) {
            throw new DashboardNotFoundException("Dashboard not found: {$query->dashboardId}");
        }

        $viewId = new SavedViewId($query->viewId);
        $savedView = $this->savedViewRepository->findById($viewId);

        if ($savedView === null || ! $savedView->dashboardId()->equals($dashboardId)) {
            throw new SavedViewNotFoundException("Saved view not found: {$query->viewId}");
        }

        return new SavedViewDto(
            id: $savedView->id()->value(),
            dashboardId: $savedView->dashboardId()->value(),
            name: $savedView->name(),
            filters: new DashboardFiltersDto(
                dateRange: $savedView->filters()->dateRange,
                dateFrom: $savedView->filters()->dateFrom,
                dateTo: $savedView->filters()->dateTo,
                categoryId: $savedView->filters()->categoryId,
                regionId: $savedView->filters()->regionId,
                warehouseId: $savedView->filters()->warehouseId,
                stockHealth: $savedView->filters()->stockHealth,
            ),
            isDefault: $savedView->isDefault(),
            createdAt: $savedView->createdAt()?->format(DateTimeImmutable::ATOM),
            updatedAt: $savedView->updatedAt()?->format(DateTimeImmutable::ATOM),
        );
    }
}
