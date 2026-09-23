<?php

namespace App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $dashboard_id
 * @property string $name
 * @property array<string, mixed> $filters
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DashboardModel $dashboard
 */
final class DashboardSavedViewModel extends Model
{
    protected $table = 'dashboard_saved_views';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_default' => 'boolean',
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
