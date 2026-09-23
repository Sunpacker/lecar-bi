<?php

namespace App\Modules\DataIngestion\Application\Commands;

use App\Modules\DataIngestion\Application\Contracts\ImportJobDispatcherInterface;
use App\Modules\DataIngestion\Application\Dtos\ImportBatchDto;
use App\Modules\DataIngestion\Domain\Exceptions\ImportBatchNotFoundException;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\Repositories\ImportFailureRepositoryInterface;
use App\Modules\DataIngestion\Domain\Repositories\StagingRecordRepositoryInterface;

final class RetryImportBatchHandler
{
    public function __construct(
        private readonly ImportBatchRepositoryInterface $batchRepository,
        private readonly ImportFailureRepositoryInterface $failureRepository,
        private readonly StagingRecordRepositoryInterface $stagingRepository,
        private readonly ImportJobDispatcherInterface $jobDispatcher,
    ) {}

    public function handle(RetryImportBatchCommand $command): ImportBatchDto
    {
        $batchId = ImportBatchId::fromString($command->batchId);
        $batch = $this->batchRepository->findById($batchId, $command->workspaceId);

        if ($batch === null) {
            throw new ImportBatchNotFoundException("Import batch '{$command->batchId}' not found in current workspace.");
        }

        // prepareRetry checks status, throws CannotRetryImportException if not allowed
        $batch->prepareRetry();

        // Clear previous failures and staging records for this batch
        $this->failureRepository->deleteByBatchId($batchId, $command->workspaceId);
        $this->stagingRepository->deleteByBatchId($batchId->toString(), $command->workspaceId);

        $this->batchRepository->save($batch);
        $this->jobDispatcher->dispatch($batchId->toString(), $command->workspaceId);

        return ImportBatchDto::fromDomain($batch);
    }
}
