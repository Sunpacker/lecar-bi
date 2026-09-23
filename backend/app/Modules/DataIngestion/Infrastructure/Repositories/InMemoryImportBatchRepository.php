<?php

namespace App\Modules\DataIngestion\Infrastructure\Repositories;

use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\ImportStatus;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;

class InMemoryImportBatchRepository implements ImportBatchRepositoryInterface
{
    /** @var array<string, ImportBatch> */
    private array $batches = [];

    public function findById(ImportBatchId $id, string $workspaceId): ?ImportBatch
    {
        if (isset($this->batches[$id->toString()]) && $this->batches[$id->toString()]->workspaceId() === $workspaceId) {
            return $this->batches[$id->toString()];
        }

        return null;
    }

    public function save(ImportBatch $batch): void
    {
        $this->batches[$batch->id()->toString()] = $batch;
    }

    public function listByWorkspace(string $workspaceId, ?ImportStatus $status, int $page, int $perPage): array
    {
        $filtered = array_filter($this->batches, function (ImportBatch $batch) use ($workspaceId, $status) {
            if ($batch->workspaceId() !== $workspaceId) {
                return false;
            }
            if ($status !== null && $batch->status() !== $status) {
                return false;
            }

            return true;
        });

        usort($filtered, fn ($a, $b) => $b->createdAt() <=> $a->createdAt());

        return array_slice($filtered, ($page - 1) * $perPage, $perPage);
    }

    public function countByWorkspace(string $workspaceId, ?ImportStatus $status = null): int
    {
        $filtered = array_filter($this->batches, function (ImportBatch $batch) use ($workspaceId, $status) {
            if ($batch->workspaceId() !== $workspaceId) {
                return false;
            }
            if ($status !== null && $batch->status() !== $status) {
                return false;
            }

            return true;
        });

        return count($filtered);
    }

    public function delete(ImportBatchId $id, string $workspaceId): void
    {
        if (isset($this->batches[$id->toString()]) && $this->batches[$id->toString()]->workspaceId() === $workspaceId) {
            unset($this->batches[$id->toString()]);
        }
    }
}
