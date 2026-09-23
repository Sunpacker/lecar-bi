<?php

namespace App\Modules\DataIngestion\Infrastructure\Models;

use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\WorkspaceModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagingInventoryRecordModel extends Model
{
    protected $table = 'staging_inventory_records';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'workspace_id',
        'row_number',
        'snapshot_date',
        'warehouse_code',
        'sku',
        'quantity_on_hand',
        'quantity_reserved',
        'safety_stock',
        'reorder_point',
        'unit_cost',
        'status',
        'created_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ImportBatchModel, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatchModel::class, 'batch_id');
    }

    /**
     * @return BelongsTo<WorkspaceModel, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(WorkspaceModel::class, 'workspace_id');
    }
}
