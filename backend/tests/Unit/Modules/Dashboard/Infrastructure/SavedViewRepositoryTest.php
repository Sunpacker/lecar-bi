<?php

namespace Tests\Unit\Modules\Dashboard\Infrastructure;

use App\Modules\Dashboard\Domain\DashboardFilters;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemorySavedViewRepository;
use PHPUnit\Framework\TestCase;

final class SavedViewRepositoryTest extends TestCase
{
    private InMemorySavedViewRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InMemorySavedViewRepository;
    }

    public function test_crud_and_clear_default(): void
    {
        $dashboardId = DashboardId::generate();
        $view1 = new SavedView(
            id: SavedViewId::generate(),
            dashboardId: $dashboardId,
            name: 'View 1',
            filters: new DashboardFilters(dateRange: '30d'),
            isDefault: true,
        );
        $view2 = new SavedView(
            id: SavedViewId::generate(),
            dashboardId: $dashboardId,
            name: 'View 2',
            filters: new DashboardFilters(dateRange: '90d'),
            isDefault: false,
        );

        $this->repository->save($view1);
        $this->repository->save($view2);

        $found = $this->repository->findById($view1->id());
        self::assertNotNull($found);
        self::assertSame('View 1', $found->name());

        $list = $this->repository->findByDashboardId($dashboardId);
        self::assertCount(2, $list);

        $this->repository->clearDefault($dashboardId, $view2->id());
        $updated1 = $this->repository->findById($view1->id());
        self::assertNotNull($updated1);
        self::assertFalse($updated1->isDefault());

        $this->repository->delete($view1->id());
        self::assertNull($this->repository->findById($view1->id()));
    }
}
