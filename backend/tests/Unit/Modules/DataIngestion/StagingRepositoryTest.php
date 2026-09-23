<?php

namespace Tests\Unit\Modules\DataIngestion;

use App\Modules\DataIngestion\Application\Dtos\ImportBatchDto;
use App\Modules\DataIngestion\Application\Dtos\ImportFailureDto;
use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\RowError;
use App\Modules\DataIngestion\Domain\SourceFormat;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryStagingRecordRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StagingRepositoryTest extends TestCase
{
    #[Test]
    public function import_batch_dto_maps_all_fields_and_progress_percentage(): void
    {
        $batch = ImportBatch::create(
            id: ImportBatchId::generate(),
            workspaceId: 'ws-test',
            datasetType: DatasetType::SALES,
            sourceFormat: SourceFormat::CSV,
            originalFilename: 'sales.csv',
            storedFilePath: 'imports/sales.csv',
        );
        $batch->startValidation();
        $batch->startProcessing(100);
        $batch->recordProgress(40, 38, 2);

        $dto = ImportBatchDto::fromDomain($batch);

        self::assertSame('ws-test', $dto->workspaceId);
        self::assertSame('sales', $dto->datasetType);
        self::assertSame('csv', $dto->sourceFormat);
        self::assertSame('processing', $dto->status);
        self::assertSame(100, $dto->totalRows);
        self::assertSame(40, $dto->processedRows);
        self::assertSame(38, $dto->successfulRows);
        self::assertSame(2, $dto->failedRows);
        self::assertSame(40.0, $dto->progressPercentage);
        self::assertNull($dto->errorMessage);
    }

    #[Test]
    public function import_failure_dto_maps_row_error(): void
    {
        $error = new RowError(12, 'sku', '', 'SKU is required', 'err-1', new DateTimeImmutable('2026-09-23T10:00:00Z'));
        $dto = ImportFailureDto::fromRowError($error, 'err-1', '2026-09-23T10:00:00+00:00');

        self::assertSame('err-1', $dto->id);
        self::assertSame(12, $dto->rowNumber);
        self::assertSame('sku', $dto->field);
        self::assertSame('', $dto->value);
        self::assertSame('SKU is required', $dto->errorMessage);
        self::assertSame('2026-09-23T10:00:00+00:00', $dto->createdAt);
    }

    #[Test]
    public function staging_repository_records_and_updates_rows(): void
    {
        $repo = new InMemoryStagingRecordRepository;
        $batchId = 'batch-1';
        $workspaceId = 'ws-test';

        $salesRow = [
            'order_number' => 'ORD-100',
            'order_date' => '2026-03-01',
            'channel_code' => 'online',
            'region_code' => 'RU-MSK',
            'warehouse_code' => 'WH-01',
            'sku' => 'BRAKE-01',
            'quantity' => '2',
            'unit_price' => '150.00',
            'unit_cost' => '90.00',
            'order_status' => 'completed',
        ];

        $repo->recordSalesRow($batchId, $workspaceId, 1, $salesRow, 'staged');
        self::assertSame(1, $repo->countSalesRows($batchId));

        $repo->markSalesRowStatus($batchId, 1, 'projected');
        self::assertSame('projected', $repo->getSalesRowStatus($batchId, 1));

        $repo->deleteByBatchId($batchId, $workspaceId);
        self::assertSame(0, $repo->countSalesRows($batchId));
    }
}
