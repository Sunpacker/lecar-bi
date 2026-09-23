<?php

namespace App\Modules\DataIngestion\Infrastructure\Repositories;

use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\ImportStatus;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\SourceFormat;
use App\Modules\DataIngestion\Infrastructure\Models\ImportBatchModel;
use DateTimeImmutable;
use Illuminate\Support\Carbon;

class EloquentImportBatchRepository implements ImportBatchRepositoryInterface
{
    public function findById(ImportBatchId $id, string $workspaceId): ?ImportBatch
    {
        $model = ImportBatchModel::where('id', $id->toString())
            ->where('workspace_id', $workspaceId)
            ->first();

        if (! $model) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function save(ImportBatch $batch): void
    {
        $data = [
            'workspace_id' => $batch->workspaceId(),
            'dataset_type' => $batch->datasetType()->value,
            'source_format' => $batch->sourceFormat()->value,
            'original_filename' => $batch->originalFilename(),
            'stored_file_path' => $batch->storedFilePath(),
            'status' => $batch->status()->value,
            'total_rows' => $batch->totalRows(),
            'processed_rows' => $batch->processedRows(),
            'successful_rows' => $batch->successfulRows(),
            'failed_rows' => $batch->failedRows(),
            'error_message' => $batch->errorMessage(),
            'created_at' => Carbon::instance($batch->createdAt()),
            'completed_at' => $batch->completedAt() ? Carbon::instance($batch->completedAt()) : null,
        ];

        ImportBatchModel::updateOrCreate(
            ['id' => $batch->id()->toString()],
            $data
        );
    }

    public function listByWorkspace(string $workspaceId, ?ImportStatus $status, int $page, int $perPage): array
    {
        $query = ImportBatchModel::where('workspace_id', $workspaceId)
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status->value);
        }

        $models = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $models->map(fn ($model) => $this->toDomain($model))->all();
    }

    public function countByWorkspace(string $workspaceId, ?ImportStatus $status = null): int
    {
        $query = ImportBatchModel::where('workspace_id', $workspaceId);

        if ($status) {
            $query->where('status', $status->value);
        }

        return $query->count();
    }

    private function toDomain(ImportBatchModel $model): ImportBatch
    {
        return new ImportBatch(
            ImportBatchId::fromString($model->id),
            $model->workspace_id,
            DatasetType::from($model->dataset_type),
            SourceFormat::from($model->source_format),
            $model->original_filename,
            $model->stored_file_path,
            ImportStatus::from($model->status),
            $model->total_rows,
            $model->processed_rows,
            $model->successful_rows,
            $model->failed_rows,
            $model->error_message,
            new DateTimeImmutable($model->created_at->toIso8601String()),
            $model->completed_at ? new DateTimeImmutable($model->completed_at->toIso8601String()) : null
        );
    }

    public function delete(ImportBatchId $id, string $workspaceId): void
    {
        ImportBatchModel::where('id', $id->toString())
            ->where('workspace_id', $workspaceId)
            ->delete();
    }
}
