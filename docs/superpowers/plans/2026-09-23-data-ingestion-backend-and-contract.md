# Phase 10 — Data Ingestion: OpenAPI Contract, Staging Pipeline & Backend Core (Multi-Agent Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the foundational OpenAPI 3.0.3 contract, pure DDD domain model, PostgreSQL staging database schema, streaming parser & validation engine, idempotent Star Schema projector, asynchronous Laravel Queue processing pipeline, and REST API for Phase 10 (Data Ingestion), enabling reliable, workspace-isolated upload, validation, staging, async processing, failure tracking, and safe retries of automotive sales and inventory datasets without corrupting valid data.

**Architecture:** Domain-Driven Design (DDD) in Laravel 11 under bounded context `DataIngestion` with strict architectural layer separation (Domain, Application, Infrastructure, Presentation). Domain layer is 100% pure PHP decoupled from Laravel/Illuminate. Ingestion lifecycle follows a robust 3-stage pipeline: (1) Upload & Batch Registration, (2) Row-by-Row Staging & Validation into dedicated staging tables (`staging_sales_records`, `staging_inventory_records`, `import_failures`), (3) Idempotent Normalization & Projection into analytics Star Schema tables (`dim_*`, `fact_orders`, `fact_order_items`, `fact_inventory_daily`). Asynchronous execution uses Laravel Queues (`ProcessImportJob`). Multi-tenancy isolation is strictly enforced via `workspace_id`. Contracts are defined in OpenAPI 3.0.3 and TypeScript types generated for Next.js.

**Tech Stack:** Laravel 11 (PHP 8.3), PostgreSQL 16, Redis (queues), OpenAPI 3.0.3, Redocly CLI, openapi-typescript 7, PHPUnit 11, Next.js 16 (React 19, TypeScript).

**Spec:** `docs/roadmap/10-data-ingestion.md`, `docs/architecture/04-backend-laravel-ddd.md`, `docs/architecture/05-bounded-contexts.md`, `docs/architecture/06-data-and-analytics.md`, `docs/architecture/08-events-outbox-async.md`, `docs/architecture/12-architecture-decisions.md`.

---

## Agent Roles & Orchestration Matrix

According to `AGENTS.md` and `docs/roadmap/10-data-ingestion.md`, execution is distributed across specialized agent roles with strict write scopes and formal handoff gates:

| Агент | Роль в проекте | Задачи в данном плане | Write Scope | Read Scope |
|---|---|---|---|---|
| **Gemini Pro** | Repository / Analysis | Предварительный анализ форматов данных (CSV/JSON), правил валидации, существующих схем `dim_*`/`fact_*`, выявление edge cases | Отчёты/спецификации в контексте | `contracts/**`, `backend/database/migrations/**`, `docs/architecture/**` |
| **GPT-5.6** | Интегратор / Архитектор (Phase A) | OpenAPI 3.0.3 контракт, генерация TypeScript-типов, миграции staging-схемы PostgreSQL, модели Eloquent | `contracts/openapi/**`, `frontend/src/shared/api/**`, `backend/database/migrations/**`, `backend/app/Modules/DataIngestion/Infrastructure/Models/**` | `contracts/**`, `docs/architecture/07-api-and-integration.md` |
| **Claude Opus 4.6** | Domain / Business Logic | Чистая доменная модель `DataIngestion/Domain`: Aggregate Root `ImportBatch`, Value Objects, Enums, State Machine, Exceptions, интерфейсы репозиториев, юнит-тесты домена | `backend/app/Modules/DataIngestion/Domain/**`, `backend/tests/Unit/Modules/DataIngestion/ImportBatchDomainTest.php` | `docs/architecture/04-backend-laravel-ddd.md`, `docs/architecture/05-bounded-contexts.md`, `backend/tests/Unit/ArchitectureTest.php` |
| **GPT-5.6** | Интегратор / Pipeline Engine | Потоковые парсеры CSV/JSON, валидаторы строк, идемпотентный Star Schema проектор, CQRS команды/запросы, Laravel Queue Job (`ProcessImportJob`), контроллер REST API, изоляция multi-tenancy | `backend/app/Modules/DataIngestion/Infrastructure/**`, `backend/app/Modules/DataIngestion/Application/**`, `backend/app/Modules/DataIngestion/Presentation/**`, `backend/routes/api.php`, `backend/tests/Feature/**` | Весь backend, `docs/architecture/06-data-and-analytics.md`, `08-events-outbox-async.md` |
| **Claude Opus 4.6** | Domain & Architectural Review | Независимое ревью DDD-границ, проверка отсутствия утечек инфраструктуры в домен, проверка `ArchitectureTest`, аудит безопасности повторных запусков (safe retry) и идемпотентности | Замечания к PR / исправления в `Domain/**` | `backend/app/Modules/DataIngestion/**`, `backend/tests/**` |
| **GPT-5.6** | Финальная интеграция и верификация | Запуск полного цикла проверок (`make check`, `verify-integration.sh`), фиксация прогресса в `docs/roadmap/10-data-ingestion.md` | `docs/roadmap/10-data-ingestion.md` | Корень репозитория |

