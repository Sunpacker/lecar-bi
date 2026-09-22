<?php

namespace Tests\Unit\Modules\Dashboard\Application;

use App\Modules\Dashboard\Application\Commands\CreateDashboardCommand;
use App\Modules\Dashboard\Application\Commands\CreateDashboardHandler;
use App\Modules\Dashboard\Application\Commands\DeleteDashboardCommand;
use App\Modules\Dashboard\Application\Commands\DeleteDashboardHandler;
use App\Modules\Dashboard\Application\Commands\UpdateDashboardCommand;
use App\Modules\Dashboard\Application\Commands\UpdateDashboardHandler;
use App\Modules\Dashboard\Application\Dtos\WidgetDto;
use App\Modules\Dashboard\Application\Dtos\WidgetGridPositionDto;
use App\Modules\Dashboard\Application\Dtos\WidgetQueryConfigDto;
use App\Modules\Dashboard\Application\Queries\GetDashboardByIdHandler;
use App\Modules\Dashboard\Application\Queries\GetDashboardByIdQuery;
use App\Modules\Dashboard\Application\Queries\GetDashboardsHandler;
use App\Modules\Dashboard\Application\Queries\GetDashboardsQuery;
use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use PHPUnit\Framework\TestCase;

final class DashboardApplicationTest extends TestCase
{
    private DashboardRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new class implements DashboardRepositoryInterface
        {
            /** @var array<string, Dashboard> */
            public array $dashboards = [];

            public function findById(DashboardId $id): ?Dashboard
            {
                return $this->dashboards[$id->value()] ?? null;
            }

            public function findByWorkspaceId(string $workspaceId): array
            {
                return array_values(array_filter(
                    $this->dashboards,
                    fn (Dashboard $d) => $d->workspaceId() === $workspaceId
                ));
            }

            public function save(Dashboard $dashboard): void
            {
                $this->dashboards[$dashboard->id()->value()] = $dashboard;
            }

            public function delete(DashboardId $id): void
            {
                unset($this->dashboards[$id->value()]);
            }
        };
    }

    public function test_create_dashboard_use_case(): void
    {
        $handler = new CreateDashboardHandler($this->repository);
        $result = $handler->handle(new CreateDashboardCommand(
            workspaceId: 'ws-1',
            title: 'Мой дашборд',
            description: 'Тестовое описание'
        ));

        self::assertSame('Мой дашборд', $result->title);
        self::assertSame('Тестовое описание', $result->description);
        self::assertSame('ws-1', $result->workspaceId);
        self::assertEmpty($result->widgets);
    }

    public function test_get_dashboards_query(): void
    {
        $createHandler = new CreateDashboardHandler($this->repository);
        $createHandler->handle(new CreateDashboardCommand('ws-1', 'Дашборд 1'));
        $createHandler->handle(new CreateDashboardCommand('ws-1', 'Дашборд 2'));
        $createHandler->handle(new CreateDashboardCommand('ws-2', 'Дашборд другого воркспейса'));

        $queryHandler = new GetDashboardsHandler($this->repository);
        $list = $queryHandler->handle(new GetDashboardsQuery('ws-1'));

        self::assertCount(2, $list);
        self::assertSame('Дашборд 1', $list[0]->title);
        self::assertSame('Дашборд 2', $list[1]->title);
    }

    public function test_get_dashboard_by_id_query(): void
    {
        $createHandler = new CreateDashboardHandler($this->repository);
        $created = $createHandler->handle(new CreateDashboardCommand('ws-1', 'Дашборд Детали'));

        $queryHandler = new GetDashboardByIdHandler($this->repository);
        $result = $queryHandler->handle(new GetDashboardByIdQuery('ws-1', $created->id));

        self::assertSame($created->id, $result->id);
        self::assertSame('Дашборд Детали', $result->title);
    }

    public function test_get_dashboard_by_id_throws_unauthorized_if_workspace_mismatch(): void
    {
        $createHandler = new CreateDashboardHandler($this->repository);
        $created = $createHandler->handle(new CreateDashboardCommand('ws-1', 'Дашборд Тест'));

        $this->expectException(UnauthorizedWorkspaceAccessException::class);

        $queryHandler = new GetDashboardByIdHandler($this->repository);
        $queryHandler->handle(new GetDashboardByIdQuery('ws-2', $created->id));
    }

    public function test_get_dashboard_by_id_throws_not_found(): void
    {
        $this->expectException(DashboardNotFoundException::class);

        $queryHandler = new GetDashboardByIdHandler($this->repository);
        $queryHandler->handle(new GetDashboardByIdQuery('ws-1', '00000000-0000-4000-8000-000000000000'));
    }

    public function test_update_dashboard_command(): void
    {
        $createHandler = new CreateDashboardHandler($this->repository);
        $created = $createHandler->handle(new CreateDashboardCommand('ws-1', 'Исходное имя'));

        $updateHandler = new UpdateDashboardHandler($this->repository);
        $updated = $updateHandler->handle(new UpdateDashboardCommand(
            workspaceId: 'ws-1',
            dashboardId: $created->id,
            title: 'Новое имя',
            description: 'Новое описание',
            widgets: [
                new WidgetDto(
                    id: null,
                    title: 'Виджет 1',
                    type: 'kpi_card',
                    queryConfig: new WidgetQueryConfigDto(dataset: 'sales', metric: 'revenue'),
                    position: new WidgetGridPositionDto(x: 0, y: 0, w: 4, h: 2),
                    options: ['color' => 'blue']
                ),
            ]
        ));

        self::assertSame('Новое имя', $updated->title);
        self::assertSame('Новое описание', $updated->description);
        self::assertCount(1, $updated->widgets);
        self::assertSame('Виджет 1', $updated->widgets[0]->title);
    }

    public function test_delete_dashboard_command(): void
    {
        $createHandler = new CreateDashboardHandler($this->repository);
        $created = $createHandler->handle(new CreateDashboardCommand('ws-1', 'На удаление'));

        $deleteHandler = new DeleteDashboardHandler($this->repository);
        $deleteHandler->handle(new DeleteDashboardCommand('ws-1', $created->id));

        $this->expectException(DashboardNotFoundException::class);
        $queryHandler = new GetDashboardByIdHandler($this->repository);
        $queryHandler->handle(new GetDashboardByIdQuery('ws-1', $created->id));
    }
}
