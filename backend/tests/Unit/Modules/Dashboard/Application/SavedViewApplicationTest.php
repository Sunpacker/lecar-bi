<?php

namespace Tests\Unit\Modules\Dashboard\Application;

use App\Modules\Dashboard\Application\Commands\CreateSavedViewCommand;
use App\Modules\Dashboard\Application\Commands\CreateSavedViewHandler;
use App\Modules\Dashboard\Application\Commands\DeleteSavedViewCommand;
use App\Modules\Dashboard\Application\Commands\DeleteSavedViewHandler;
use App\Modules\Dashboard\Application\Commands\UpdateSavedViewCommand;
use App\Modules\Dashboard\Application\Commands\UpdateSavedViewHandler;
use App\Modules\Dashboard\Application\Dtos\DashboardFiltersDto;
use App\Modules\Dashboard\Application\Queries\GetSavedViewByIdHandler;
use App\Modules\Dashboard\Application\Queries\GetSavedViewByIdQuery;
use App\Modules\Dashboard\Application\Queries\GetSavedViewsByDashboardHandler;
use App\Modules\Dashboard\Application\Queries\GetSavedViewsByDashboardQuery;
use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Exceptions\SavedViewNotFoundException;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemoryDashboardRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemorySavedViewRepository;
use PHPUnit\Framework\TestCase;

final class SavedViewApplicationTest extends TestCase
{
    private InMemoryDashboardRepository $dashboardRepo;

    private InMemorySavedViewRepository $savedViewRepo;

    private Dashboard $dashboard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboardRepo = new InMemoryDashboardRepository;
        $this->savedViewRepo = new InMemorySavedViewRepository;

        $this->dashboard = new Dashboard(
            id: DashboardId::generate(),
            workspaceId: 'ws-1',
            title: 'Sales Dashboard',
        );
        $this->dashboardRepo->save($this->dashboard);
    }

    public function test_create_and_get_saved_views(): void
    {
        $createHandler = new CreateSavedViewHandler($this->dashboardRepo, $this->savedViewRepo);
        $getHandler = new GetSavedViewsByDashboardHandler($this->dashboardRepo, $this->savedViewRepo);
        $getByIdHandler = new GetSavedViewByIdHandler($this->dashboardRepo, $this->savedViewRepo);

        $viewDto = $createHandler->handle(new CreateSavedViewCommand(
            workspaceId: 'ws-1',
            dashboardId: $this->dashboard->id()->value(),
            name: 'North Region View',
            filters: new DashboardFiltersDto(dateRange: '30d', regionId: 'reg-north'),
            isDefault: true,
        ));

        self::assertSame('North Region View', $viewDto->name);
        self::assertTrue($viewDto->isDefault);
        self::assertSame('30d', $viewDto->filters->dateRange);
        self::assertSame('reg-north', $viewDto->filters->regionId);

        $views = $getHandler->handle(new GetSavedViewsByDashboardQuery('ws-1', $this->dashboard->id()->value()));
        self::assertCount(1, $views);
        self::assertSame($viewDto->id, $views[0]->id);

        $single = $getByIdHandler->handle(new GetSavedViewByIdQuery('ws-1', $this->dashboard->id()->value(), $viewDto->id));
        self::assertSame($viewDto->id, $single->id);
    }

    public function test_cannot_access_dashboard_from_other_workspace(): void
    {
        $createHandler = new CreateSavedViewHandler($this->dashboardRepo, $this->savedViewRepo);

        $this->expectException(DashboardNotFoundException::class);
        $createHandler->handle(new CreateSavedViewCommand(
            workspaceId: 'ws-other',
            dashboardId: $this->dashboard->id()->value(),
            name: 'Hacked View',
            filters: new DashboardFiltersDto(dateRange: '30d'),
        ));
    }

    public function test_update_and_delete_saved_view(): void
    {
        $createHandler = new CreateSavedViewHandler($this->dashboardRepo, $this->savedViewRepo);
        $updateHandler = new UpdateSavedViewHandler($this->dashboardRepo, $this->savedViewRepo);
        $deleteHandler = new DeleteSavedViewHandler($this->dashboardRepo, $this->savedViewRepo);

        $view = $createHandler->handle(new CreateSavedViewCommand(
            workspaceId: 'ws-1',
            dashboardId: $this->dashboard->id()->value(),
            name: 'Initial View',
            filters: new DashboardFiltersDto(dateRange: '30d'),
        ));

        $updated = $updateHandler->handle(new UpdateSavedViewCommand(
            workspaceId: 'ws-1',
            dashboardId: $this->dashboard->id()->value(),
            viewId: $view->id,
            name: 'Renamed View',
            filters: new DashboardFiltersDto(dateRange: '90d'),
            isDefault: false,
        ));

        self::assertSame('Renamed View', $updated->name);
        self::assertSame('90d', $updated->filters->dateRange);

        $deleteHandler->handle(new DeleteSavedViewCommand('ws-1', $this->dashboard->id()->value(), $view->id));

        $this->expectException(SavedViewNotFoundException::class);
        $updateHandler->handle(new UpdateSavedViewCommand(
            workspaceId: 'ws-1',
            dashboardId: $this->dashboard->id()->value(),
            viewId: $view->id,
            name: 'Should Fail',
            filters: new DashboardFiltersDto(dateRange: '90d'),
        ));
    }
}
