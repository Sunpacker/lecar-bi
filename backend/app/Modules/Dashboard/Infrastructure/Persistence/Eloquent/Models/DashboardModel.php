<?php

namespace App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $workspace_id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, DashboardWidgetModel> $widgets
 */
final class DashboardModel extends Model
{
    protected $table = 'dashboards';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * @return HasMany<DashboardWidgetModel, $this>
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidgetModel::class, 'dashboard_id')
            ->orderBy('grid_y')
            ->orderBy('grid_x');
    }

    /**
     * @return HasMany<DashboardSavedViewModel, $this>
     */
    public function savedViews(): HasMany
    {
        return $this->hasMany(DashboardSavedViewModel::class, 'dashboard_id')
            ->orderBy('created_at', 'asc');
    }
}
