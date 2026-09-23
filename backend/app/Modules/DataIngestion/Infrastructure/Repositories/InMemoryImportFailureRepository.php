<?php

namespace App\Modules\DataIngestion\Infrastructure\Repositories;

use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportFailureRepositoryInterface;
use App\Modules\DataIngestion\Domain\RowError;

class InMemoryImportFailureRepository implements ImportFailureRepositoryInterface
{
    /** @var array<string, RowError[]> */
    private array $failures = [];

    public function recordFailures(ImportBatchId $batchId, string $workspaceId, array $rowErrors): void
    {
        $key = $batchId->toString().'_'.$workspaceId;
        if (! isset($this->failures[$key])) {
            $this->failures[$key] = [];
        }

        $this->failures[$key] = array_merge($this->failures[$key], $rowErrors);
    }

    public function listByBatchId(ImportBatchId $batchId, string $workspaceId, int $page, int $perPage): array
    {
        $key = $batchId->toString().'_'.$workspaceId;
        $items = $this->failures[$key] ?? [];

        usort($items, fn ($a, $b) => $a->rowNumber <=> $b->rowNumber);

        return array_slice($items, max(0, ($page - 1) * $perPage), $perPage);
    }

    public function countByBatchId(ImportBatchId $batchId, string $workspaceId): int
    {
        $key = $batchId->toString().'_'.$workspaceId;

        return count($this->failures[$key] ?? []);
    }

    public function deleteByBatchId(ImportBatchId $batchId, string $workspaceId): void
    {
        $key = $batchId->toString().'_'.$workspaceId;
        if (isset($this->failures[$key])) {
            unset($this->failures[$key]);
        }
    }
}
