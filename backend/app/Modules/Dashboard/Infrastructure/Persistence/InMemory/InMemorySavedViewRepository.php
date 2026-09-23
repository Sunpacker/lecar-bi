<?php

namespace App\Modules\Dashboard\Infrastructure\Persistence\InMemory;

use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Repositories\SavedViewRepositoryInterface;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;

final class InMemorySavedViewRepository implements SavedViewRepositoryInterface
{
    /** @var array<string, SavedView> */
    private array $items = [];

    public function findById(SavedViewId $id): ?SavedView
    {
        return $this->items[$id->value()] ?? null;
    }

    /**
     * @return list<SavedView>
     */
    public function findByDashboardId(DashboardId $dashboardId): array
    {
        $filtered = array_filter(
            $this->items,
            fn (SavedView $v) => $v->dashboardId()->equals($dashboardId)
        );

        return array_values($filtered);
    }

    public function save(SavedView $view): void
    {
        $this->items[$view->id()->value()] = $view;
    }

    public function delete(SavedViewId $id): void
    {
        unset($this->items[$id->value()]);
    }

    public function clearDefault(DashboardId $dashboardId, ?SavedViewId $exceptId = null): void
    {
        foreach ($this->items as $view) {
            if ($view->dashboardId()->equals($dashboardId)) {
                if ($exceptId !== null && $view->id()->equals($exceptId)) {
                    continue;
                }
                $view->markAsDefault(false);
            }
        }
    }
}
