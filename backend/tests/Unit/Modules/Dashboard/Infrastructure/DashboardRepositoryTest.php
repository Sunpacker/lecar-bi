<?php

namespace Tests\Unit\Modules\Dashboard\Infrastructure;

use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\DatasetType;
use App\Modules\Dashboard\Domain\MetricType;
use App\Modules\Dashboard\Domain\Widget;
use App\Modules\Dashboard\Domain\WidgetGridPosition;
use App\Modules\Dashboard\Domain\WidgetId;
use App\Modules\Dashboard\Domain\WidgetQueryConfig;
use App\Modules\Dashboard\Domain\WidgetType;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemoryDashboardRepository;
use PHPUnit\Framework\TestCase;

final class DashboardRepositoryTest extends TestCase
{
    public function test_in_memory_repository_crud(): void
    {
        $repo = new InMemoryDashboardRepository;

        $dashboardId = DashboardId::generate();
        $dashboard = new Dashboard($dashboardId, 'ws-1', 'Главный дашборд');

        $widget = new Widget(
            id: WidgetId::generate(),
            title: 'KPI Выручка',
            type: WidgetType::KPI_CARD,
            queryConfig: new WidgetQueryConfig(DatasetType::SALES, MetricType::REVENUE),
            position: new WidgetGridPosition(0, 0, 3, 2),
            options: ['prefix' => '₽']
        );
        $dashboard->addWidget($widget);

        $repo->save($dashboard);

        $found = $repo->findById($dashboardId);
        self::assertNotNull($found);
        self::assertSame('Главный дашборд', $found->title());
        self::assertCount(1, $found->widgets());
        self::assertSame('KPI Выручка', $found->widgets()[0]->title());
        self::assertSame('₽', $found->widgets()[0]->options()['prefix']);

        $byWorkspace = $repo->findByWorkspaceId('ws-1');
        self::assertCount(1, $byWorkspace);

        $byOther = $repo->findByWorkspaceId('ws-2');
        self::assertCount(0, $byOther);

        $repo->delete($dashboardId);
        self::assertNull($repo->findById($dashboardId));
    }
}
