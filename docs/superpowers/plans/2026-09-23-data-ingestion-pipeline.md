# Data Ingestion Application & API Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the asynchronous data ingestion pipeline, CQRS application commands & queries, staging persistence, queue job execution, REST API presentation endpoints, and end-to-end verification for Phase 10 (Data Ingestion), enabling secure, workspace-isolated upload, validation, failure tracking, safe retry, and idempotent projection into the Star Schema.

**Architecture:** Domain-Driven Design (DDD) in Laravel 11 under bounded context `DataIngestion`. Application layer provides CQRS commands/queries and orchestrates ingestion. Asynchronous queue processing via `ProcessImportJob` executes streaming validation, saves rows to staging tables, isolates invalid rows into `import_failures`, and idempotently projects valid records into Star Schema fact and dimension tables. Presentation layer exposes REST endpoints adhering strictly to OpenAPI 3.0.3 contract.

**Tech Stack:** Laravel 11 (PHP 8.3), PostgreSQL 16, Redis (queues), OpenAPI 3.0.3, PHPUnit 11, Larastan (Level Max).

**Spec:** `docs/roadmap/10-data-ingestion.md`, `contracts/openapi/analytics-v1.yaml`, `docs/architecture/04-backend-laravel-ddd.md`, `docs/architecture/05-bounded-contexts.md`, `docs/architecture/06-data-and-analytics.md`, `docs/architecture/08-events-outbox-async.md`.

## Global Constraints

- Domain Layer in `App\Modules\DataIngestion\Domain` MUST NOT depend on Laravel/Illuminate, framework helpers, or Infrastructure layers (`ArchitectureTest`).
- Multi-tenancy isolation MUST be strictly enforced via `workspace_id` through `GetCurrentWorkspaceHandler` / `WorkspaceAccessGuard` (cross-workspace requests return `403 Forbidden`).
- Invalid imports MUST NOT corrupt valid data: invalid rows are recorded in `import_failures` with row number and error description, while valid rows are staged and projected.
- Star Schema projection MUST be idempotent: re-processing an import batch MUST NOT duplicate records in `fact_orders`, `fact_order_items`, or `fact_inventory_daily`.
- Retry is permitted ONLY for batches in `failed` or `completed_with_errors` state; attempting to retry a `processing` or `completed` batch returns HTTP 409 Conflict.
- REST endpoints and responses MUST strictly match `contracts/openapi/analytics-v1.yaml`.
- All tests MUST pass with 0 warnings, PHPStan at level max, and Pint formatting clean.

---

### Task 1: Application DTOs & Staging Record Repository

**Files:**
- Create: `backend/app/Modules/DataIngestion/Application/Dtos/ImportBatchDto.php`
- Create: `backend/app/Modules/DataIngestion/Application/Dtos/ImportFailureDto.php`
- Create: `backend/app/Modules/DataIngestion/Domain/Repositories/StagingRecordRepositoryInterface.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Repositories/EloquentStagingRecordRepository.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Repositories/InMemoryStagingRecordRepository.php`
- Modify: `backend/app/Modules/DataIngestion/Domain/RowError.php:1-14`
- Modify: `backend/app/Modules/DataIngestion/Infrastructure/Repositories/EloquentImportFailureRepository.php:40-62`
- Modify: `backend/app/Providers/AppServiceProvider.php:80-100`
- Test: `backend/tests/Unit/Modules/DataIngestion/StagingRepositoryTest.php`

**Interfaces:**
- Consumes: `ImportBatch`, `RowError`, `StagingSalesRecordModel`, `StagingInventoryRecordModel`
- Produces:
  - `ImportBatchDto::fromDomain(ImportBatch $batch): self`
  - `ImportFailureDto::fromRowError(RowError $error, string $id, string $createdAt): self`
  - `StagingRecordRepositoryInterface`:
    - `recordSalesRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void`
    - `recordInventoryRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void`
    - `markSalesRowStatus(string $batchId, int $rowNumber, string $status): void`
    - `markInventoryRowStatus(string $batchId, int $rowNumber, string $status): void`
    - `deleteByBatchId(string $batchId, string $workspaceId): void`

- [ ] **Step 1: Write failing test in `backend/tests/Unit/Modules/DataIngestion/StagingRepositoryTest.php`**

```php
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
        $error = new RowError(12, 'sku', '', 'SKU is required', 'err-1', new \DateTimeImmutable('2026-09-23T10:00:00Z'));
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
        $repo = new InMemoryStagingRecordRepository();
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=StagingRepositoryTest`
Expected: FAIL with "Class App\Modules\DataIngestion\Application\Dtos\ImportBatchDto not found".

- [ ] **Step 3: Implement DTOs, update RowError, and implement StagingRecordRepository**

1. Update `backend/app/Modules/DataIngestion/Domain/RowError.php`:
```php
<?php

namespace App\Modules\DataIngestion\Domain;

use DateTimeImmutable;

final readonly class RowError
{
    public function __construct(
        public int $rowNumber,
        public ?string $field,
        public ?string $value,
        public string $message,
        public ?string $id = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
```