---

## Global Constraints

- Domain Layer in `App\Modules\DataIngestion\Domain` MUST NOT depend on Laravel/Illuminate, framework helpers, or Infrastructure layers (`ArchitectureTest`).
- Bounded context `DataIngestion` owns import datasets, staging, parsing, validation, and initiates analytics projection updates (`docs/architecture/05-bounded-contexts.md`).
- Multi-tenancy isolation MUST be strictly enforced on every command and query via `workspace_id` through `GetCurrentWorkspaceHandler` / `WorkspaceAccessGuard` (cross-workspace requests return `403 Forbidden`).
- Data staging MUST logically separate raw/staging data from analytics facts (`docs/architecture/06-data-and-analytics.md`).
- Invalid imports MUST NOT corrupt valid data: invalid rows are recorded in `import_failures` with row number and error description, while valid rows can proceed or be rolled back cleanly based on batch policy (`docs/roadmap/10-data-ingestion.md`).
- Projections into Star Schema MUST be idempotent and retry-safe: re-processing an import batch must NOT create duplicate facts or duplicate orders (`docs/architecture/08-events-outbox-async.md`).
- OpenAPI specification in `contracts/openapi/analytics-v1.yaml` is the single source of truth; TypeScript types must be generated via `npm --prefix frontend run api:generate` (`docs/architecture/07-api-and-integration.md`, ADR-007).
- All commands and queries adhere to CQRS-lite (`docs/architecture/04-backend-laravel-ddd.md`, ADR-006).

---

### Task 1: [Gemini Pro] Анализ форматов данных, структуры Star Schema и правил валидации

**Role:** Gemini Pro (Repository / Analysis)
**Write Scope:** `docs/superpowers/plans/*` (или контекстный отчёт)
**Read Scope:** `backend/database/migrations/2026_09_22_000010_create_analytics_dimensions_tables.php`, `backend/database/migrations/2026_09_22_000011_create_analytics_facts_tables.php`, `backend/app/Modules/SalesAnalytics/**`, `backend/app/Modules/InventoryAnalytics/**`.

- [ ] **Step 1: Инвентаризация входящих структур данных**
  - **Sales Dataset:**
    - Поля: `order_number` (string), `order_date` (date YYYY-MM-DD), `channel_code` (string), `region_code` (string), `warehouse_code` (string), `sku` (string), `quantity` (int > 0), `unit_price` (numeric >= 0), `unit_cost` (numeric >= 0), `order_status` (string).
    - Соответствие фактам: `fact_orders` (группировка по `order_number`), `fact_order_items` (детализация позиций).
  - **Inventory Dataset:**
    - Поля: `snapshot_date` (date YYYY-MM-DD), `warehouse_code` (string), `sku` (string), `quantity_on_hand` (int >= 0), `quantity_reserved` (int >= 0), `safety_stock` (int >= 0), `reorder_point` (int >= 0), `unit_cost` (numeric >= 0).
    - Соответствие фактам: `fact_inventory_daily` (уникальный ключ `(workspace_id, snapshot_date, product_id, warehouse_id)`).

