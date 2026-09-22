<?php

namespace App\Modules\Dashboard\Infrastructure\Persistence\InMemory;

use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;

final class InMemoryDashboardRepository implements DashboardRepositoryInterface
{
    /** @var array<string, Dashboard> */
    private array $dashboards = [];

    public function findById(DashboardId $id): ?Dashboard
    {
        return $this->dashboards[$id->value()] ?? null;
    }

    /**
     * @return list<Dashboard>
     */
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
}
