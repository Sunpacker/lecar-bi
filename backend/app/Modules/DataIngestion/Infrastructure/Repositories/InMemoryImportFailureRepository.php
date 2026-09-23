<?php

namespace App\Modules\DataIngestion\Infrastructure\Repositories;

use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportFailureRepositoryInterface;

class InMemoryImportFailureRepository implements ImportFailureRepositoryInterface
{
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

        return array_slice($items, ($page - 1) * $perPage, $perPage);
    }

    public function countByBatchId(ImportBatchId $batchId, string $workspaceId): int
    {
        $key = $batchId->toString().'_'.$workspaceId;

        return count($this->failures[$key] ?? []);
    }
}