- [ ] **Step 2: Формирование Handoff для Интегратора (GPT-5.6)**
  - Передать утверждённый перечень полей и их типов для схемы OpenAPI и миграций staging-таблиц.

---

### Task 2: [GPT-5.6] OpenAPI 3.0.3 Контракт и типизация

**Role:** GPT-5.6 (Интегратор / Архитектор)
**Write Scope:** `contracts/openapi/analytics-v1.yaml`, `backend/tests/Feature/ApiContractTest.php`, `frontend/src/shared/api/generated/schema.ts`
**Read Scope:** `contracts/openapi/analytics-v1.yaml`, `docs/architecture/07-api-and-integration.md`

**Interfaces:**
- Consumes: Existing OpenAPI schemas (`ErrorResponse`, `UserIdAuth`)
- Produces:
  - `POST /imports` -> `ImportBatchDetailResponse` (multipart/form-data upload)
  - `GET /imports` -> `ImportBatchListResponse`
  - `GET /imports/{id}` -> `ImportBatchDetailResponse`
  - `GET /imports/{id}/failures` -> `ImportFailureListResponse`
  - `POST /imports/{id}/retry` -> `ImportBatchDetailResponse`
  - Schemas: `ImportBatchSummary`, `ImportBatchListResponse`, `ImportBatchDetail`, `ImportBatchDetailResponse`, `ImportFailureItem`, `ImportFailureListResponse`

- [ ] **Step 1: Write failing test in `backend/tests/Feature/ApiContractTest.php`**

Add assertion method to `backend/tests/Feature/ApiContractTest.php`:
```php
    public function test_contract_contains_data_ingestion_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/imports', $contract['paths']);
        self::assertArrayHasKey('/imports/{id}', $contract['paths']);
        self::assertArrayHasKey('/imports/{id}/failures', $contract['paths']);
        self::assertArrayHasKey('/imports/{id}/retry', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('ImportBatchSummary', $schemas);
        self::assertArrayHasKey('ImportBatchListResponse', $schemas);
        self::assertArrayHasKey('ImportBatchDetail', $schemas);
        self::assertArrayHasKey('ImportBatchDetailResponse', $schemas);
        self::assertArrayHasKey('ImportFailureItem', $schemas);
        self::assertArrayHasKey('ImportFailureListResponse', $schemas);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_data_ingestion_endpoints`
Expected: FAIL with "Failed asserting that an array has key '/imports'."

- [ ] **Step 3: Update `contracts/openapi/analytics-v1.yaml` and regenerate TypeScript types**

Add `/imports` endpoints and schemas to `contracts/openapi/analytics-v1.yaml`:
- `paths:`
  - `/imports`:
    - `get`: operationId `getImportBatches`, summary: "List data ingestion batches for workspace", returns `ImportBatchListResponse`.
    - `post`: operationId `uploadImportBatch`, summary: "Upload data ingestion file", requestBody multipart/form-data with `file` (binary) and `dataset_type` (string enum: `sales`, `inventory`), returns `202 Accepted` with `ImportBatchDetailResponse`.
  - `/imports/{id}`:
    - `get`: operationId `getImportBatch`, summary: "Get import batch details and progress", returns `ImportBatchDetailResponse`.
  - `/imports/{id}/failures`:
    - `get`: operationId `getImportFailures`, summary: "List validation failure rows for import batch", returns `ImportFailureListResponse`.
  - `/imports/{id}/retry`:
    - `post`: operationId `retryImportBatch`, summary: "Retry failed or incomplete import batch", returns `202 Accepted` with `ImportBatchDetailResponse`.
- `components/schemas`:
  - `ImportBatchSummary`: id, workspace_id, dataset_type, source_format, original_filename, status, total_rows, processed_rows, successful_rows, failed_rows, error_message, created_at, completed_at.
  - `ImportBatchListResponse`: items (array of `ImportBatchSummary`).
  - `ImportBatchDetail`: includes all summary fields plus progress percentage and storage info.
  - `ImportBatchDetailResponse`: batch (`ImportBatchDetail`).
  - `ImportFailureItem`: id, row_number, field, value, error_message, created_at.
  - `ImportFailureListResponse`: items (array of `ImportFailureItem`), total (integer).