2. Create `backend/app/Modules/DataIngestion/Application/Dtos/ImportBatchDto.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Dtos;

use App\Modules\DataIngestion\Domain\ImportBatch;
use DateTimeInterface;

final readonly class ImportBatchDto
{
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $datasetType,
        public string $sourceFormat,
        public string $originalFilename,
        public string $storedFilePath,
        public string $status,
        public int $totalRows,
        public int $processedRows,
        public int $successfulRows,
        public int $failedRows,
        public float $progressPercentage,
        public ?string $errorMessage,
        public string $createdAt,
        public ?string $completedAt,
    ) {}

    public static function fromDomain(ImportBatch $batch): self
    {
        $total = $batch->totalRows();
        $processed = $batch->processedRows();
        $progress = $total > 0 ? round(($processed / $total) * 100, 2) : 0.0;
        if ($batch->isCompleted() && in_array($batch->status()->value, ['completed', 'completed_with_errors'], true)) {
            $progress = 100.0;
        }

        return new self(
            id: $batch->id()->toString(),
            workspaceId: $batch->workspaceId(),
            datasetType: $batch->datasetType()->value,
            sourceFormat: $batch->sourceFormat()->value,
            originalFilename: $batch->originalFilename(),
            storedFilePath: $batch->storedFilePath(),
            status: $batch->status()->value,
            totalRows: $batch->totalRows(),
            processedRows: $batch->processedRows(),
            successfulRows: $batch->successfulRows(),
            failedRows: $batch->failedRows(),
            progressPercentage: $progress,
            errorMessage: $batch->errorMessage(),
            createdAt: $batch->createdAt()->format(DateTimeInterface::ATOM),
            completedAt: $batch->completedAt()?->format(DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'dataset_type' => $this->datasetType,
            'source_format' => $this->sourceFormat,
            'original_filename' => $this->originalFilename,
            'stored_file_path' => $this->storedFilePath,
            'status' => $this->status,
            'total_rows' => $this->totalRows,
            'processed_rows' => $this->processedRows,
            'successful_rows' => $this->successfulRows,
            'failed_rows' => $this->failedRows,
            'progress_percentage' => $this->progressPercentage,
            'error_message' => $this->errorMessage,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completedAt,
        ];
    }
}
```

3. Create `backend/app/Modules/DataIngestion/Application/Dtos/ImportFailureDto.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Dtos;

use App\Modules\DataIngestion\Domain\RowError;
use DateTimeInterface;

final readonly class ImportFailureDto
{
    public function __construct(
        public string $id,
        public int $rowNumber,
        public ?string $field,
        public ?string $value,
        public string $errorMessage,
        public string $createdAt,
    ) {}

    public static function fromRowError(RowError $error, string $id, string $createdAt): self
    {
        return new self(
            id: $id,
            rowNumber: $error->rowNumber,
            field: $error->field,
            value: $error->value,
            errorMessage: $error->message,
            createdAt: $createdAt,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->rowNumber,
            'field' => $this->field,
            'value' => $this->value,
            'error_message' => $this->errorMessage,
            'created_at' => $this->createdAt,
        ];
    }
}
```

4. Create `backend/app/Modules/DataIngestion/Domain/Repositories/StagingRecordRepositoryInterface.php`:
```php
<?php

namespace App\Modules\DataIngestion\Domain\Repositories;

interface StagingRecordRepositoryInterface
{
    /** @param array<string, string> $row */
    public function recordSalesRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void;

    /** @param array<string, string> $row */
    public function recordInventoryRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void;

    public function markSalesRowStatus(string $batchId, int $rowNumber, string $status): void;

    public function markInventoryRowStatus(string $batchId, int $rowNumber, string $status): void;

    public function deleteByBatchId(string $batchId, string $workspaceId): void;
}
```

5. Create `backend/app/Modules/DataIngestion/Infrastructure/Repositories/EloquentStagingRecordRepository.php`:
```php
<?php

namespace App\Modules\DataIngestion\Infrastructure\Repositories;

use App\Modules\DataIngestion\Domain\Repositories\StagingRecordRepositoryInterface;
use App\Modules\DataIngestion\Infrastructure\Models\StagingInventoryRecordModel;
use App\Modules\DataIngestion\Infrastructure\Models\StagingSalesRecordModel;

final class EloquentStagingRecordRepository implements StagingRecordRepositoryInterface
{
    public function recordSalesRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void
    {
        StagingSalesRecordModel::create([
            'batch_id' => $batchId,
            'workspace_id' => $workspaceId,
            'row_number' => $rowNumber,
            'order_number' => $row['order_number'],
            'order_date' => $row['order_date'],
            'channel_code' => $row['channel_code'],
            'region_code' => $row['region_code'],
            'warehouse_code' => $row['warehouse_code'],
            'sku' => $row['sku'],
            'quantity' => (int) $row['quantity'],
            'unit_price' => (float) $row['unit_price'],
            'unit_cost' => (float) $row['unit_cost'],
            'order_status' => $row['order_status'],
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    public function recordInventoryRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void
    {
        StagingInventoryRecordModel::create([
            'batch_id' => $batchId,
            'workspace_id' => $workspaceId,
            'row_number' => $rowNumber,
            'snapshot_date' => $row['snapshot_date'],
            'warehouse_code' => $row['warehouse_code'],
            'sku' => $row['sku'],
            'quantity_on_hand' => (int) $row['quantity_on_hand'],
            'quantity_reserved' => (int) $row['quantity_reserved'],
            'safety_stock' => (int) ($row['safety_stock'] ?? 0),
            'reorder_point' => (int) ($row['reorder_point'] ?? 0),
            'unit_cost' => isset($row['unit_cost']) && $row['unit_cost'] !== '' ? (float) $row['unit_cost'] : null,
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    public function markSalesRowStatus(string $batchId, int $rowNumber, string $status): void
    {
        StagingSalesRecordModel::where('batch_id', $batchId)
            ->where('row_number', $rowNumber)
            ->update(['status' => $status]);
    }

    public function markInventoryRowStatus(string $batchId, int $rowNumber, string $status): void
    {
        StagingInventoryRecordModel::where('batch_id', $batchId)
            ->where('row_number', $rowNumber)
            ->update(['status' => $status]);
    }

    public function deleteByBatchId(string $batchId, string $workspaceId): void
    {
        StagingSalesRecordModel::where('batch_id', $batchId)
            ->where('workspace_id', $workspaceId)
            ->delete();

        StagingInventoryRecordModel::where('batch_id', $batchId)
            ->where('workspace_id', $workspaceId)
            ->delete();
    }
}
```

