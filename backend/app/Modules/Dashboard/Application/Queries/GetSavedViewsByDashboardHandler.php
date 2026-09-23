<?php

namespace App\Modules\Dashboard\Application\Queries;

use App\Modules\Dashboard\Application\Dtos\DashboardFiltersDto;
use App\Modules\Dashboard\Application\Dtos\SavedViewDto;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Repositories\SavedViewRepositoryInterface;
use App\Modules\Dashboard\Domain\SavedView;
use DateTimeImmutable;

final readonly class GetSavedViewsByDashboardHandler
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
        private SavedViewRepositoryInterface $savedViewRepository,
    ) {}

    /**
     * @return list<SavedViewDto>
     */
    public function handle(GetSavedViewsByDashboardQuery $query): array
    {
        $dashboardId = new DashboardId($query->dashboardId);
        $dashboard = $this->dashboardRepository->findById($dashboardId);

        if ($dashboard === null || $dashboard->workspaceId() !== $query->workspaceId) {
            throw new DashboardNotFoundException("Dashboard not found: {$query->dashboardId}");
        }

        $views = $this->savedViewRepository->findByDashboardId($dashboardId);

        return array_map(fn (SavedView $v) => new SavedViewDto(
            id: $v->id()->value(),
            dashboardId: $v->dashboardId()->value(),
            name: $v->name(),
            filters: new DashboardFiltersDto(
                dateRange: $v->filters()->dateRange,
                dateFrom: $v->filters()->dateFrom,
                dateTo: $v->filters()->dateTo,
                categoryId: $v->filters()->categoryId,
                regionId: $v->filters()->regionId,
                warehouseId: $v->filters()->warehouseId,
                stockHealth: $v->filters()->stockHealth,
            ),
            isDefault: $v->isDefault(),
            createdAt: $v->createdAt()?->format(DateTimeImmutable::ATOM),
            updatedAt: $v->updatedAt()?->format(DateTimeImmutable::ATOM),
        ), $views);
    }
}