Validate contract:
Run: `npm --prefix frontend run contracts:validate`
Expected: "validating ../contracts/openapi/analytics-v1.yaml... Your API description is valid."

Generate TypeScript types:
Run: `npm --prefix frontend run api:generate`

- [ ] **Step 4: Run contract test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_data_ingestion_endpoints`
Expected: PASS

- [ ] **Step 5: Commit contract changes**

```bash
git add contracts/openapi/analytics-v1.yaml backend/tests/Feature/ApiContractTest.php frontend/src/shared/api/generated/schema.ts
git commit -m "feat(ingestion): add OpenAPI contract for data ingestion endpoints"
```

---

### Task 3: [Claude Opus 4.6] Pure DDD Domain Model for Data Ingestion

**Role:** Claude Opus 4.6 (Domain Modeling)
**Write Scope:** `backend/app/Modules/DataIngestion/Domain/**`, `backend/tests/Unit/Modules/DataIngestion/ImportBatchDomainTest.php`
**Read Scope:** `docs/architecture/04-backend-laravel-ddd.md`, `docs/architecture/05-bounded-contexts.md`, `backend/tests/Unit/ArchitectureTest.php`

**Files:**
- Create: `backend/app/Modules/DataIngestion/Domain/ImportBatchId.php`
- Create: `backend/app/Modules/DataIngestion/Domain/DatasetType.php`
- Create: `backend/app/Modules/DataIngestion/Domain/ImportStatus.php`
- Create: `backend/app/Modules/DataIngestion/Domain/SourceFormat.php`
- Create: `backend/app/Modules/DataIngestion/Domain/ImportStats.php`
- Create: `backend/app/Modules/DataIngestion/Domain/RowError.php`
- Create: `backend/app/Modules/DataIngestion/Domain/ImportBatch.php`
- Create: `backend/app/Modules/DataIngestion/Domain/Repositories/ImportBatchRepositoryInterface.php`
- Create: `backend/app/Modules/DataIngestion/Domain/Repositories/ImportFailureRepositoryInterface.php`
- Create: `backend/app/Modules/DataIngestion/Domain/Exceptions/ImportBatchNotFoundException.php`
- Create: `backend/app/Modules/DataIngestion/Domain/Exceptions/InvalidImportFileException.php`
- Create: `backend/app/Modules/DataIngestion/Domain/Exceptions/CannotRetryImportException.php`
- Create: `backend/tests/Unit/Modules/DataIngestion/ImportBatchDomainTest.php`

**Interfaces:**
- Consumes: Standard PHP types, `DateTimeImmutable`
- Produces:
  - `ImportBatchId::generate(): self`, `ImportBatchId::fromString(string $val): self`
  - `DatasetType::SALES`, `DatasetType::INVENTORY`
  - `ImportStatus::PENDING`, `ImportStatus::VALIDATING`, `ImportStatus::PROCESSING`, `ImportStatus::COMPLETED`, `ImportStatus::COMPLETED_WITH_ERRORS`, `ImportStatus::FAILED`
  - `SourceFormat::CSV`, `SourceFormat::JSON`
  - `RowError(int $rowNumber, ?string $field, ?string $value, string $message)`
  - `ImportBatch`: aggregate root with state transitions: `startValidation()`, `startProcessing(int $total)`, `recordProgress(int $processed, int $successful, int $failed)`, `markCompleted()`, `markCompletedWithErrors()`, `markFailed(string $reason)`, `canRetry()`, `prepareRetry()`
  - Repositories: `ImportBatchRepositoryInterface`, `ImportFailureRepositoryInterface`

- [ ] **Step 1: Write failing domain test in `backend/tests/Unit/Modules/DataIngestion/ImportBatchDomainTest.php`**

```php
<?php

namespace Tests\Unit\Modules\DataIngestion;

use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\Exceptions\CannotRetryImportException;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\ImportStatus;
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
            createdAt: new DateTimeImmutable(),
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
            createdAt: new DateTimeImmutable(),
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
            createdAt: new DateTimeImmutable(),
            completedAt: null,
        );

        self::assertFalse($batch->canRetry());
        $this->expectException(CannotRetryImportException::class);
        $batch->prepareRetry();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=ImportBatchDomainTest`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement pure Domain classes and Enums**

Implement `ImportBatchId`, `DatasetType`, `ImportStatus`, `SourceFormat`, `RowError`, `ImportBatch`, and repository interfaces.

- [ ] **Step 4: Run tests and verify ArchitectureTest passes**

Run: `composer --working-dir=backend test -- --filter=ImportBatchDomainTest`
Expected: PASS
Run: `composer --working-dir=backend test -- --filter=ArchitectureTest`
Expected: PASS (0 architectural violations)

- [ ] **Step 5: Commit Domain layer**

```bash
git add backend/app/Modules/DataIngestion/Domain backend/tests/Unit/Modules/DataIngestion/ImportBatchDomainTest.php
git commit -m "feat(ingestion): implement pure DDD domain model for DataIngestion"
```

---

### Task 4: [GPT-5.6] Staging Schema, Migrations & Eloquent Persistence

**Role:** GPT-5.6 (Интегратор / Persistence)
**Write Scope:** `backend/database/migrations/**`, `backend/app/Modules/DataIngestion/Infrastructure/**`, `backend/tests/Unit/Modules/DataIngestion/ImportBatchRepositoryTest.php`, `backend/app/Providers/AppServiceProvider.php`
**Read Scope:** `docs/architecture/06-data-and-analytics.md`, `backend/app/Modules/DataIngestion/Domain/**`

**Files:**
- Create: `backend/database/migrations/2026_09_23_000030_create_import_batches_table.php`
- Create: `backend/database/migrations/2026_09_23_000031_create_staging_records_tables.php`
- Create: `backend/database/migrations/2026_09_23_000032_create_import_failures_table.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Models/ImportBatchModel.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Models/StagingSalesRecordModel.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Models/StagingInventoryRecordModel.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Models/ImportFailureModel.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Repositories/EloquentImportBatchRepository.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Repositories/EloquentImportFailureRepository.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Repositories/InMemoryImportBatchRepository.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Repositories/InMemoryImportFailureRepository.php`
- Create: `backend/tests/Unit/Modules/DataIngestion/ImportBatchRepositoryTest.php`

- [ ] **Step 1: Write failing repository test in `backend/tests/Unit/Modules/DataIngestion/ImportBatchRepositoryTest.php`**
- [ ] **Step 2: Run test to verify it fails**
- [ ] **Step 3: Implement migrations, Eloquent models, repositories, and register DI bindings in `AppServiceProvider`**
- [ ] **Step 4: Run repository tests and confirm passing**
- [ ] **Step 5: Commit persistence layer**

```bash
git add backend/database/migrations backend/app/Modules/DataIngestion/Infrastructure backend/tests/Unit/Modules/DataIngestion/ImportBatchRepositoryTest.php backend/app/Providers/AppServiceProvider.php
git commit -m "feat(ingestion): add migrations, Eloquent models and repositories for DataIngestion"
```

---

### Task 5: [GPT-5.6] File Parsing, Row Validation & Idempotent Star Schema Projection

**Role:** GPT-5.6 (Интегратор / Data Processing)
**Write Scope:** `backend/app/Modules/DataIngestion/Infrastructure/Parsers/**`, `backend/app/Modules/DataIngestion/Infrastructure/Validators/**`, `backend/app/Modules/DataIngestion/Infrastructure/Projection/**`, `backend/tests/Unit/Modules/DataIngestion/*`
**Read Scope:** `docs/architecture/06-data-and-analytics.md`, `docs/architecture/08-events-outbox-async.md`, existing Star Schema tables.

**Files:**
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Parsers/ImportParserInterface.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Parsers/CsvImportParser.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Parsers/JsonImportParser.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Validators/RowValidatorInterface.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Validators/SalesRowValidator.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Validators/InventoryRowValidator.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Projection/StarSchemaProjector.php`
- Create: `backend/tests/Unit/Modules/DataIngestion/ImportParserAndValidationTest.php`
- Create: `backend/tests/Unit/Modules/DataIngestion/StarSchemaProjectionTest.php`

- [ ] **Step 1: Write failing parser, validation, and idempotent projection tests**
- [ ] **Step 2: Run tests to verify failures**
- [ ] **Step 3: Implement `CsvImportParser`, `JsonImportParser`, `SalesRowValidator`, `InventoryRowValidator`, and `StarSchemaProjector`**
- [ ] **Step 4: Run tests and verify idempotent replay safety (re-processing does not duplicate rows)**
- [ ] **Step 5: Commit parsers, validators, and projection engine**

```bash
git add backend/app/Modules/DataIngestion/Infrastructure/Parsers backend/app/Modules/DataIngestion/Infrastructure/Validators backend/app/Modules/DataIngestion/Infrastructure/Projection backend/tests/Unit/Modules/DataIngestion/ImportParserAndValidationTest.php backend/tests/Unit/Modules/DataIngestion/StarSchemaProjectionTest.php
git commit -m "feat(ingestion): implement parsers, validators, and idempotent Star Schema projector"
```

---

### Task 6: [GPT-5.6] Application CQRS Layer & Laravel Queue Job Pipeline

**Role:** GPT-5.6 (Интегратор / Application)
**Write Scope:** `backend/app/Modules/DataIngestion/Application/**`, `backend/app/Modules/DataIngestion/Infrastructure/Jobs/**`, `backend/tests/Unit/Modules/DataIngestion/ImportApplicationTest.php`
**Read Scope:** `docs/architecture/04-backend-laravel-ddd.md`, `docs/architecture/08-events-outbox-async.md`

**Files:**
- Create: `backend/app/Modules/DataIngestion/Application/Commands/UploadImportBatchCommand.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/UploadImportBatchHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/ProcessImportBatchCommand.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/ProcessImportBatchHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/RetryImportBatchCommand.php`
- Create: `backend/app/Modules/DataIngestion/Application/Commands/RetryImportBatchHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchesQuery.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchesHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchByIdQuery.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportBatchByIdHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportFailuresQuery.php`
- Create: `backend/app/Modules/DataIngestion/Application/Queries/GetImportFailuresHandler.php`
- Create: `backend/app/Modules/DataIngestion/Application/Dtos/ImportBatchDto.php`
- Create: `backend/app/Modules/DataIngestion/Application/Dtos/ImportFailureDto.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Jobs/ProcessImportJob.php`
- Create: `backend/tests/Unit/Modules/DataIngestion/ImportApplicationTest.php`

- [ ] **Step 1: Write failing application tests for upload, queue processing, progress updates, and retry**
- [ ] **Step 2: Run tests to verify failures**
- [ ] **Step 3: Implement CQRS commands, queries, handlers, and `ProcessImportJob`**
- [ ] **Step 4: Run application tests and confirm passing**
- [ ] **Step 5: Commit application layer**

```bash
git add backend/app/Modules/DataIngestion/Application backend/app/Modules/DataIngestion/Infrastructure/Jobs backend/tests/Unit/Modules/DataIngestion/ImportApplicationTest.php
git commit -m "feat(ingestion): implement CQRS commands, queries and Laravel queue job"
```

---

### Task 7: [GPT-5.6] Presentation Layer, REST Controllers, Security & Feature Tests

**Role:** GPT-5.6 (Интегратор / Presentation)
**Write Scope:** `backend/app/Modules/DataIngestion/Presentation/**`, `backend/routes/api.php`, `backend/tests/Feature/ImportApiTest.php`
**Read Scope:** `docs/architecture/07-api-and-integration.md`, `backend/app/Modules/Workspace/**`

**Files:**
- Create: `backend/app/Modules/DataIngestion/Presentation/Requests/UploadImportRequest.php`
- Create: `backend/app/Modules/DataIngestion/Presentation/Controllers/ImportController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/ImportApiTest.php`

- [ ] **Step 1: Write failing HTTP feature tests with multi-tenancy isolation and queue fakes**
- [ ] **Step 2: Run test to verify failure**
- [ ] **Step 3: Implement `UploadImportRequest`, `ImportController`, and register routes in `routes/api.php`**
- [ ] **Step 4: Run feature tests and verify passing**
- [ ] **Step 5: Commit presentation layer**

```bash
git add backend/app/Modules/DataIngestion/Presentation backend/routes/api.php backend/tests/Feature/ImportApiTest.php
git commit -m "feat(ingestion): implement REST API controller and feature tests for Data Ingestion"
```

---

### Task 8: [Claude Opus 4.6] Domain & Architectural Review

**Role:** Claude Opus 4.6 (Domain & Quality Reviewer)
**Write Scope:** `backend/app/Modules/DataIngestion/Domain/**` (при необходимости правок)
**Read Scope:** Весь код модуля `DataIngestion`, `ArchitectureTest.php`, `docs/architecture/**`

- [ ] **Step 1: Проверка изоляции слоя Domain**
  - Запустить `ArchitectureTest` (`composer --working-dir=backend test -- --filter=ArchitectureTest`).
  - Убедиться в отсутствии зависимостей `Illuminate\*`, `Laravel\*`, инфраструктурных хелперов и других bounded contexts в `Domain`.
- [ ] **Step 2: Аудит инвариантов и устойчивости к ошибкам**
  - Проверить переходы состояний `ImportBatch` (невозможность повторного запуска `in_progress` батча).
  - Проверить, что ошибочные строки изолируются в `import_failures` без падения всей транзакции и без искажения существующих данных в `fact_orders` / `fact_inventory_daily`.
- [ ] **Step 3: Одобрение или исправление замечаний**

---

### Task 9: [GPT-5.6] Финальная интеграция, сквозная валидация и фиксация прогресса

**Role:** GPT-5.6 (Интегратор)
**Write Scope:** `docs/roadmap/10-data-ingestion.md`
**Read Scope:** Весь репозиторий

- [ ] **Step 1: Запуск полного набора автоматических проверок проекта**
  - `npm --prefix frontend run contracts:validate`
  - `npm --prefix frontend run lint && npm --prefix frontend run typecheck && npm --prefix frontend test`
  - `composer --working-dir=backend validate --strict`
  - `composer --working-dir=backend lint`
  - `composer --working-dir=backend test`
- [ ] **Step 2: Обновление прогресса в `docs/roadmap/10-data-ingestion.md`**
  - Зафиксировать выполненную работу по Task 1 в разделе «Прогресс».
  - Описать следующие шаги: Task 2 (Frontend Data Ingestion UI) и Task 3 (Integration Checkpoint).
- [ ] **Step 3: Финальный коммит интеграции**

```bash
git add docs/roadmap/10-data-ingestion.md
git commit -m "docs(roadmap): record progress for Phase 10 Task 1 Data Ingestion Backend Engine & Contract"
```

---

## Handoff Protocol Between Agents

Каждая передача задачи между агентами строго документируется по правилам `AGENTS.md`:
1. **Gemini Pro -> GPT-5.6 (Phase A):** Передача проверенных структур полей и ограничений форматов CSV/JSON для проектирования контракта и миграций.
2. **GPT-5.6 (Phase A) -> Claude Opus 4.6 (Domain):** Зафиксированный контракт и схемы данных, передача задачи на доменное моделирование `DataIngestion/Domain`.
3. **Claude Opus 4.6 -> GPT-5.6 (Pipeline Engine):** Готовый и протестированный доменный слой (чистый PHP, 0 внешних зависимостей, подтверждено `ArchitectureTest`).
4. **GPT-5.6 (Pipeline Engine) -> Claude Opus 4.6 (Reviewer):** Реализованный пайплайн, парсеры, проектор, очередь, контроллер и тесты для ревью связности и обработки граничных случаев.
5. **Claude Opus 4.6 (Reviewer) -> GPT-5.6 (Интегратор):** Одобренный код для финального прогона проверок `make check` и обновления документации Roadmap.