6. Create `backend/app/Modules/DataIngestion/Infrastructure/Repositories/InMemoryStagingRecordRepository.php`:
```php
<?php

namespace App\Modules\DataIngestion\Infrastructure\Repositories;

use App\Modules\DataIngestion\Domain\Repositories\StagingRecordRepositoryInterface;

final class InMemoryStagingRecordRepository implements StagingRecordRepositoryInterface
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $salesRows = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $inventoryRows = [];

    public function recordSalesRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void
    {
        $this->salesRows[$batchId][$rowNumber] = array_merge($row, [
            'batch_id' => $batchId,
            'workspace_id' => $workspaceId,
            'row_number' => $rowNumber,
            'status' => $status,
        ]);
    }

    public function recordInventoryRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void
    {
        $this->inventoryRows[$batchId][$rowNumber] = array_merge($row, [
            'batch_id' => $batchId,
            'workspace_id' => $workspaceId,
            'row_number' => $rowNumber,
            'status' => $status,
        ]);
    }

    public function markSalesRowStatus(string $batchId, int $rowNumber, string $status): void
    {
        if (isset($this->salesRows[$batchId][$rowNumber])) {
            $this->salesRows[$batchId][$rowNumber]['status'] = $status;
        }
    }

    public function markInventoryRowStatus(string $batchId, int $rowNumber, string $status): void
    {
        if (isset($this->inventoryRows[$batchId][$rowNumber])) {
            $this->inventoryRows[$batchId][$rowNumber]['status'] = $status;
        }
    }

    public function countSalesRows(string $batchId): int
    {
        return count($this->salesRows[$batchId] ?? []);
    }

    public function countInventoryRows(string $batchId): int
    {
        return count($this->inventoryRows[$batchId] ?? []);
    }

    public function getSalesRowStatus(string $batchId, int $rowNumber): ?string
    {
        return $this->salesRows[$batchId][$rowNumber]['status'] ?? null;
    }

    public function deleteByBatchId(string $batchId, string $workspaceId): void
    {
        unset($this->salesRows[$batchId], $this->inventoryRows[$batchId]);
    }
}
```

7. Update `backend/app/Modules/DataIngestion/Infrastructure/Repositories/EloquentImportFailureRepository.php` to populate id and created_at on `RowError`.
8. Register `StagingRecordRepositoryInterface` binding in `backend/app/Providers/AppServiceProvider.php`.

- [ ] **Step 4: Run tests and verify passing**

Run: `composer --working-dir=backend test -- --filter=StagingRepositoryTest`
Expected: PASS (3 tests, assertions pass)

- [ ] **Step 5: Commit changes**

```bash
git add backend/app/Modules/DataIngestion/Application/Dtos backend/app/Modules/DataIngestion/Domain backend/app/Modules/DataIngestion/Infrastructure backend/app/Providers/AppServiceProvider.php backend/tests/Unit/Modules/DataIngestion/StagingRepositoryTest.php
git commit -m "feat(ingestion): add Application DTOs and StagingRecordRepository"
```

---

### Task 2: Ingestion Application Queries & Handlers

**Files:**
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchesQuery.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchesHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchByIdQuery.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchByIdHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportFailuresQuery.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportFailuresHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Dtos/PaginatedListDto.php`
- Test: `backend/tests/Unit/Modules/DataIngestion/ImportQueriesTest.php`

**Interfaces:**
- Consumes: `ImportBatchRepositoryInterface`, `ImportFailureRepositoryInterface`, `ImportBatchDto`, `ImportFailureDto`
- Produces:
  - `GetImportBatchesHandler::handle(GetImportBatchesQuery $query): PaginatedListDto<ImportBatchDto>`
  - `GetImportBatchByIdHandler::handle(GetImportBatchByIdQuery $query): ImportBatchDto`
  - `GetImportFailuresHandler::handle(GetImportFailuresQuery $query): PaginatedListDto<ImportFailureDto>`

- [ ] **Step 1: Write failing test in `backend/tests/Unit/Modules/DataIngestion/ImportQueriesTest.php`**

```php
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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportQueriesTest extends TestCase
{
    private InMemoryImportBatchRepository $batchRepo;
    private InMemoryImportFailureRepository $failureRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->batchRepo = new InMemoryImportBatchRepository();
        $this->failureRepo = new InMemoryImportFailureRepository();
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
            new RowError(1, 'sku', '', 'Missing SKU', 'fail-1', new \DateTimeImmutable('2026-09-23T10:00:00Z')),
            new RowError(2, 'quantity', '-5', 'Quantity must be > 0', 'fail-2', new \DateTimeImmutable('2026-09-23T10:00:01Z')),
        ]);

        $handler = new GetImportFailuresHandler($this->batchRepo, $this->failureRepo);
        $result = $handler->handle(new GetImportFailuresQuery('ws-1', $id->toString(), 1, 10));

        self::assertSame(2, $result->total);
        self::assertCount(2, $result->items);
        self::assertSame(1, $result->items[0]->rowNumber);
        self::assertSame('Missing SKU', $result->items[0]->errorMessage);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=ImportQueriesTest`
Expected: FAIL with "Class App\Modules\DataIngestion\Application\Queries\GetImportBatchesHandler not found".

- [ ] **Step 3: Implement Query DTOs and Handlers**

1. Create `backend/app/Modules/DataIngestion/Application/Dtos/PaginatedListDto.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Dtos;

