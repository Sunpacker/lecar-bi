<?php

namespace App\Modules\DataIngestion\Application\Commands;

use App\Modules\DataIngestion\Application\Contracts\StarSchemaProjectorInterface;
use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\Exceptions\ImportBatchNotFoundException;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\Repositories\ImportFailureRepositoryInterface;
use App\Modules\DataIngestion\Domain\Repositories\StagingRecordRepositoryInterface;
use App\Modules\DataIngestion\Domain\SourceFormat;
use App\Modules\DataIngestion\Infrastructure\Parsers\CsvImportParser;
use App\Modules\DataIngestion\Infrastructure\Parsers\JsonImportParser;
use App\Modules\DataIngestion\Infrastructure\Validators\InventoryRowValidator;
use App\Modules\DataIngestion\Infrastructure\Validators\SalesRowValidator;
use Throwable;

final class ProcessImportBatchHandler
{
    public function __construct(
        private readonly ImportBatchRepositoryInterface $batchRepository,
        private readonly ImportFailureRepositoryInterface $failureRepository,
        private readonly StagingRecordRepositoryInterface $stagingRepository,
        private readonly StarSchemaProjectorInterface $projector,
        private readonly CsvImportParser $csvParser,
        private readonly JsonImportParser $jsonParser,
        private readonly SalesRowValidator $salesValidator,
        private readonly InventoryRowValidator $inventoryValidator,
    ) {}

    public function handle(ProcessImportBatchCommand $command): void
    {
        $batchId = ImportBatchId::fromString($command->batchId);
        $batch = $this->batchRepository->findById($batchId, $command->workspaceId);

        if ($batch === null) {
            throw new ImportBatchNotFoundException("Import batch '{$command->batchId}' not found.");
        }

        try {
            $batch->startValidation();
            $this->batchRepository->save($batch);

            $filePath = $batch->storedFilePath();
            if (! file_exists($filePath)) {
                $batch->markFailed("Import file not found at path: {$filePath}");
                $this->batchRepository->save($batch);

                return;
            }

            $parser = $batch->sourceFormat() === SourceFormat::CSV ? $this->csvParser : $this->jsonParser;
            $validator = $batch->datasetType() === DatasetType::SALES ? $this->salesValidator : $this->inventoryValidator;

            // Load rows
            $rows = [];
            foreach ($parser->parse($filePath) as $row) {
                $rows[] = $row;
            }

            $totalRows = count($rows);
            $batch->startProcessing($totalRows);
            $this->batchRepository->save($batch);

            if ($totalRows === 0) {
                $batch->markCompleted();
                $this->batchRepository->save($batch);

                return;
            }

            $successful = 0;
            $failed = 0;
            $rowNumber = 1;
            $failuresToRecord = [];

            foreach ($rows as $row) {
                $errors = $validator->validate($row, $rowNumber);

                if (! empty($errors)) {
                    $failed++;
                    foreach ($errors as $error) {
                        $failuresToRecord[] = $error;
                    }
                } else {
                    // Valid row -> record in staging and project into Star Schema
                    if ($batch->datasetType() === DatasetType::SALES) {
                        $this->stagingRepository->recordSalesRow($batch->id()->toString(), $batch->workspaceId(), $rowNumber, $row, 'staged');
                        $this->projector->projectSalesRow($batch->workspaceId(), $row);
                        $this->stagingRepository->markSalesRowStatus($batch->id()->toString(), $rowNumber, 'projected');
                    } else {
                        $this->stagingRepository->recordInventoryRow($batch->id()->toString(), $batch->workspaceId(), $rowNumber, $row, 'staged');
                        $this->projector->projectInventoryRow($batch->workspaceId(), $row);
                        $this->stagingRepository->markInventoryRowStatus($batch->id()->toString(), $rowNumber, 'projected');
                    }
                    $successful++;
                }

                $rowNumber++;
            }

            if (! empty($failuresToRecord)) {
                $this->failureRepository->recordFailures($batch->id(), $batch->workspaceId(), $failuresToRecord);
            }

            $batch->recordProgress($totalRows, $successful, $failed);

            if ($failed > 0 && $successful > 0) {
                $batch->markCompletedWithErrors();
            } elseif ($failed > 0) {
                $batch->markFailed('All rows in dataset failed validation.');
            } else {
                $batch->markCompleted();
            }

            $this->batchRepository->save($batch);
        } catch (Throwable $e) {
            if (! $batch->isCompleted()) {
                $batch->markFailed($e->getMessage());
                $this->batchRepository->save($batch);
            }
            throw $e;
        }
    }
}
