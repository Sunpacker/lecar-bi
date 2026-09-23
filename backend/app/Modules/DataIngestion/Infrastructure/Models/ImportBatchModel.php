<?php

namespace App\Modules\DataIngestion\Infrastructure\Models;

use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\WorkspaceModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatchModel extends Model
{
    protected $table = 'import_batches';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'dataset_type',
        'source_format',
        'original_filename',
        'stored_file_path',
        'status',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<WorkspaceModel, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(WorkspaceModel::class, 'workspace_id');
    }

    /**
     * @return HasMany<StagingSalesRecordModel, $this>
     */
    public function salesRecords(): HasMany
    {
        return $this->hasMany(StagingSalesRecordModel::class, 'batch_id');
    }

    /**
     * @return HasMany<StagingInventoryRecordModel, $this>
     */
    public function inventoryRecords(): HasMany
    {
        return $this->hasMany(StagingInventoryRecordModel::class, 'batch_id');
    }

    /**
     * @return HasMany<ImportFailureModel, $this>
     */
    public function failures(): HasMany
    {
        return $this->hasMany(ImportFailureModel::class, 'batch_id');
    }
}
