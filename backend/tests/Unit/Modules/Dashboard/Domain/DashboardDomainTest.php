<?php

namespace Tests\Unit\Modules\Dashboard\Domain;

use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\DatasetType;
use App\Modules\Dashboard\Domain\DimensionType;
use App\Modules\Dashboard\Domain\Exceptions\InvalidGridPositionException;
use App\Modules\Dashboard\Domain\MetricType;
use App\Modules\Dashboard\Domain\Widget;
use App\Modules\Dashboard\Domain\WidgetGridPosition;
use App\Modules\Dashboard\Domain\WidgetId;
use App\Modules\Dashboard\Domain\WidgetQueryConfig;
use App\Modules\Dashboard\Domain\WidgetType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DashboardDomainTest extends TestCase
{
    public function test_dashboard_can_be_created_and_manipulated(): void
    {
        $dashboardId = DashboardId::generate();
        $dashboard = new Dashboard($dashboardId, 'ws-1', 'Основной обзор');

        self::assertSame($dashboardId->value(), $dashboard->id()->value());
        self::assertSame('ws-1', $dashboard->workspaceId());
        self::assertSame('Основной обзор', $dashboard->title());
        self::assertNull($dashboard->description());
        self::assertCount(0, $dashboard->widgets());
        self::assertSame(0, $dashboard->widgetCount());

        $widget = new Widget(
            id: WidgetId::generate(),
            title: 'Выручка за 30 дней',
            type: WidgetType::KPI_CARD,
            queryConfig: new WidgetQueryConfig(DatasetType::SALES, MetricType::REVENUE, null, '30d'),
            position: new WidgetGridPosition(x: 0, y: 0, w: 4, h: 2),
            options: ['color' => 'emerald']
        );

        $dashboard->addWidget($widget);
        self::assertCount(1, $dashboard->widgets());
        self::assertSame(1, $dashboard->widgetCount());

        $dashboard->rename('Обновленный обзор', 'Новое описание');
        self::assertSame('Обновленный обзор', $dashboard->title());
        self::assertSame('Новое описание', $dashboard->description());

        $dashboard->removeWidget($widget->id());
        self::assertCount(0, $dashboard->widgets());
        self::assertSame(0, $dashboard->widgetCount());
    }

    public function test_dashboard_can_replace_all_widgets(): void
    {
        $dashboard = new Dashboard(DashboardId::generate(), 'ws-1', 'Тест');
        $widget1 = new Widget(
            WidgetId::generate(),
            'В1',
            WidgetType::LINE_CHART,
            new WidgetQueryConfig(DatasetType::SALES, MetricType::REVENUE, DimensionType::DATE),
            new WidgetGridPosition(0, 0, 6, 4)
        );
        $widget2 = new Widget(
            WidgetId::generate(),
            'В2',
            WidgetType::BAR_CHART,
            new WidgetQueryConfig(DatasetType::INVENTORY, MetricType::STOCK_VALUE, DimensionType::WAREHOUSE),
            new WidgetGridPosition(6, 0, 6, 4)
        );

        $dashboard->replaceWidgets([$widget1, $widget2]);
        self::assertCount(2, $dashboard->widgets());
    }

    public function test_invalid_grid_position_throws_exception(): void
    {
        $this->expectException(InvalidGridPositionException::class);
        new WidgetGridPosition(x: 10, y: 0, w: 4, h: 2); // 10 + 4 > 12
    }

    public function test_negative_coordinates_throw_exception(): void
    {
        $this->expectException(InvalidGridPositionException::class);
        new WidgetGridPosition(x: -1, y: 0, w: 2, h: 2);
    }

    public function test_zero_or_negative_dimensions_throw_exception(): void
    {
        $this->expectException(InvalidGridPositionException::class);
        new WidgetGridPosition(x: 0, y: 0, w: 0, h: 2);
    }

    public function test_empty_dashboard_title_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Dashboard(DashboardId::generate(), 'ws-1', '   ');
    }

    public function test_id_value_object_validation(): void
    {
        $id = DashboardId::generate();
        self::assertTrue(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id->value()) === 1);

        $this->expectException(InvalidArgumentException::class);
        new DashboardId('not-a-valid-uuid');
    }
}
