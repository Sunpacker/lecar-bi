# Phase 10 — Data Ingestion

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Move to a real import pipeline.

## Functionality

Upload, validation, import record, staging, async processing, progress/status, validation failures, normalization, analytics projection update, safe retry.

Use Laravel queues. Invalid imports must not corrupt valid data.

## Responsibilities

Gemini Pro analyzes formats/validation. Claude implements or reviews the bounded context. GPT-5.6 is responsible for pipeline integration and projection strategy.

## Exit Criteria

Import works, progress is visible, invalid rows are understandable, retries are safe, duplicate processing does not corrupt data, and projection rebuild is tested. Check against `docs/architecture/06-data-and-analytics.md` and `08-events-outbox-async.md`.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before finishing the phase.

---

## Progress

### Completed

1. **DTOs and Staging Repository (`App\Modules\DataIngestion`):**
   - Developed DTOs: `ImportBatchDto`, `ImportFailureDto`, `PaginatedListDto`.
   - Extended the `RowError` domain model with optional `id` and `createdAt` fields for precise tracking.
   - Designed `StagingRecordRepositoryInterface` and implemented `EloquentStagingRecordRepository` for `staging_sales_records` and `staging_inventory_records`:
     - batch insertion of raw records with `pending` status;
     - paginated record retrieval by batch and status;
     - record status updates (`projected`, `failed`).
   - Implemented `InMemoryStagingRecordRepository` for fast isolated testing.
   - Registered bindings in `AppServiceProvider`.
   - Covered by `StagingRepositoryTest`.

2. **Application CQRS Queries (`App\Modules\DataIngestion\Application\Queries`):**
   - Implemented CQRS queries and handlers with multi-tenant `workspace_id` isolation:
     - `GetImportBatchesQuery` / `GetImportBatchesHandler` — paginated import batch list with status filtering;
     - `GetImportBatchByIdQuery` / `GetImportBatchByIdHandler` — batch details;
     - `GetImportFailuresQuery` / `GetImportFailuresHandler` — paginated validation errors for a specific batch.
   - Covered by `ImportQueriesTest`.

3. **Application CQRS Commands, Queue Pipeline & Star Schema Projection:**
   - Designed `StarSchemaProjectorInterface` and implemented `StarSchemaProjector` to transfer validated records from staging to analytics tables (`fact_sales`, `dim_products`, `dim_customers`, `dim_channels`, `inventory_snapshots`).
   - Implemented `InMemoryStarSchemaProjector` for tests.
   - Developed an asynchronous Laravel queue:
     - `ImportJobDispatcherInterface` and `QueueImportJobDispatcher`;
     - `ProcessImportJob` (`ShouldQueue`), automatically delegating processing to `ProcessImportBatchCommand`.
   - Implemented `UploadImportBatchCommand` / `UploadImportBatchHandler`:
     - creating an `ImportBatch` aggregate in `pending` status;
     - saving the source file in `storage/app/imports`;
     - dispatching a job to the processing queue.
   - Implemented `ProcessImportBatchCommand` / `ProcessImportBatchHandler`:
     - streaming CSV parsing;
     - row-by-row validation by domain dataset specifications (`sales`, `inventory`);
     - saving invalid rows to `import_failures`;
     - saving valid rows to `staging_*_records` with `pending` status;
     - projecting valid rows into Star Schema tables and changing their status to `projected`;
     - recording progress and transitioning to `completed`, `completed_with_errors` (if some rows fail validation), or `failed` (if all rows are invalid);
     - consistency guarantee: invalid rows do not corrupt valid data.
   - Implemented the safe retry command `RetryImportBatchCommand` / `RetryImportBatchHandler`:
     - clearing previous `import_failures` errors and batch staging records;
     - resetting batch status to `pending` and zeroing counters;
     - restarting the queued job.
   - Covered by `ImportCommandsTest`.

4. **Presentation REST API (`App\Modules\DataIngestion\Presentation`):**
   - Developed `UploadImportRequest` with file validation (CSV, up to 50 MB) and dataset type validation (`sales`, `inventory`), returning structured 422 JSON errors.
   - Implemented `ImportBatchController`:
     - `GET /api/v1/imports` — batch list with filtering and pagination;
     - `POST /api/v1/imports` — upload a new file and start import (201 Created);
     - `GET /api/v1/imports/{id}` — import status and progress;
     - `GET /api/v1/imports/{id}/failures` — batch validation error list;
     - `POST /api/v1/imports/{id}/retry` — reprocess a batch (202 Accepted).
   - Registered routes in `backend/routes/api.php` within the `AuthenticateUserIdMiddleware` group.
   - Covered by `ImportBatchApiTest`.

