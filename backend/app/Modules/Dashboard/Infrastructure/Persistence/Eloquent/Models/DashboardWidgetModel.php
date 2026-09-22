<?php

namespace App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $dashboard_id
 * @property string $title
 * @property string $type
 * @property array<string, mixed> $query_config
 * @property int $grid_x
 * @property int $grid_y
 * @property int $grid_w
 * @property int $grid_h
 * @property array<string, mixed>|null $options
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DashboardModel $dashboard
 */
final class DashboardWidgetModel extends Model
{
    protected $table = 'dashboard_widgets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'query_config' => 'array',
            'options' => 'array',
            'grid_x' => 'integer',
            'grid_y' => 'integer',
            'grid_w' => 'integer',
            'grid_h' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DashboardModel, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(DashboardModel::class, 'dashboard_id');
    }
}
