<?php

namespace Tests\Unit\Modules\DataIngestion;

use App\Modules\DataIngestion\Application\Commands\ProcessImportBatchCommand;
use App\Modules\DataIngestion\Application\Commands\ProcessImportBatchHandler;
use App\Modules\DataIngestion\Application\Commands\RetryImportBatchCommand;
use App\Modules\DataIngestion\Application\Commands\RetryImportBatchHandler;
use App\Modules\DataIngestion\Application\Commands\UploadImportBatchCommand;
use App\Modules\DataIngestion\Application\Commands\UploadImportBatchHandler;
use App\Modules\DataIngestion\Application\Contracts\ImportJobDispatcherInterface;
use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\Exceptions\CannotRetryImportException;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\ImportStatus;
use App\Modules\DataIngestion\Domain\SourceFormat;
use App\Modules\DataIngestion\Infrastructure\Parsers\CsvImportParser;
use App\Modules\DataIngestion\Infrastructure\Parsers\JsonImportParser;
use App\Modules\DataIngestion\Infrastructure\Projection\InMemoryStarSchemaProjector;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryImportBatchRepository;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryImportFailureRepository;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryStagingRecordRepository;
use App\Modules\DataIngestion\Infrastructure\Validators\InventoryRowValidator;
use App\Modules\DataIngestion\Infrastructure\Validators\SalesRowValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportCommandsTest extends TestCase
{
    private InMemoryImportBatchRepository $batchRepo;

    private InMemoryImportFailureRepository $failureRepo;

    private InMemoryStagingRecordRepository $stagingRepo;

    private InMemoryStarSchemaProjector $projector;

    /** @var list<array{batchId: string, workspaceId: string}> */
    private array $dispatchedJobs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->batchRepo = new InMemoryImportBatchRepository;
        $this->failureRepo = new InMemoryImportFailureRepository;
        $this->stagingRepo = new InMemoryStagingRecordRepository;
        $this->projector = new InMemoryStarSchemaProjector;
        $this->dispatchedJobs = [];
    }

    private function makeDispatcher(): ImportJobDispatcherInterface
    {
        return new class($this->dispatchedJobs) implements ImportJobDispatcherInterface
        {
            /**
             * @param  list<array{batchId: string, workspaceId: string}>  $dispatched
             */
            public function __construct(private array &$dispatched) {}

            public function dispatch(string $batchId, string $workspaceId): void
            {
                $this->dispatched[] = ['batchId' => $batchId, 'workspaceId' => $workspaceId];
            }
        };
    }

    #[Test]
    public function upload_handler_creates_pending_batch_and_dispatches_job(): void
    {
        $dispatcher = $this->makeDispatcher();
        $handler = new UploadImportBatchHandler($this->batchRepo, $dispatcher);

        $dto = $handler->handle(new UploadImportBatchCommand(
            workspaceId: 'ws-1',
            datasetType: 'sales',
            sourceFormat: 'csv',
            originalFilename: 'orders.csv',
            storedFilePath: '/tmp/orders.csv',
        ));

        self::assertSame('pending', $dto->status);
        self::assertSame('orders.csv', $dto->originalFilename);
        self::assertCount(1, $this->dispatchedJobs);
        self::assertSame($dto->id, $this->dispatchedJobs[0]['batchId']);
    }

    #[Test]
    public function process_handler_processes_valid_csv_and_marks_completed(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, "order_number,order_date,channel_code,region_code,warehouse_code,sku,quantity,unit_price,unit_cost,order_status\nORD-1,2026-03-01,online,RU-MSK,WH-01,SKU-A,2,100.00,50.00,completed\nORD-2,2026-03-02,retail,RU-SPB,WH-02,SKU-B,1,200.00,120.00,completed\n");

        $batchId = ImportBatchId::generate();
        $batch = ImportBatch::create($batchId, 'ws-1', DatasetType::SALES, SourceFormat::CSV, 'test.csv', $tempFile);
        $this->batchRepo->save($batch);

        $handler = new ProcessImportBatchHandler(
            $this->batchRepo,
            $this->failureRepo,
            $this->stagingRepo,
            $this->projector,
            new CsvImportParser,
            new JsonImportParser,
            new SalesRowValidator,
            new InventoryRowValidator,
        );

        $handler->handle(new ProcessImportBatchCommand($batchId->toString(), 'ws-1'));

        $updated = $this->batchRepo->findById($batchId, 'ws-1');
        self::assertNotNull($updated);
        self::assertSame(ImportStatus::COMPLETED, $updated->status());
        self::assertSame(2, $updated->totalRows());
        self::assertSame(2, $updated->processedRows());
        self::assertSame(2, $updated->successfulRows());
        self::assertSame(0, $updated->failedRows());
        self::assertSame(2, $this->stagingRepo->countSalesRows($batchId->toString()));
        self::assertCount(2, $this->projector->projectedSalesRows);

        unlink($tempFile);
    }

    #[Test]
    public function process_handler_handles_invalid_rows_and_marks_completed_with_errors(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
        self::assertNotFalse($tempFile);
        // Row 1 valid, Row 2 invalid (empty sku, negative quantity)
        file_put_contents($tempFile, "order_number,order_date,channel_code,region_code,warehouse_code,sku,quantity,unit_price,unit_cost,order_status\nORD-1,2026-03-01,online,RU-MSK,WH-01,SKU-A,2,100.00,50.00,completed\nORD-2,2026-03-02,retail,RU-SPB,WH-02,,-5,200.00,120.00,completed\n");

        $batchId = ImportBatchId::generate();
        $batch = ImportBatch::create($batchId, 'ws-1', DatasetType::SALES, SourceFormat::CSV, 'test.csv', $tempFile);
        $this->batchRepo->save($batch);

        $handler = new ProcessImportBatchHandler(
            $this->batchRepo,
            $this->failureRepo,
            $this->stagingRepo,
            $this->projector,
            new CsvImportParser,
            new JsonImportParser,
            new SalesRowValidator,
            new InventoryRowValidator,
        );

        $handler->handle(new ProcessImportBatchCommand($batchId->toString(), 'ws-1'));

        $updated = $this->batchRepo->findById($batchId, 'ws-1');
        self::assertNotNull($updated);
        self::assertSame(ImportStatus::COMPLETED_WITH_ERRORS, $updated->status());
        self::assertSame(2, $updated->totalRows());
        self::assertSame(1, $updated->successfulRows());
        self::assertSame(1, $updated->failedRows());
        self::assertSame(1, $this->stagingRepo->countSalesRows($batchId->toString()));
        self::assertGreaterThanOrEqual(1, $this->failureRepo->countByBatchId($batchId, 'ws-1'));

        unlink($tempFile);
    }

    #[Test]
    public function retry_handler_resets_batch_clears_failures_and_redispatches(): void
    {
        $batchId = ImportBatchId::generate();
        $batch = ImportBatch::create($batchId, 'ws-1', DatasetType::SALES, SourceFormat::CSV, 'test.csv', '/tmp/dummy.csv');
        $batch->startValidation();
        $batch->startProcessing(10);
        $batch->recordProgress(10, 8, 2);
        $batch->markCompletedWithErrors();
        $this->batchRepo->save($batch);

        $dispatcher = $this->makeDispatcher();
        $handler = new RetryImportBatchHandler($this->batchRepo, $this->failureRepo, $this->stagingRepo, $dispatcher);

        $dto = $handler->handle(new RetryImportBatchCommand('ws-1', $batchId->toString()));

        self::assertSame('pending', $dto->status);
        self::assertSame(0, $dto->totalRows);
        self::assertCount(1, $this->dispatchedJobs);
    }

    #[Test]
    public function retry_handler_throws_when_batch_in_progress(): void
    {
        $batchId = ImportBatchId::generate();
        $batch = ImportBatch::create($batchId, 'ws-1', DatasetType::SALES, SourceFormat::CSV, 'test.csv', '/tmp/dummy.csv');
        $batch->startValidation();
        $batch->startProcessing(10);
        $this->batchRepo->save($batch);

        $dispatcher = $this->makeDispatcher();
        $handler = new RetryImportBatchHandler($this->batchRepo, $this->failureRepo, $this->stagingRepo, $dispatcher);

        $this->expectException(CannotRetryImportException::class);
        $handler->handle(new RetryImportBatchCommand('ws-1', $batchId->toString()));
    }
}
