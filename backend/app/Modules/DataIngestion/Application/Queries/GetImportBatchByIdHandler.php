<?php

namespace App\Modules\DataIngestion\Application\Queries;

use App\Modules\DataIngestion\Application\Dtos\ImportBatchDto;
use App\Modules\DataIngestion\Domain\Exceptions\ImportBatchNotFoundException;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;

final class GetImportBatchByIdHandler
{
    public function __construct(
        private readonly ImportBatchRepositoryInterface $batchRepository,
    ) {}

    public function handle(GetImportBatchByIdQuery $query): ImportBatchDto
    {
        $batch = $this->batchRepository->findById(
            ImportBatchId::fromString($query->batchId),
            $query->workspaceId,
        );

        if ($batch === null) {
            throw new ImportBatchNotFoundException("Import batch '{$query->batchId}' not found in current workspace.");
        }

        return ImportBatchDto::fromDomain($batch);
    }
}
