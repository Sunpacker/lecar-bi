<?php

namespace App\Modules\Dashboard\Domain\Repositories;

use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;

interface SavedViewRepositoryInterface
{
    public function findById(SavedViewId $id): ?SavedView;

    /**
     * @return list<SavedView>
     */
    public function findByDashboardId(DashboardId $dashboardId): array;

    public function save(SavedView $view): void;

    public function delete(SavedViewId $id): void;

    public function clearDefault(DashboardId $dashboardId, ?SavedViewId $exceptId = null): void;
}
