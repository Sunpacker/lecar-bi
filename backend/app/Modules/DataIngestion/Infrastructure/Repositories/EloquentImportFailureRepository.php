<?php

namespace App\Modules\DataIngestion\Infrastructure\Repositories;

use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportFailureRepositoryInterface;
use App\Modules\DataIngestion\Domain\RowError;
use App\Modules\DataIngestion\Infrastructure\Models\ImportFailureModel;
use Illuminate\Support\Str;

class EloquentImportFailureRepository implements ImportFailureRepositoryInterface
{
    public function recordFailures(ImportBatchId $batchId, string $workspaceId, array $rowErrors): void
    {
        $data = array_map(function (RowError $error) use ($batchId, $workspaceId) {
            return [
                'id' => Str::uuid()->toString(),
                'batch_id' => $batchId->toString(),
                'workspace_id' => $workspaceId,
                'row_number' => $error->rowNumber,
                'field' => $error->field,
                'value' => $error->value !== null ? substr((string) $error->value, 0, 255) : null,
                'error_message' => substr($error->message, 0, 500),
                'created_at' => now(),
            ];
        }, $rowErrors);

        if (! empty($data)) {
            // Chunk inserts if necessary, but assume array isn't too large
            ImportFailureModel::insert($data);
        }
    }

    public function listByBatchId(ImportBatchId $batchId, string $workspaceId, int $page, int $perPage): array
    {
        $models = ImportFailureModel::where('batch_id', $batchId->toString())
            ->where('workspace_id', $workspaceId)
            ->orderBy('row_number')
            ->orderBy('id')
            ->skip(max(0, ($page - 1) * $perPage))
            ->take($perPage)
            ->get();

        return $models->map(function (ImportFailureModel $model) {
            /** @var mixed $rowNumber */
            $rowNumber = $model->getAttribute('row_number');
            /** @var mixed $field */
            $field = $model->getAttribute('field');
            /** @var mixed $value */
            $value = $model->getAttribute('value');
            /** @var mixed $errorMessage */
            $errorMessage = $model->getAttribute('error_message');
            /** @var mixed $id */
            $id = $model->getAttribute('id');
            /** @var mixed $createdAt */
            $createdAt = $model->getAttribute('created_at');

            $createdDate = null;
            if ($createdAt instanceof \DateTimeInterface) {
                $createdDate = new \DateTimeImmutable($createdAt->format(\DateTimeInterface::ATOM));
            } elseif (is_string($createdAt)) {
                $createdDate = new \DateTimeImmutable($createdAt);
            }

            return new RowError(
                rowNumber: (int) $rowNumber,
                field: $field !== null ? (string) $field : null,
                value: $value !== null ? (string) $value : null,
                message: (string) $errorMessage,
                id: $id !== null ? (string) $id : null,
                createdAt: $createdDate,
            );
        })->all();
    }

    public function countByBatchId(ImportBatchId $batchId, string $workspaceId): int
    {
        return ImportFailureModel::where('batch_id', $batchId->toString())
            ->where('workspace_id', $workspaceId)
            ->count();
    }

    public function deleteByBatchId(ImportBatchId $batchId, string $workspaceId): void
    {
        ImportFailureModel::where('batch_id', $batchId->toString())
            ->where('workspace_id', $workspaceId)
            ->delete();
    }
}
