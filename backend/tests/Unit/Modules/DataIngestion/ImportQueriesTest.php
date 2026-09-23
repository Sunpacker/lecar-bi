<?php

namespace Tests\Unit\Modules\DataIngestion;

use App\Modules\DataIngestion\Application\Queries\GetImportBatchByIdHandler;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchByIdQuery;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchesHandler;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchesQuery;
use App\Modules\DataIngestion\Application\Queries\GetImportFailuresHandler;
use App\Modules\DataIngestion\Application\Queries\GetImportFailuresQuery;
use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\Exceptions\ImportBatchNotFoundException;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\RowError;
use App\Modules\DataIngestion\Domain\SourceFormat;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryImportBatchRepository;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryImportFailureRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportQueriesTest extends TestCase
{
    private InMemoryImportBatchRepository $batchRepo;

    private InMemoryImportFailureRepository $failureRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->batchRepo = new InMemoryImportBatchRepository;
        $this->failureRepo = new InMemoryImportFailureRepository;
    }

    #[Test]
    public function lists_batches_with_pagination_and_status_filtering(): void
    {
        $batch1 = ImportBatch::create(ImportBatchId::generate(), 'ws-1', DatasetType::SALES, SourceFormat::CSV, 's1.csv', 'p1');
        $batch2 = ImportBatch::create(ImportBatchId::generate(), 'ws-1', DatasetType::INVENTORY, SourceFormat::JSON, 's2.json', 'p2');
        $batch3 = ImportBatch::create(ImportBatchId::generate(), 'ws-2', DatasetType::SALES, SourceFormat::CSV, 's3.csv', 'p3');

        $this->batchRepo->save($batch1);
        $this->batchRepo->save($batch2);
        $this->batchRepo->save($batch3);

        $handler = new GetImportBatchesHandler($this->batchRepo);
        $result = $handler->handle(new GetImportBatchesQuery('ws-1', null, 1, 10));

        self::assertSame(2, $result->total);
        self::assertCount(2, $result->items);
        self::assertSame(1, $result->page);
        self::assertSame(10, $result->perPage);
        self::assertSame(1, $result->totalPages);
    }

    #[Test]
    public function gets_batch_by_id_or_throws_when_not_found(): void
    {
        $id = ImportBatchId::generate();
        $batch = ImportBatch::create($id, 'ws-1', DatasetType::SALES, SourceFormat::CSV, 's1.csv', 'p1');
        $this->batchRepo->save($batch);

        $handler = new GetImportBatchByIdHandler($this->batchRepo);
        $dto = $handler->handle(new GetImportBatchByIdQuery('ws-1', $id->toString()));

        self::assertSame($id->toString(), $dto->id);

        $this->expectException(ImportBatchNotFoundException::class);
        $handler->handle(new GetImportBatchByIdQuery('ws-other', $id->toString()));
    }

    #[Test]
    public function gets_import_failures_for_batch(): void
    {
        $id = ImportBatchId::generate();
        $batch = ImportBatch::create($id, 'ws-1', DatasetType::SALES, SourceFormat::CSV, 's1.csv', 'p1');
        $this->batchRepo->save($batch);

        $this->failureRepo->recordFailures($id, 'ws-1', [
            new RowError(1, 'sku', '', 'Missing SKU', 'fail-1', new DateTimeImmutable('2026-09-23T10:00:00Z')),
            new RowError(2, 'quantity', '-5', 'Quantity must be > 0', 'fail-2', new DateTimeImmutable('2026-09-23T10:00:01Z')),
        ]);

        $handler = new GetImportFailuresHandler($this->batchRepo, $this->failureRepo);
        $result = $handler->handle(new GetImportFailuresQuery('ws-1', $id->toString(), 1, 10));

        self::assertSame(2, $result->total);
        self::assertCount(2, $result->items);
        self::assertSame(1, $result->items[0]->rowNumber);
        self::assertSame('Missing SKU', $result->items[0]->errorMessage);
    }
}
