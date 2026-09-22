<?php

namespace App\Modules\Dashboard\Application\Queries;

use App\Modules\Dashboard\Application\Dtos\DashboardSummaryDto;
use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use DateTimeImmutable;

final readonly class GetDashboardsHandler
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
    ) {}

    /**
     * @return list<DashboardSummaryDto>
     */
    public function handle(GetDashboardsQuery $query): array
    {
        $dashboards = $this->dashboardRepository->findByWorkspaceId($query->workspaceId);

        return array_map(function (Dashboard $d): DashboardSummaryDto {
            return new DashboardSummaryDto(
                id: $d->id()->value(),
                workspaceId: $d->workspaceId(),
                title: $d->title(),
                description: $d->description(),
                widgetCount: $d->widgetCount(),
                createdAt: ($d->createdAt() ?? new DateTimeImmutable)->format(DATE_ATOM),
                updatedAt: ($d->updatedAt() ?? new DateTimeImmutable)->format(DATE_ATOM),
            );
        }, $dashboards);
    }
}