/**
 * @template T
 */
final readonly class PaginatedListDto
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {}
}
```

2. Create `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchesQuery.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Queries;

final readonly class GetImportBatchesQuery
{
    public function __construct(
        public string $workspaceId,
        public ?string $status = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
```

3. Create `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchesHandler.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Queries;

use App\Modules\DataIngestion\Application\Dtos\ImportBatchDto;
use App\Modules\DataIngestion\Application\Dtos\PaginatedListDto;
use App\Modules\DataIngestion\Domain\ImportStatus;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;

final class GetImportBatchesHandler
{
    public function __construct(
        private readonly ImportBatchRepositoryInterface $batchRepository,
    ) {}

    /**
     * @return PaginatedListDto<ImportBatchDto>
     */
    public function handle(GetImportBatchesQuery $query): PaginatedListDto
    {
        $status = $query->status !== null ? ImportStatus::tryFrom($query->status) : null;
        $total = $this->batchRepository->countByWorkspace($query->workspaceId, $status);
        $batches = $this->batchRepository->listByWorkspace($query->workspaceId, $status, $query->page, $query->perPage);

        $items = array_map(static fn ($b) => ImportBatchDto::fromDomain($b), $batches);
        $totalPages = $query->perPage > 0 ? (int) ceil($total / $query->perPage) : 1;

        return new PaginatedListDto(
            items: array_values($items),
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            totalPages: max(1, $totalPages),
        );
    }
}
```

4. Create `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchByIdQuery.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Queries;

final readonly class GetImportBatchByIdQuery
{
    public function __construct(
        public string $workspaceId,
        public string $batchId,
    ) {}
}
```

5. Create `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchByIdHandler.php`:
```php
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
```

6. Create `backend/app/Modules/DataIngestion/Application/Queries/GetImportFailuresQuery.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Queries;

final readonly class GetImportFailuresQuery
{
    public function __construct(
        public string $workspaceId,
        public string $batchId,
        public int $page = 1,
        public int $perPage = 50,
    ) {}
}
```

7. Create `backend/app/Modules/DataIngestion/Application/Queries/GetImportFailuresHandler.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Queries;

use App\Modules\DataIngestion\Application\Dtos\ImportFailureDto;
use App\Modules\DataIngestion\Application\Dtos\PaginatedListDto;
use App\Modules\DataIngestion\Domain\Exceptions\ImportBatchNotFoundException;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\Repositories\ImportFailureRepositoryInterface;
use DateTimeInterface;

final class GetImportFailuresHandler
{
    public function __construct(
        private readonly ImportBatchRepositoryInterface $batchRepository,
        private readonly ImportFailureRepositoryInterface $failureRepository,
    ) {}

    /**
     * @return PaginatedListDto<ImportFailureDto>
     */
    public function handle(GetImportFailuresQuery $query): PaginatedListDto
    {
        $batchId = ImportBatchId::fromString($query->batchId);
        $batch = $this->batchRepository->findById($batchId, $query->workspaceId);

        if ($batch === null) {
            throw new ImportBatchNotFoundException("Import batch '{$query->batchId}' not found in current workspace.");
        }

        $total = $this->failureRepository->countByBatchId($batchId, $query->workspaceId);
        $errors = $this->failureRepository->listByBatchId($batchId, $query->workspaceId, $query->page, $query->perPage);

        $items = array_map(static function ($err) use ($batchId) {
            $id = $err->id ?? ($batchId->toString().'-'.$err->rowNumber);
            $createdAt = $err->createdAt !== null
                ? $err->createdAt->format(DateTimeInterface::ATOM)
                : (new \DateTimeImmutable())->format(DateTimeInterface::ATOM);

            return ImportFailureDto::fromRowError($err, $id, $createdAt);
        }, $errors);

        $totalPages = $query->perPage > 0 ? (int) ceil($total / $query->perPage) : 1;

        return new PaginatedListDto(
            items: array_values($items),
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            totalPages: max(1, $totalPages),
        );
    }
}
```

- [ ] **Step 4: Run tests and verify passing**

Run: `composer --working-dir=backend test -- --filter=ImportQueriesTest`
Expected: PASS (3 tests, assertions pass)

- [ ] **Step 5: Commit queries**

```bash
git add backend/app/Modules/DataIngestion/Application/Dtos/PaginatedListDto.php backend/app/Modules/DataIngestion/Application/Queries backend/tests/Unit/Modules/DataIngestion/ImportQueriesTest.php
git commit -m "feat(ingestion): implement Application CQRS queries and handlers"
```

---

### Task 3: Ingestion Application Commands & Queue Job Processing Pipeline

**Files:**
- Create: `backend/app/Modules/DataIngestion/Application/Contracts/ImportJobDispatcherInterface.php`
- Create: `backend/app/Modules/DataIngestion/Application/Contracts/StarSchemaProjectorInterface.php`
- Modify: `backend/app/Modules/DataIngestion/Infrastructure/Projection/StarSchemaProjector.php:23-28`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Projection/InMemoryStarSchemaProjector.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/UploadImportBatchCommand.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/UploadImportBatchHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/ProcessImportBatchCommand.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/ProcessImportBatchHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/RetryImportBatchCommand.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/RetryImportBatchHandler.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Jobs/ProcessImportJob.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Jobs/QueueImportJobDispatcher.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Unit/Modules/DataIngestion/ImportCommandsTest.php`

**Interfaces:**
- Consumes: `ImportBatchRepositoryInterface`, `ImportFailureRepositoryInterface`, `StagingRecordRepositoryInterface`, `StarSchemaProjectorInterface`, `ImportJobDispatcherInterface`
- Produces:
  - `UploadImportBatchHandler::handle(UploadImportBatchCommand $command): ImportBatchDto`
  - `ProcessImportBatchHandler::handle(ProcessImportBatchCommand $command): void`
  - `RetryImportBatchHandler::handle(RetryImportBatchCommand $command): ImportBatchDto`
  - `ProcessImportJob`: queue job implementing `ShouldQueue` calling `ProcessImportBatchHandler`

- [ ] **Step 1: Write failing application command & pipeline test in `backend/tests/Unit/Modules/DataIngestion/ImportCommandsTest.php`**

```php
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
        $this->batchRepo = new InMemoryImportBatchRepository();
        $this->failureRepo = new InMemoryImportFailureRepository();
        $this->stagingRepo = new InMemoryStagingRecordRepository();
        $this->projector = new InMemoryStarSchemaProjector();
        $this->dispatchedJobs = [];
    }

    private function makeDispatcher(): ImportJobDispatcherInterface
    {
        return new class($this->dispatchedJobs) implements ImportJobDispatcherInterface {
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
        file_put_contents($tempFile, "order_number,order_date,channel_code,region_code,warehouse_code,sku,quantity,unit_price,unit_cost,order_status\nORD-1,2026-03-01,online,RU-MSK,WH-01,SKU-A,2,100.00,50.00,completed\nORD-2,2026-03-02,retail,RU-SPB,WH-02,SKU-B,1,200.00,120.00,completed\n");

        $batchId = ImportBatchId::generate();
        $batch = ImportBatch::create($batchId, 'ws-1', DatasetType::SALES, SourceFormat::CSV, 'test.csv', $tempFile);
        $this->batchRepo->save($batch);

        $handler = new ProcessImportBatchHandler(
            $this->batchRepo,
            $this->failureRepo,
            $this->stagingRepo,
            $this->projector,
            new CsvImportParser(),
            new JsonImportParser(),
            new SalesRowValidator(),
            new InventoryRowValidator(),
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
            new CsvImportParser(),
            new JsonImportParser(),
            new SalesRowValidator(),
            new InventoryRowValidator(),
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=ImportCommandsTest`
Expected: FAIL with missing classes.

- [ ] **Step 3: Implement Projector Interface, Application Commands, Handlers, and Queue Job**

1. Create `backend/app/Modules/DataIngestion/Application/Contracts/StarSchemaProjectorInterface.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Contracts;

interface StarSchemaProjectorInterface
{
    /** @param array<string, string> $row */
    public function projectSalesRow(string $workspaceId, array $row): void;

    /** @param array<string, string> $row */
    public function projectInventoryRow(string $workspaceId, array $row): void;
}
```

2. Make `backend/app/Modules/DataIngestion/Infrastructure/Projection/StarSchemaProjector.php` implement `StarSchemaProjectorInterface`:
Add `implements StarSchemaProjectorInterface` to class definition.

3. Create `backend/app/Modules/DataIngestion/Infrastructure/Projection/InMemoryStarSchemaProjector.php`:
```php
<?php

namespace App\Modules\DataIngestion\Infrastructure\Projection;

use App\Modules\DataIngestion\Application\Contracts\StarSchemaProjectorInterface;

final class InMemoryStarSchemaProjector implements StarSchemaProjectorInterface
{
    /** @var list<array{workspaceId: string, row: array<string, string>}> */
    public array $projectedSalesRows = [];

    /** @var list<array{workspaceId: string, row: array<string, string>}> */
    public array $projectedInventoryRows = [];

    public function projectSalesRow(string $workspaceId, array $row): void
    {
        $this->projectedSalesRows[] = ['workspaceId' => $workspaceId, 'row' => $row];
    }

    public function projectInventoryRow(string $workspaceId, array $row): void
    {
        $this->projectedInventoryRows[] = ['workspaceId' => $workspaceId, 'row' => $row];
    }
}
```

4. Create `backend/app/Modules/DataIngestion/Application/Contracts/ImportJobDispatcherInterface.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Contracts;

interface ImportJobDispatcherInterface
{
    public function dispatch(string $batchId, string $workspaceId): void;
}
```

5. Create `backend/app/Modules/DataIngestion/Infrastructure/Jobs/ProcessImportJob.php`:
```php
<?php

namespace App\Modules\DataIngestion\Infrastructure\Jobs;

use App\Modules\DataIngestion\Application\Commands\ProcessImportBatchCommand;
use App\Modules\DataIngestion\Application\Commands\ProcessImportBatchHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $batchId,
        public readonly string $workspaceId,
    ) {}

    public function handle(ProcessImportBatchHandler $handler): void
    {
        $handler->handle(new ProcessImportBatchCommand($this->batchId, $this->workspaceId));
    }
}
```

6. Create `backend/app/Modules/DataIngestion/Infrastructure/Jobs/QueueImportJobDispatcher.php`:
```php
<?php

namespace App\Modules\DataIngestion\Infrastructure\Jobs;

use App\Modules\DataIngestion\Application\Contracts\ImportJobDispatcherInterface;

final class QueueImportJobDispatcher implements ImportJobDispatcherInterface
{
    public function dispatch(string $batchId, string $workspaceId): void
    {
        ProcessImportJob::dispatch($batchId, $workspaceId);
    }
}
```

7. Create `backend/app/Modules/DataIngestion/Application/Commands/UploadImportBatchCommand.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Commands;

final readonly class UploadImportBatchCommand
{
    public function __construct(
        public string $workspaceId,
        public string $datasetType,
        public string $sourceFormat,
        public string $originalFilename,
        public string $storedFilePath,
    ) {}
}
```

8. Create `backend/app/Modules/DataIngestion/Application/Commands/UploadImportBatchHandler.php`:
```php
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
```

9. Create `backend/app/Modules/DataIngestion/Application/Commands/ProcessImportBatchCommand.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Commands;

final readonly class ProcessImportBatchCommand
{
    public function __construct(
        public string $batchId,
        public string $workspaceId,
    ) {}
}
```

10. Create `backend/app/Modules/DataIngestion/Application/Commands/ProcessImportBatchHandler.php`:
```php
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
            } elseif ($failed > 0 && $successful === 0) {
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
```

11. Create `backend/app/Modules/DataIngestion/Application/Commands/RetryImportBatchCommand.php`:
```php
<?php

namespace App\Modules\DataIngestion\Application\Commands;

final readonly class RetryImportBatchCommand
{
    public function __construct(
        public string $workspaceId,
        public string $batchId,
    ) {}
}
```

12. Create `backend/app/Modules/DataIngestion/Application/Commands/RetryImportBatchHandler.php`:
```php
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
```

13. Register bindings in `backend/app/Providers/AppServiceProvider.php`:
- `StarSchemaProjectorInterface` -> `InMemoryStarSchemaProjector` (testing) / `StarSchemaProjector` (production)
- `ImportJobDispatcherInterface` -> `QueueImportJobDispatcher` (both or testing fake)

- [ ] **Step 4: Run tests and verify passing**

Run: `composer --working-dir=backend test -- --filter=ImportCommandsTest`
Expected: PASS (5 tests, assertions pass)

- [ ] **Step 5: Commit application commands**

```bash
git add backend/app/Modules/DataIngestion/Application backend/app/Modules/DataIngestion/Infrastructure backend/app/Providers/AppServiceProvider.php backend/tests/Unit/Modules/DataIngestion/ImportCommandsTest.php
git commit -m "feat(ingestion): implement Application CQRS commands, handlers, and ProcessImportJob"
```

---

### Task 4: Presentation Layer: Form Request, REST Controller & Routes

**Files:**
- Create: `backend/app/Modules/DataIngestion/Presentation/Requests/UploadImportRequest.php`
- Create: `backend/app/Modules/DataIngestion/Presentation/Controllers/ImportBatchController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Modules/DataIngestion/ImportBatchApiTest.php`

**Interfaces:**
- Consumes: `UploadImportBatchHandler`, `GetImportBatchesHandler`, `GetImportBatchByIdHandler`, `GetImportFailuresHandler`, `RetryImportBatchHandler`, `GetCurrentWorkspaceHandler`
- Produces:
  - `GET /api/v1/imports` -> 200 `ImportBatchListResponse`
  - `POST /api/v1/imports` -> 202 `ImportBatchDetailResponse`
  - `GET /api/v1/imports/{id}` -> 200 `ImportBatchDetailResponse`
  - `GET /api/v1/imports/{id}/failures` -> 200 `ImportFailureListResponse`
  - `POST /api/v1/imports/{id}/retry` -> 202 `ImportBatchDetailResponse` (or 409 Conflict)

- [ ] **Step 1: Write failing feature test in `backend/tests/Feature/Modules/DataIngestion/ImportBatchApiTest.php`**

```php
<?php

namespace Tests\Feature\Modules\DataIngestion;

use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\SourceFormat;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class ImportBatchApiTest extends TestCase
{
    private ImportBatchRepositoryInterface $batchRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);
        $this->batchRepo = $this->app->make(ImportBatchRepositoryInterface::class);

        $user1 = new User(new UserId('user-1'), 'user1@example.com', 'User One');
        $user2 = new User(new UserId('user-2'), 'user2@example.com', 'User Two');
        $userRepo->save($user1);
        $userRepo->save($user2);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'Workspace 1', 'workspace-1');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws1);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Workspace 2', 'workspace-2');
        $ws2->addMember(new UserId('user-2'), MembershipRole::OWNER);
        $wsRepo->save($ws2);
    }

    #[Test]
    public function unauthenticated_requests_return_401(): void
    {
        $this->getJson('/api/v1/imports')->assertStatus(401);
        $this->postJson('/api/v1/imports')->assertStatus(401);
    }

    #[Test]
    public function uploads_valid_csv_file_returns_202(): void
    {
        $file = UploadedFile::fake()->createWithContent('sales.csv', "order_number,order_date,channel_code,region_code,warehouse_code,sku,quantity,unit_price,unit_cost,order_status\nORD-1,2026-03-01,online,RU-MSK,WH-01,SKU-A,1,10.0,5.0,completed\n");

        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->post('/api/v1/imports', [
            'file' => $file,
            'dataset_type' => 'sales',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'batch' => [
                'id',
                'workspace_id',
                'dataset_type',
                'source_format',
                'original_filename',
                'status',
                'total_rows',
                'processed_rows',
                'successful_rows',
                'failed_rows',
                'progress_percentage',
                'created_at',
            ],
        ]);
        self::assertSame('pending', $response->json('batch.status'));
    }

    #[Test]
    public function lists_workspace_batches(): void
    {
        $batch = ImportBatch::create(ImportBatchId::generate(), 'ws-1', DatasetType::SALES, SourceFormat::CSV, 's.csv', 'p');
        $this->batchRepo->save($batch);

        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/imports');

        $response->assertOk();
        $response->assertJsonStructure([
            'items',
            'total',
            'page',
            'per_page',
            'total_pages',
        ]);
        self::assertSame(1, $response->json('total'));
    }

    #[Test]
    public function prevents_cross_workspace_access_with_403_or_404(): void
    {
        $batchWs2 = ImportBatch::create(ImportBatchId::generate(), 'ws-2', DatasetType::SALES, SourceFormat::CSV, 's.csv', 'p');
        $this->batchRepo->save($batchWs2);

        // User 1 requests ws-2 -> 403 Forbidden
        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson("/api/v1/imports/{$batchWs2->id()->toString()}")
            ->assertStatus(403);

        // User 1 in ws-1 requests batch from ws-2 -> 404 Not Found
        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/imports/{$batchWs2->id()->toString()}")
            ->assertStatus(404);
    }

    #[Test]
    public function retrying_non_failed_batch_returns_409_conflict(): void
    {
        $batch = ImportBatch::create(ImportBatchId::generate(), 'ws-1', DatasetType::SALES, SourceFormat::CSV, 's.csv', 'p');
        $this->batchRepo->save($batch);

        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/imports/{$batch->id()->toString()}/retry");

        $response->assertStatus(409);
        $response->assertJsonPath('code', 'CONFLICT');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=ImportBatchApiTest`
Expected: FAIL with 404 for `/api/v1/imports`.

- [ ] **Step 3: Implement Form Request, Controller, and register routes in `routes/api.php`**

1. Create `backend/app/Modules/DataIngestion/Presentation/Requests/UploadImportRequest.php`:
```php
<?php

namespace App\Modules\DataIngestion\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class UploadImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:51200'],
            'dataset_type' => ['required', 'string', 'in:sales,inventory'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            new JsonResponse([
                'message' => 'Validation error',
                'code' => 'VALIDATION_ERROR',
                'details' => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
```

2. Create `backend/app/Modules/DataIngestion/Presentation/Controllers/ImportBatchController.php`:
```php
<?php

namespace App\Modules\DataIngestion\Presentation\Controllers;

use App\Modules\DataIngestion\Application\Commands\RetryImportBatchCommand;
use App\Modules\DataIngestion\Application\Commands\RetryImportBatchHandler;
use App\Modules\DataIngestion\Application\Commands\UploadImportBatchCommand;
use App\Modules\DataIngestion\Application\Commands\UploadImportBatchHandler;
use App\Modules\DataIngestion\Application\Dtos\ImportBatchDto;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchByIdHandler;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchByIdQuery;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchesHandler;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchesQuery;
use App\Modules\DataIngestion\Application\Queries\GetImportFailuresHandler;
use App\Modules\DataIngestion\Application\Queries\GetImportFailuresQuery;
use App\Modules\DataIngestion\Domain\Exceptions\CannotRetryImportException;
use App\Modules\DataIngestion\Domain\Exceptions\ImportBatchNotFoundException;
use App\Modules\DataIngestion\Presentation\Requests\UploadImportRequest;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class ImportBatchController
{
    public function index(
        Request $request,
        GetImportBatchesHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $status = $request->query('status');
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

            $result = $handler->handle(new GetImportBatchesQuery(
                workspaceId: $workspaceId,
                status: is_string($status) && $status !== '' ? $status : null,
                page: $page,
                perPage: $perPage,
            ));

            return response()->json([
                'items' => array_map(static fn (ImportBatchDto $b) => $b->toArray(), $result->items),
                'total' => $result->total,
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total_pages' => $result->totalPages,
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function store(
        UploadImportRequest $request,
        UploadImportBatchHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            /** @var UploadedFile $file */
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $ext = strtolower($file->getClientOriginalExtension());

            if (! in_array($ext, ['csv', 'json', 'jsonl'], true)) {
                return response()->json([
                    'message' => "Unsupported file format '.{$ext}'. Only CSV and JSON/JSONL files are supported.",
                    'code' => 'UNSUPPORTED_FORMAT',
                ], 422);
            }

            $sourceFormat = $ext === 'csv' ? 'csv' : 'json';
            $datasetType = (string) $request->input('dataset_type');

            $storedName = Str::uuid()->toString().'.'.$ext;
            $destinationDir = storage_path("app/imports/{$workspaceId}");
            if (! is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }
            $file->move($destinationDir, $storedName);
            $storedPath = "{$destinationDir}/{$storedName}";

            $batchDto = $handler->handle(new UploadImportBatchCommand(
                workspaceId: $workspaceId,
                datasetType: $datasetType,
                sourceFormat: $sourceFormat,
                originalFilename: $originalName,
                storedFilePath: $storedPath,
            ));

            return response()->json([
                'batch' => $batchDto->toArray(),
            ], 202);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function show(
        string $id,
        Request $request,
        GetImportBatchByIdHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $batchDto = $handler->handle(new GetImportBatchByIdQuery($workspaceId, $id));

            return response()->json([
                'batch' => $batchDto->toArray(),
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException|ImportBatchNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function failures(
        string $id,
        Request $request,
        GetImportFailuresHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('per_page', 50)));

            $result = $handler->handle(new GetImportFailuresQuery($workspaceId, $id, $page, $perPage));

            return response()->json([
                'items' => array_map(static fn ($f) => $f->toArray(), $result->items),
                'total' => $result->total,
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total_pages' => $result->totalPages,
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException|ImportBatchNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function retry(
        string $id,
        Request $request,
        RetryImportBatchHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $batchDto = $handler->handle(new RetryImportBatchCommand($workspaceId, $id));

            return response()->json([
                'batch' => $batchDto->toArray(),
            ], 202);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException|ImportBatchNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        } catch (CannotRetryImportException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CONFLICT'], 409);
        }
    }
}
```

3. Register routes in `backend/routes/api.php` under `AuthenticateUserIdMiddleware`:
```php
Route::get('/imports', [ImportBatchController::class, 'index']);
Route::post('/imports', [ImportBatchController::class, 'store']);
Route::get('/imports/{id}', [ImportBatchController::class, 'show']);
Route::get('/imports/{id}/failures', [ImportBatchController::class, 'failures']);
Route::post('/imports/{id}/retry', [ImportBatchController::class, 'retry']);
```

- [ ] **Step 4: Run tests and verify passing**

Run: `composer --working-dir=backend test -- --filter=ImportBatchApiTest`
Expected: PASS (5 tests, assertions pass)

- [ ] **Step 5: Commit presentation layer**

```bash
git add backend/app/Modules/DataIngestion/Presentation backend/routes/api.php backend/tests/Feature/Modules/DataIngestion/ImportBatchApiTest.php
git commit -m "feat(ingestion): implement REST API controller and endpoints for Data Ingestion"
```

---

### Task 5: End-to-End Pipeline Execution, Idempotency & Verification Checkpoint

**Files:**
- Create: `backend/tests/Feature/Modules/DataIngestion/ImportPipelineExecutionTest.php`
- Modify: `docs/roadmap/10-data-ingestion.md`
- Test: Full backend check (`make check-backend`)

**Interfaces:**
- Consumes: Complete Data Ingestion vertical slice
- Produces: Verified end-to-end processing pipeline, safe retry guarantees, idempotent Star Schema replay

- [ ] **Step 1: Write comprehensive pipeline integration test in `backend/tests/Feature/Modules/DataIngestion/ImportPipelineExecutionTest.php`**

```php
<?php

namespace Tests\Feature\Modules\DataIngestion;

use App\Modules\DataIngestion\Application\Commands\ProcessImportBatchCommand;
use App\Modules\DataIngestion\Application\Commands\ProcessImportBatchHandler;
use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\ImportStatus;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\SourceFormat;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class ImportPipelineExecutionTest extends TestCase
{
    private ImportBatchRepositoryInterface $batchRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);
        $this->batchRepo = $this->app->make(ImportBatchRepositoryInterface::class);

        $user = new User(new UserId('user-1'), 'admin@example.com', 'Admin');
        $userRepo->save($user);

        $ws = new Workspace(new WorkspaceId('ws-1'), 'Workspace 1', 'workspace-1');
        $ws->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws);
    }

    #[Test]
    public function full_lifecycle_upload_process_inspect_and_retry(): void
    {
        // 1. Upload CSV with 1 valid row and 1 invalid row
        $csvContent = "order_number,order_date,channel_code,region_code,warehouse_code,sku,quantity,unit_price,unit_cost,order_status\nORD-001,2026-03-01,online,RU-MSK,WH-01,BRAKE-01,2,100.00,50.00,completed\nORD-002,invalid-date,online,RU-MSK,WH-01,BRAKE-02,1,100.00,50.00,completed\n";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csvContent);

        $uploadResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->post('/api/v1/imports', [
            'file' => $file,
            'dataset_type' => 'sales',
        ])->assertStatus(202);

        $batchId = $uploadResponse->json('batch.id');
        self::assertNotEmpty($batchId);

        // 2. Execute process handler (simulating Queue Job)
        $processHandler = $this->app->make(ProcessImportBatchHandler::class);
        $processHandler->handle(new ProcessImportBatchCommand($batchId, 'ws-1'));

        // 3. Inspect batch detail
        $detailResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/imports/{$batchId}")
            ->assertOk();

        self::assertSame('completed_with_errors', $detailResponse->json('batch.status'));
        self::assertSame(2, $detailResponse->json('batch.total_rows'));
        self::assertSame(1, $detailResponse->json('batch.successful_rows'));
        self::assertSame(1, $detailResponse->json('batch.failed_rows'));

        // 4. Inspect failure rows
        $failuresResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/imports/{$batchId}/failures")
            ->assertOk();

        self::assertSame(1, $failuresResponse->json('total'));
        self::assertSame(2, $failuresResponse->json('items.0.row_number'));
        self::assertSame('order_date', $failuresResponse->json('items.0.field'));

        // 5. Trigger Retry
        $retryResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/imports/{$batchId}/retry")
            ->assertStatus(202);

        self::assertSame('pending', $retryResponse->json('batch.status'));
        self::assertSame(0, $retryResponse->json('batch.total_rows'));

        // 6. Re-process (Idempotency: duplicate processing does not duplicate facts)
        $processHandler->handle(new ProcessImportBatchCommand($batchId, 'ws-1'));

        $detailAfterRetry = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/imports/{$batchId}")
            ->assertOk();

        self::assertSame('completed_with_errors', $detailAfterRetry->json('batch.status'));
        self::assertSame(1, $detailAfterRetry->json('batch.successful_rows'));
        self::assertSame(1, $detailAfterRetry->json('batch.failed_rows'));
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=ImportPipelineExecutionTest`
Expected: PASS (1 test, assertions pass)

- [ ] **Step 3: Run full backend verification**

Run: `make check-backend`
Expected:
- Composer validate: valid
- Pint & Larastan: 0 errors
- PHPUnit: all tests pass (ArchitectureTest, Unit, Feature)

- [ ] **Step 4: Update roadmap progress in `docs/roadmap/10-data-ingestion.md`**

Record the completion of the Data Ingestion application layer, queue processing pipeline, REST endpoints, and safe retry execution.

- [ ] **Step 5: Commit completion**

```bash
git add backend/tests/Feature/Modules/DataIngestion/ImportPipelineExecutionTest.php docs/roadmap/10-data-ingestion.md
git commit -m "feat(ingestion): verify end-to-end ingestion pipeline with safe retry and idempotency"
```
