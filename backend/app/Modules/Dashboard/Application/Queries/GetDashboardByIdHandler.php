<?php

namespace App\Modules\Dashboard\Application\Queries;

use App\Modules\Dashboard\Application\Dtos\DashboardDetailDto;
use App\Modules\Dashboard\Application\Dtos\WidgetDto;
use App\Modules\Dashboard\Application\Dtos\WidgetGridPositionDto;
use App\Modules\Dashboard\Application\Dtos\WidgetQueryConfigDto;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Widget;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use DateTimeImmutable;

final readonly class GetDashboardByIdHandler
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
    ) {}

    public function handle(GetDashboardByIdQuery $query): DashboardDetailDto
    {
        $dashboardId = new DashboardId($query->dashboardId);
        $dashboard = $this->dashboardRepository->findById($dashboardId);

        if ($dashboard === null) {
            throw DashboardNotFoundException::forId($query->dashboardId);
        }

        if ($dashboard->workspaceId() !== $query->workspaceId) {
            throw UnauthorizedWorkspaceAccessException::forUserAndWorkspace('current-user', $query->workspaceId);
        }

        $widgetDtos = array_map(function (Widget $w): WidgetDto {
            return new WidgetDto(
                id: $w->id()->value(),
                title: $w->title(),
                type: $w->type()->value,
                queryConfig: new WidgetQueryConfigDto(
                    dataset: $w->queryConfig()->dataset->value,
                    metric: $w->queryConfig()->metric->value,
                    dimension: $w->queryConfig()->dimension?->value,
                    dateRange: $w->queryConfig()->dateRange,
                ),
                position: new WidgetGridPositionDto(
                    x: $w->position()->x,
                    y: $w->position()->y,
                    w: $w->position()->w,
                    h: $w->position()->h,
                ),
                options: $w->options(),
            );
        }, $dashboard->widgets());

        return new DashboardDetailDto(
            id: $dashboard->id()->value(),
            workspaceId: $dashboard->workspaceId(),
            title: $dashboard->title(),
            description: $dashboard->description(),
            widgets: $widgetDtos,
            createdAt: ($dashboard->createdAt() ?? new DateTimeImmutable)->format(DATE_ATOM),
            updatedAt: ($dashboard->updatedAt() ?? new DateTimeImmutable)->format(DATE_ATOM),
        );
    }
}
