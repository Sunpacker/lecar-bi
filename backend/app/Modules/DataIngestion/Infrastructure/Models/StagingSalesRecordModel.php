<?php

namespace App\Modules\DataIngestion\Infrastructure\Models;

use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\WorkspaceModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagingSalesRecordModel extends Model
{
    protected $table = 'staging_sales_records';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'workspace_id',
        'row_number',
        'order_number',
        'order_date',
        'channel_code',
        'region_code',
        'warehouse_code',
        'sku',
        'quantity',
        'unit_price',
        'unit_cost',
        'order_status',
        'status',
        'created_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatchModel::class, 'batch_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(WorkspaceModel::class, 'workspace_id');
    }
}
