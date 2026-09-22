<?php

namespace Database\Seeders;

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
use DateTimeImmutable;
use Illuminate\Database\Seeder;

final class DashboardDatabaseSeeder extends Seeder
{
    public function run(DashboardRepositoryInterface $dashboardRepository): void
    {
        $now = new DateTimeImmutable;

        // Dashboard for ws-1
        $ws1Dashboard = new Dashboard(
            id: new DashboardId('d0000001-0000-4000-8000-000000000001'),
            workspaceId: 'ws-1',
            title: 'Сводный обзор бизнеса',
            description: 'Оперативные показатели выручки, заказов и структуры складских запасов',
            widgets: [
                new Widget(
                    id: new WidgetId('a0000001-0000-4000-8000-000000000001'),
                    title: 'Выручка за 30 дней',
                    type: WidgetType::KPI_CARD,
                    queryConfig: new WidgetQueryConfig(
                        dataset: DatasetType::SALES,
                        metric: MetricType::REVENUE,
                        dateRange: '30d'
                    ),
                    position: new WidgetGridPosition(x: 0, y: 0, w: 3, h: 2),
                    options: ['unit' => 'currency']
                ),
                new Widget(
                    id: new WidgetId('a0000001-0000-4000-8000-000000000002'),
                    title: 'Количество заказов',
                    type: WidgetType::KPI_CARD,
                    queryConfig: new WidgetQueryConfig(
                        dataset: DatasetType::SALES,
                        metric: MetricType::ORDER_COUNT,
                        dateRange: '30d'
                    ),
                    position: new WidgetGridPosition(x: 3, y: 0, w: 3, h: 2),
                    options: ['unit' => 'count']
                ),
                new Widget(
                    id: new WidgetId('a0000001-0000-4000-8000-000000000003'),
                    title: 'Стоимость запасов',
                    type: WidgetType::KPI_CARD,
                    queryConfig: new WidgetQueryConfig(
                        dataset: DatasetType::INVENTORY,
                        metric: MetricType::STOCK_VALUE
                    ),
                    position: new WidgetGridPosition(x: 6, y: 0, w: 3, h: 2),
                    options: ['unit' => 'currency']
                ),
                new Widget(
                    id: new WidgetId('a0000001-0000-4000-8000-000000000004'),
                    title: 'Критический дефицит',
                    type: WidgetType::KPI_CARD,
                    queryConfig: new WidgetQueryConfig(
                        dataset: DatasetType::INVENTORY,
                        metric: MetricType::OUT_OF_STOCK_COUNT
                    ),
                    position: new WidgetGridPosition(x: 9, y: 0, w: 3, h: 2),
                    options: ['status' => 'critical']
                ),
                new Widget(
                    id: new WidgetId('a0000001-0000-4000-8000-000000000005'),
                    title: 'Динамика выручки и заказов',
                    type: WidgetType::LINE_CHART,
                    queryConfig: new WidgetQueryConfig(
                        dataset: DatasetType::SALES,
                        metric: MetricType::REVENUE,
                        dimension: DimensionType::DATE,
                        dateRange: '90d'
                    ),
                    position: new WidgetGridPosition(x: 0, y: 2, w: 8, h: 4),
                    options: ['show_legend' => true]
                ),
                new Widget(
                    id: new WidgetId('a0000001-0000-4000-8000-000000000006'),
                    title: 'Выручка по категориям',
                    type: WidgetType::DONUT_CHART,
                    queryConfig: new WidgetQueryConfig(
                        dataset: DatasetType::SALES,
                        metric: MetricType::REVENUE,
                        dimension: DimensionType::CATEGORY,
                        dateRange: '90d'
                    ),
                    position: new WidgetGridPosition(x: 8, y: 2, w: 4, h: 4),
                    options: ['show_legend' => true]
                ),
            ],
            createdAt: $now,
            updatedAt: $now
        );

        // Dashboard for ws-2
        $ws2Dashboard = new Dashboard(
            id: new DashboardId('d0000002-0000-4000-8000-000000000001'),
            workspaceId: 'ws-2',
            title: 'Оптовые поставки и склад',
            description: 'Сводный дашборд оптового распределения',
            widgets: [
                new Widget(
                    id: new WidgetId('a0000002-0000-4000-8000-000000000001'),
                    title: 'Оптовая выручка',
                    type: WidgetType::KPI_CARD,
                    queryConfig: new WidgetQueryConfig(
                        dataset: DatasetType::SALES,
                        metric: MetricType::REVENUE,
                        dateRange: '30d'
                    ),
                    position: new WidgetGridPosition(x: 0, y: 0, w: 6, h: 2),
                    options: ['unit' => 'currency']
                ),
                new Widget(
                    id: new WidgetId('a0000002-0000-4000-8000-000000000002'),
                    title: 'Остатки на складах',
                    type: WidgetType::BAR_CHART,
                    queryConfig: new WidgetQueryConfig(
                        dataset: DatasetType::INVENTORY,
                        metric: MetricType::STOCK_QUANTITY,
                        dimension: DimensionType::WAREHOUSE
                    ),
                    position: new WidgetGridPosition(x: 6, y: 0, w: 6, h: 4),
                    options: ['show_legend' => true]
                ),
            ],
            createdAt: $now,
            updatedAt: $now
        );

        $dashboardRepository->save($ws1Dashboard);
        $dashboardRepository->save($ws2Dashboard);
    }
}