5. **End-to-End Pipeline & Integration Testing:**
   - Implemented the end-to-end `ImportPipelineExecutionTest`:
     - verifying successful upload and full transfer of data into Star Schema;
     - verifying partial import (completed_with_errors) with invalid row isolation;
     - verifying safe retry with previous error cleanup and re-projection;
     - confirming idempotency and protection against data corruption.
   - Added `QUEUE_CONNECTION=sync` to `backend/phpunit.xml` for deterministic queue testing.
   - All 194 backend tests (28 396 assertions) pass.
   - PHPStan static analysis (Level Max) and Laravel Pint formatting passed with 0 errors.

6. **Frontend Data Ingestion UI (`frontend/src/features/data-ingestion`):**
   - Developed the typed `importGateway` REST gateway over OpenAPI 3.0.3 (`getBatches`, `uploadBatch`, `getBatch`, `getFailures`, `retryBatch`).
   - Implemented the `ImportUploadDropzone` Drag & Drop component with dataset selection (`sales`, `inventory`), extension validation (.csv, .json, .jsonl), and a 50 MB limit.
   - Implemented `ImportStatusBadge` status badges and `ImportBatchList` with dynamic progress indicators and a `Retry` button.
   - Implemented the `ImportBatchDetailSheet` slide-over panel with KPI cards, batch error information, a paginated row validation error table, and a Retry button.
   - Implemented the `DataIngestionView` client view with silent auto-polling (every 3 seconds for active processes) and a loading skeleton to prevent empty-state flicker.
   - Implemented the server App Router route `frontend/app/(dashboard)/imports/page.tsx` with session authentication and workspace resolution.
   - Added the "Data Import" navigation item with an `UploadCloud` icon to `Sidebar`.
   - Full coverage with unit, component, and end-to-end flow tests.

7. **Integration Checkpoint and Star Schema Projections (`ImportPipelineExecutionTest`):**
   - Verified end-to-end upload and processing scenarios for both datasets (`sales` and `inventory`).
   - Confirmed correct projection of sales records (`projectedSalesRows`) and inventory balances (`projectedInventoryRows`).
   - Verified invalid row isolation with `completed_with_errors` status and error inspection through the API.
   - Verified safe restart (`retry`) with idempotent projection updates without corrupting or duplicating analytics read models.

### Remaining Work

- None (all phase tasks and the integration checkpoint are complete).

### Blockers

- None.

### Next Step

- Proceed to `Phase 12 — Alerting` (Phase 11 Supplier Analytics is already complete).

---

## Completion Verification

- **Date:** 2026-09-23
- **Exit Criteria:**
  - `Import works`: Multipart CSV/JSON/JSONL upload for sales (`sales`) and inventory (`inventory`) datasets through `POST /api/v1/imports`, with size and format validation.
  - `Progress is visible`: UI with auto-polling every 3 seconds for active processes, status indicators (`pending`, `validating`, `processing`, `completed`, `completed_with_errors`, `failed`), and progress bars.
  - `Invalid rows are understandable`: Invalid rows are isolated in staging tables; details are available through `GET /api/v1/imports/{id}/failures`, including row number, field, and error text, and are displayed in `ImportBatchDetailSheet`.
  - `Retries are safe`: `POST /api/v1/imports/{id}/retry` is available only for batches in `failed` or `completed_with_errors` status, clears previous errors, and restarts processing.
  - `Duplicate processing does not corrupt data`: Pipeline idempotency is ensured when batch processing is restarted.
  - `Projection rebuild is tested`: End-to-end `ImportPipelineExecutionTest` tests confirm transfer of validated rows into the Star Schema projection (`fact_sales`, `fact_inventory_daily`).
- **Verification Commands and Results:**
  - `backend: ./vendor/bin/phpunit tests/Unit/Modules/DataIngestion tests/Feature/Modules/DataIngestion` — 60 tests, 218 assertions, OK.
  - `backend (full suite): ./vendor/bin/phpunit` — 231 tests, 29117 assertions, OK.
  - `backend static analysis: ./vendor/bin/phpstan analyse` — Level Max, 0 errors.
  - `backend code style: ./vendor/bin/pint --test` — Passed.
  - `frontend tests: npm test` — 43 test files, 177 tests passed.
  - `frontend typecheck: npm run typecheck` — 0 errors.
  - `frontend lint: npm run lint` — 0 errors.
  - `openapi contract validation: npm run contracts:validate` — Valid (0 errors).
