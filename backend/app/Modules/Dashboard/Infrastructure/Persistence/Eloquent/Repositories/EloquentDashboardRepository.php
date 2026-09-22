<?php

namespace App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\DatasetType;
use App\Modules\Dashboard\Domain\DimensionType;
use App\Modules\Dashboard\Domain\MetricType;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Widget;
use App\Modules\Dashboard\Domain\WidgetGridPosition;
use App\Modules\Dashboard\Domain\WidgetId;
use App\Modules\Dashboard\Domain\WidgetQueryConfig;
use App\Modules\Dashboard\Domain\WidgetType;
use App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Models\DashboardModel;
use App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Models\DashboardWidgetModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentDashboardRepository implements DashboardRepositoryInterface
{
    public function findById(DashboardId $id): ?Dashboard
    {
        /** @var DashboardModel|null $record */
        $record = DashboardModel::query()->with('widgets')->find($id->value());

        if ($record === null) {
            return null;
        }

        return $this->toDomain($record);
    }

    /**
     * @return list<Dashboard>
     */
    public function findByWorkspaceId(string $workspaceId): array
    {
        $records = DashboardModel::query()
            ->where('workspace_id', $workspaceId)
            ->with('widgets')
            ->orderBy('created_at', 'desc')
            ->get();

        return $records->map(fn (DashboardModel $model) => $this->toDomain($model))->values()->all();
    }

    public function save(Dashboard $dashboard): void
    {
        DB::transaction(function () use ($dashboard): void {
            DashboardModel::query()->updateOrCreate(
                ['id' => $dashboard->id()->value()],
                [
                    'workspace_id' => $dashboard->workspaceId(),
                    'title' => $dashboard->title(),
                    'description' => $dashboard->description(),
                ]
            );

            // Reconcile widgets
            $existingWidgetIds = array_map(fn (Widget $w) => $w->id()->value(), $dashboard->widgets());

            DashboardWidgetModel::query()
                ->where('dashboard_id', $dashboard->id()->value())
                ->whereNotIn('id', $existingWidgetIds)
                ->delete();

            foreach ($dashboard->widgets() as $widget) {
                DashboardWidgetModel::query()->updateOrCreate(
                    [
                        'id' => $widget->id()->value(),
                    ],
                    [
                        'dashboard_id' => $dashboard->id()->value(),
                        'title' => $widget->title(),
                        'type' => $widget->type()->value,
                        'query_config' => [
                            'dataset' => $widget->queryConfig()->dataset->value,
                            'metric' => $widget->queryConfig()->metric->value,
                            'dimension' => $widget->queryConfig()->dimension?->value,
                            'date_range' => $widget->queryConfig()->dateRange,
                        ],
                        'grid_x' => $widget->position()->x,
                        'grid_y' => $widget->position()->y,
                        'grid_w' => $widget->position()->w,
                        'grid_h' => $widget->position()->h,
                        'options' => $widget->options(),
                    ]
                );
            }
        });
    }

    public function delete(DashboardId $id): void
    {
        DashboardModel::query()->where('id', $id->value())->delete();
    }

    private function toDomain(DashboardModel $model): Dashboard
    {
        $widgets = [];

        foreach ($model->widgets as $wModel) {
            $wType = WidgetType::tryFrom($wModel->type) ?? WidgetType::KPI_CARD;
            $queryCfgRaw = $wModel->query_config;

            $dataset = isset($queryCfgRaw['dataset']) && is_string($queryCfgRaw['dataset'])
                ? DatasetType::tryFrom($queryCfgRaw['dataset']) ?? DatasetType::SALES
                : DatasetType::SALES;

            $metric = isset($queryCfgRaw['metric']) && is_string($queryCfgRaw['metric'])
                ? MetricType::tryFrom($queryCfgRaw['metric']) ?? MetricType::REVENUE
                : MetricType::REVENUE;

            $dimension = isset($queryCfgRaw['dimension']) && is_string($queryCfgRaw['dimension'])
                ? DimensionType::tryFrom($queryCfgRaw['dimension'])
                : null;

            $dateRange = isset($queryCfgRaw['date_range']) && is_string($queryCfgRaw['date_range'])
                ? $queryCfgRaw['date_range']
                : null;

            $widgets[] = new Widget(
                id: new WidgetId((string) $wModel->id),
                title: (string) $wModel->title,
                type: $wType,
                queryConfig: new WidgetQueryConfig($dataset, $metric, $dimension, $dateRange),
                position: new WidgetGridPosition(
                    x: (int) $wModel->grid_x,
                    y: (int) $wModel->grid_y,
                    w: (int) $wModel->grid_w,
                    h: (int) $wModel->grid_h,
                ),
                options: is_array($wModel->options) ? $wModel->options : [],
            );
        }

        $createdAt = $model->created_at !== null
            ? DateTimeImmutable::createFromInterface($model->created_at)
            : null;

        $updatedAt = $model->updated_at !== null
            ? DateTimeImmutable::createFromInterface($model->updated_at)
            : null;

        return new Dashboard(
            id: new DashboardId((string) $model->id),
            workspaceId: (string) $model->workspace_id,
            title: (string) $model->title,
            description: $model->description !== null ? (string) $model->description : null,
            widgets: $widgets,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }
}
