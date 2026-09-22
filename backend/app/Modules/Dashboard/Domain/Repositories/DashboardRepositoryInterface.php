<?php

namespace App\Modules\Dashboard\Domain\Repositories;

use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;

interface DashboardRepositoryInterface
{
    public function findById(DashboardId $id): ?Dashboard;

    /**
     * @return list<Dashboard>
     */
    public function findByWorkspaceId(string $workspaceId): array;

    public function save(Dashboard $dashboard): void;

    public function delete(DashboardId $id): void;
}
