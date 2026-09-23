<?php

namespace App\Modules\DataIngestion\Application\Commands;

use App\Modules\DataIngestion\Application\Contracts\ImportJobDispatcherInterface;
use App\Modules\DataIngestion\Application\Dtos\ImportBatchDto;
use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\SourceFormat;

final class UploadImportBatchHandler
{
    public function __construct(
        private readonly ImportBatchRepositoryInterface $batchRepository,
        private readonly ImportJobDispatcherInterface $jobDispatcher,
    ) {}

    public function handle(UploadImportBatchCommand $command): ImportBatchDto
    {
        $batchId = ImportBatchId::generate();
        $datasetType = DatasetType::from($command->datasetType);
        $sourceFormat = SourceFormat::from($command->sourceFormat);

        $batch = ImportBatch::create(
            id: $batchId,
            workspaceId: $command->workspaceId,
            datasetType: $datasetType,
            sourceFormat: $sourceFormat,
            originalFilename: $command->originalFilename,
            storedFilePath: $command->storedFilePath,
        );

        $this->batchRepository->save($batch);
        $this->jobDispatcher->dispatch($batchId->toString(), $command->workspaceId);

        return ImportBatchDto::fromDomain($batch);
    }
}
