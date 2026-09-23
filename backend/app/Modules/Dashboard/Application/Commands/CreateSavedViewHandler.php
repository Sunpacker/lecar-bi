<?php

namespace App\Modules\Dashboard\Application\Commands;

use App\Modules\Dashboard\Application\Dtos\DashboardFiltersDto;
use App\Modules\Dashboard\Application\Dtos\SavedViewDto;
use App\Modules\Dashboard\Domain\DashboardFilters;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Repositories\SavedViewRepositoryInterface;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;
use DateTimeImmutable;

final readonly class CreateSavedViewHandler
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
        private SavedViewRepositoryInterface $savedViewRepository,
    ) {}

    public function handle(CreateSavedViewCommand $command): SavedViewDto
    {
        $dashboardId = new DashboardId($command->dashboardId);
        $dashboard = $this->dashboardRepository->findById($dashboardId);

        if ($dashboard === null || $dashboard->workspaceId() !== $command->workspaceId) {
            throw new DashboardNotFoundException("Dashboard not found: {$command->dashboardId}");
        }

        if ($command->isDefault) {
            $this->savedViewRepository->clearDefault($dashboardId);
        }

        $viewId = SavedViewId::generate();
        $domainFilters = new DashboardFilters(
            dateRange: $command->filters->dateRange,
            dateFrom: $command->filters->dateFrom,
            dateTo: $command->filters->dateTo,
            categoryId: $command->filters->categoryId,
            regionId: $command->filters->regionId,
            warehouseId: $command->filters->warehouseId,
            stockHealth: $command->filters->stockHealth,
        );

        $now = new DateTimeImmutable;
        $savedView = new SavedView(
            id: $viewId,
            dashboardId: $dashboardId,
            name: $command->name,
            filters: $domainFilters,
            isDefault: $command->isDefault,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->savedViewRepository->save($savedView);

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
