<?php

namespace Tests\Unit\Modules\DataIngestion;

use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\Exceptions\CannotRetryImportException;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\ImportStatus;
use App\Modules\DataIngestion\Domain\RowError;
use App\Modules\DataIngestion\Domain\SourceFormat;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportBatchDomainTest extends TestCase
{
    #[Test]
    public function creates_import_batch_with_initial_pending_state(): void
    {
        $id = ImportBatchId::generate();
        $batch = new ImportBatch(
            id: $id,
            workspaceId: 'ws-1',
            datasetType: DatasetType::SALES,
            sourceFormat: SourceFormat::CSV,
            originalFilename: 'orders_2026.csv',
            storedFilePath: 'imports/ws-1/test.csv',
            status: ImportStatus::PENDING,
            totalRows: 0,
            processedRows: 0,
            successfulRows: 0,
            failedRows: 0,
            errorMessage: null,
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            completedAt: null,
        );

        self::assertSame($id->toString(), $batch->id()->toString());
        self::assertSame('ws-1', $batch->workspaceId());
        self::assertSame(DatasetType::SALES, $batch->datasetType());
        self::assertSame(SourceFormat::CSV, $batch->sourceFormat());
        self::assertSame(ImportStatus::PENDING, $batch->status());
        self::assertSame(0, $batch->totalRows());
        self::assertFalse($batch->isCompleted());
    }

    #[Test]
    public function progresses_through_lifecycle_to_completed(): void
    {
        $batch = new ImportBatch(
            id: ImportBatchId::generate(),
            workspaceId: 'ws-1',
            datasetType: DatasetType::SALES,
            sourceFormat: SourceFormat::CSV,
            originalFilename: 'orders.csv',
            storedFilePath: 'imports/ws-1/orders.csv',
            status: ImportStatus::PENDING,
            totalRows: 0,
            processedRows: 0,
            successfulRows: 0,
            failedRows: 0,
            errorMessage: null,
            createdAt: new DateTimeImmutable,
            completedAt: null,
        );

        $batch->startValidation();
        self::assertSame(ImportStatus::VALIDATING, $batch->status());

        $batch->startProcessing(100);
        self::assertSame(ImportStatus::PROCESSING, $batch->status());
        self::assertSame(100, $batch->totalRows());

        $batch->recordProgress(50, 48, 2);
        self::assertSame(50, $batch->processedRows());
        self::assertSame(48, $batch->successfulRows());
        self::assertSame(2, $batch->failedRows());

        $batch->markCompletedWithErrors();
        self::assertSame(ImportStatus::COMPLETED_WITH_ERRORS, $batch->status());
        self::assertTrue($batch->isCompleted());
        self::assertNotNull($batch->completedAt());
    }

    #[Test]
    public function retries_eligible_batch(): void
    {
        $batch = new ImportBatch(
            id: ImportBatchId::generate(),
            workspaceId: 'ws-1',
            datasetType: DatasetType::INVENTORY,
            sourceFormat: SourceFormat::JSON,
            originalFilename: 'stock.json',
            storedFilePath: 'imports/ws-1/stock.json',
            status: ImportStatus::FAILED,
            totalRows: 50,
            processedRows: 10,
            successfulRows: 5,
            failedRows: 5,
            errorMessage: 'Timeout error',
            createdAt: new DateTimeImmutable,
            completedAt: null,
        );

        self::assertTrue($batch->canRetry());
        $batch->prepareRetry();
        self::assertSame(ImportStatus::PENDING, $batch->status());
        self::assertNull($batch->errorMessage());
    }

    #[Test]
    public function cannot_retry_in_progress_batch(): void
    {
        $batch = new ImportBatch(
            id: ImportBatchId::generate(),
            workspaceId: 'ws-1',
            datasetType: DatasetType::SALES,
            sourceFormat: SourceFormat::CSV,
            originalFilename: 'orders.csv',
            storedFilePath: 'imports/ws-1/orders.csv',
            status: ImportStatus::PROCESSING,
            totalRows: 100,
            processedRows: 20,
            successfulRows: 20,
            failedRows: 0,
            errorMessage: null,
            createdAt: new DateTimeImmutable,
            completedAt: null,
        );

        self::assertFalse($batch->canRetry());
        $this->expectException(CannotRetryImportException::class);
        $batch->prepareRetry();
    }

    #[Test]
    public function can_create_import_batch_using_factory(): void
    {
        $id = ImportBatchId::generate();
        $batch = ImportBatch::create(
            id: $id,
            workspaceId: 'ws-2',
            datasetType: DatasetType::INVENTORY,
            sourceFormat: SourceFormat::JSON,
            originalFilename: 'stock_2026.json',
            storedFilePath: 'imports/ws-2/stock_2026.json',
        );

        self::assertSame($id->toString(), $batch->id()->toString());
        self::assertSame(ImportStatus::PENDING, $batch->status());
        self::assertSame(0, $batch->totalRows());
        self::assertNull($batch->completedAt());
    }

    #[Test]
    public function cannot_start_processing_from_completed_state(): void
    {
        $batch = ImportBatch::create(
            id: ImportBatchId::generate(),
            workspaceId: 'ws-1',
            datasetType: DatasetType::SALES,
            sourceFormat: SourceFormat::CSV,
            originalFilename: 'test.csv',
            storedFilePath: 'test.csv',
        );
        $batch->startValidation();
        $batch->startProcessing(10);
        $batch->markCompleted();

        $this->expectException(\DomainException::class);
        $batch->startProcessing(20);
    }

    #[Test]
    public function marking_failed_records_reason_and_completion_time(): void
    {
        $batch = ImportBatch::create(
            id: ImportBatchId::generate(),
            workspaceId: 'ws-1',
            datasetType: DatasetType::SALES,
            sourceFormat: SourceFormat::CSV,
            originalFilename: 'test.csv',
            storedFilePath: 'test.csv',
        );
        $batch->startValidation();
        $batch->startProcessing(10);

        $batch->markFailed('Something went wrong');

        self::assertSame(ImportStatus::FAILED, $batch->status());
        self::assertSame('Something went wrong', $batch->errorMessage());
        self::assertNotNull($batch->completedAt());
    }

    #[Test]
    public function import_batch_id_equality_and_validation(): void
    {
        $id1 = ImportBatchId::generate();
        $id2 = ImportBatchId::fromString($id1->toString());
        $id3 = ImportBatchId::generate();

        self::assertTrue($id1->equals($id2));
        self::assertFalse($id1->equals($id3));

        $this->expectException(\InvalidArgumentException::class);
        ImportBatchId::fromString('   ');
    }

    #[Test]
    public function can_instantiate_row_error(): void
    {
        $error = new RowError(
            rowNumber: 42,
            field: 'sku',
            value: 'UNKNOWN',
            message: 'SKU not found'
        );

        self::assertSame(42, $error->rowNumber);
        self::assertSame('sku', $error->field);
        self::assertSame('UNKNOWN', $error->value);
        self::assertSame('SKU not found', $error->message);
    }

    #[Test]
    public function cannot_mark_terminal_batch_as_failed(): void
    {
        $batch = ImportBatch::create(
            id: ImportBatchId::generate(),
            workspaceId: 'ws-1',
            datasetType: DatasetType::SALES,
            sourceFormat: SourceFormat::CSV,
            originalFilename: 'test.csv',
            storedFilePath: 'test.csv',
        );
        $batch->startValidation();
        $batch->startProcessing(10);
        $batch->markCompleted();

        $this->expectException(\DomainException::class);
        $batch->markFailed('Another failure');
    }

    #[Test]
    public function mark_failed_requires_non_empty_reason(): void
    {
        $batch = ImportBatch::create(
            id: ImportBatchId::generate(),
            workspaceId: 'ws-1',
            datasetType: DatasetType::SALES,
            sourceFormat: SourceFormat::CSV,
            originalFilename: 'test.csv',
            storedFilePath: 'test.csv',
        );

        $this->expectException(\InvalidArgumentException::class);
        $batch->markFailed('   ');
    }
}
