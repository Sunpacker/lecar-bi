<?php

namespace App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Dashboard\Domain\DashboardFilters;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Repositories\SavedViewRepositoryInterface;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;
use App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Models\DashboardSavedViewModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentSavedViewRepository implements SavedViewRepositoryInterface
{
    public function findById(SavedViewId $id): ?SavedView
    {
        /** @var DashboardSavedViewModel|null $record */
        $record = DashboardSavedViewModel::query()->find($id->value());

        if ($record === null) {
            return null;
        }

        return $this->toDomain($record);
    }

    /**
     * @return list<SavedView>
     */
    public function findByDashboardId(DashboardId $dashboardId): array
    {
        $records = DashboardSavedViewModel::query()
            ->where('dashboard_id', $dashboardId->value())
            ->orderBy('created_at', 'asc')
            ->get();

        return $records->map(fn (DashboardSavedViewModel $m) => $this->toDomain($m))->values()->all();
    }

    public function save(SavedView $view): void
    {
        DashboardSavedViewModel::query()->updateOrCreate(
            ['id' => $view->id()->value()],
            [
                'dashboard_id' => $view->dashboardId()->value(),
                'name' => $view->name(),
                'filters' => $view->filters()->toArray(),
                'is_default' => $view->isDefault(),
            ]
        );
    }

    public function delete(SavedViewId $id): void
    {
        DashboardSavedViewModel::query()->where('id', $id->value())->delete();
    }

    public function clearDefault(DashboardId $dashboardId, ?SavedViewId $exceptId = null): void
    {
        DB::transaction(function () use ($dashboardId, $exceptId): void {
            $query = DashboardSavedViewModel::query()
                ->where('dashboard_id', $dashboardId->value());

            if ($exceptId !== null) {
                $query->where('id', '!=', $exceptId->value());
            }

            $query->update(['is_default' => false]);
        });
    }

    private function toDomain(DashboardSavedViewModel $model): SavedView
    {
        $createdAt = $model->created_at !== null
            ? DateTimeImmutable::createFromInterface($model->created_at)
            : null;

        $updatedAt = $model->updated_at !== null
            ? DateTimeImmutable::createFromInterface($model->updated_at)
            : null;

        return new SavedView(
            id: new SavedViewId((string) $model->id),
            dashboardId: new DashboardId((string) $model->dashboard_id),
            name: (string) $model->name,
            filters: DashboardFilters::fromArray($model->filters),
            isDefault: (bool) $model->is_default,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }
}
