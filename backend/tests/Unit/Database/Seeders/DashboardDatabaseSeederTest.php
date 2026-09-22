<?php

namespace Tests\Unit\Database\Seeders;

use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemoryDashboardRepository;
use Database\Seeders\DashboardDatabaseSeeder;
use PHPUnit\Framework\TestCase;

final class DashboardDatabaseSeederTest extends TestCase
{
    public function test_it_seeds_dashboards_with_valid_widget_identifiers(): void
    {
        $repository = new InMemoryDashboardRepository;

        (new DashboardDatabaseSeeder)->run($repository);

        $workspaceOneDashboards = $repository->findByWorkspaceId('ws-1');
        $workspaceTwoDashboards = $repository->findByWorkspaceId('ws-2');

        self::assertCount(1, $workspaceOneDashboards);
        self::assertCount(6, $workspaceOneDashboards[0]->widgets());
        self::assertCount(1, $workspaceTwoDashboards);
        self::assertCount(2, $workspaceTwoDashboards[0]->widgets());
    }
}
