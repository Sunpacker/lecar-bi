<?php

namespace App\Modules\Dashboard\Application\Commands;

use App\Modules\Dashboard\Application\Dtos\DashboardDetailDto;
use App\Modules\Dashboard\Application\Dtos\WidgetDto;
use App\Modules\Dashboard\Application\Dtos\WidgetGridPositionDto;
use App\Modules\Dashboard\Application\Dtos\WidgetQueryConfigDto;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\DatasetType;
use App\Modules\Dashboard\Domain\DimensionType;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\MetricType;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Widget;
use App\Modules\Dashboard\Domain\WidgetGridPosition;
use App\Modules\Dashboard\Domain\WidgetId;
use App\Modules\Dashboard\Domain\WidgetQueryConfig;
use App\Modules\Dashboard\Domain\WidgetType;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class UpdateDashboardHandler
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
    ) {}

    public function handle(UpdateDashboardCommand $command): DashboardDetailDto
    {
        $dashboardId = new DashboardId($command->dashboardId);
        $dashboard = $this->dashboardRepository->findById($dashboardId);

        if ($dashboard === null) {
            throw DashboardNotFoundException::forId($command->dashboardId);
        }

        if ($dashboard->workspaceId() !== $command->workspaceId) {
            throw UnauthorizedWorkspaceAccessException::forUserAndWorkspace('current-user', $command->workspaceId);
        }

        $dashboard->rename($command->title, $command->description);

        $domainWidgets = [];
        foreach ($command->widgets as $wDto) {
            $wId = $wDto->id !== null && $wDto->id !== '' ? new WidgetId($wDto->id) : WidgetId::generate();
            $wType = WidgetType::tryFrom($wDto->type);
            if ($wType === null) {
                throw new InvalidArgumentException("Unknown widget type: '{$wDto->type}'");
            }

            $dataset = DatasetType::tryFrom($wDto->queryConfig->dataset);
            if ($dataset === null) {
                throw new InvalidArgumentException("Unknown dataset: '{$wDto->queryConfig->dataset}'");
            }

            $metric = MetricType::tryFrom($wDto->queryConfig->metric);
            if ($metric === null) {
                throw new InvalidArgumentException("Unknown metric: '{$wDto->queryConfig->metric}'");
            }

            $dimension = null;
            if ($wDto->queryConfig->dimension !== null && $wDto->queryConfig->dimension !== '') {
                $dimension = DimensionType::tryFrom($wDto->queryConfig->dimension);
                if ($dimension === null) {
                    throw new InvalidArgumentException("Unknown dimension: '{$wDto->queryConfig->dimension}'");
                }
            }

            $queryConfig = new WidgetQueryConfig(
                dataset: $dataset,
                metric: $metric,
                dimension: $dimension,
                dateRange: $wDto->queryConfig->dateRange,
            );

            $position = new WidgetGridPosition(
                x: $wDto->position->x,
                y: $wDto->position->y,
                w: $wDto->position->w,
                h: $wDto->position->h,
            );

            $domainWidgets[] = new Widget(
                id: $wId,
                title: $wDto->title,
                type: $wType,
                queryConfig: $queryConfig,
                position: $position,
                options: $wDto->options,
            );
        }

        $dashboard->replaceWidgets($domainWidgets);
        $this->dashboardRepository->save($dashboard);

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
