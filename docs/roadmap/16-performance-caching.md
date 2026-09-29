# Phase 16 — Performance and Caching

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Prepare the system for larger data volumes.

## Work

Query timings, expensive query analysis, indexes, selective caching, materialized views/projections, invalidation strategy, larger datasets.

Do not cache without a freshness/invalidation strategy.

## Exit Criteria

The performance baseline is documented, bottlenecks are measured, optimization is evidence-driven, cache semantics are clear, and correctness has not degraded.

## Progress

### Completed

- **Task 1:** Engineering performance contract, load testing protocol, latency budgets, and data profiles documented in `docs/performance/README.md` and `docs/performance/phase-16-baseline.md`.
- **Task 2:** Implemented the streaming deterministic reference dataset generator `PerformanceDatasetGenerator`, console commands `performance:seed` and `performance:benchmark`, and strict environment safety tests `PerformanceCommandSafetyTest`.
- **Task 3:** Collected baseline measurements on the `large` profile (100k orders, 300k items, 500k inventory records, 200k deliveries, 10k products), with `EXPLAIN (ANALYZE, BUFFERS)` artifacts in `docs/performance/plans/phase-16-before/`. Identified and localized the engine's main bottleneck: an unindexed `Seq Scan` with a sort spilling to disk (13.6 MB temp spill) in `SALES-01` (p95 = 1 947 ms).
- **Task 4:** Added covering B-tree indexes (`2026_09_23_000070_add_phase_16_analytics_indexes.php`), including `idx_foi_ws_order_covering` (`INCLUDE (total_price, gross_profit)`). Decomposed the monolithic `PostgresInventoryAnalyticsReadModel` into query collaborators (`InventorySummaryQuery`, `InventoryItemsQuery`, `AbcXyzSummaryQuery`, `InventoryFilterOptionsQuery`). After-SQL plans switched to `Index Only Scan` with 0 MB temp disk spill (`docs/performance/plans/phase-16-after-sql/`). Reduced `SALES-01` latency to 370 ms (5.2 times faster), `DASH-01` to 747 ms, and `DASH-02` to 1 161 ms.
- **Task 5:** Evidence-based decision on materialized views / projections: rejected based on measurements. All 23 analytics scenarios met their budgets using SQL and indexes alone, preserving architectural simplicity and eliminating Write Amplification during data imports.
- **Task 6:** Implemented Selective Versioned Cache in Redis at the Read Model interface boundary:
  - Allowlist of 7 methods (KPI summaries and filter options);
  - No caching of paginated lists or search queries;
  - Deterministic parameter canonicalization via `CanonicalCriteria` and standardized cache key `AnalyticsCacheKey`;
  - Monotonic dataset versioning in `analytics_dataset_versions`;
  - Automatic invalidation when batches are imported in Data Ingestion;
  - Fail-Open architecture with sanitized logging of technical metadata.
- **Task 7:** Assessed the need for frontend caching: persistent client-side caching rejected (ADR-013, ADR-020). Added lightweight request-scoped promise coalescing (`coalesceInFlightRequest`) to `widget-data-loader.ts`, merging parallel dashboard widget requests within a single render cycle.
- **Task 8:** Added a full set of regression and parity tests (`AnalyticsCacheParityAndRegressionTest.php`, `AnalyticsCacheInvalidationTest.php`, `AnalyticsCacheIntegrationTest.php`, `AnalyticsQueryPlanTest.php`, `widget-data-loader.test.ts`), and updated the integration verification script `scripts/verify-integration.sh`.
- **Task 9:** Updated architecture documentation (06, 09, 10) and recorded architectural decision `ADR-020` in `docs/architecture/12-architecture-decisions.md`.

### Remaining

- All planned tasks and exit criteria are complete.

### Blockers

- None.

### Next Step

- Proceed to Phase 17 — Observability (`docs/roadmap/17-observability.md`).

---

## Completion Verification

- **Date:** 2026-09-25.
- **Acceptance Criteria Status (Exit Criteria):**
  - [x] _Performance baseline documented:_ the `large` profile is described in `docs/performance/README.md`, and the baseline matrix is recorded in `docs/performance/phase-16-baseline.md`.
  - [x] _Bottlenecks measured:_ before/after `EXPLAIN (ANALYZE, BUFFERS)` plans collected in `docs/performance/plans/` directories.
  - [x] _Optimization evidence-driven:_ the covering index eliminated sort disk spill (13.6 MB → 0 MB), `SALES-01` p95 improved from 1 947 ms to 370 ms (PASS), all 23 scenarios within budget.
  - [x] _Projection decision recorded:_ rejected with supporting metrics in `docs/performance/phase-16-optimization-report.md`.
  - [x] _Cache semantics clear:_ allowlist of 7 methods, monotonic version increments in `analytics_dataset_versions`, TTL 120–300 s, Fail-Open resilience, no pagination caching.
  - [x] _Correctness preserved:_ 100% payload parity between `disabled`, `cold`, and `warm` verified by automated tests.
- **Executed verification commands and results:**
  - `composer --working-dir=backend test -- --testsuite=Unit` — OK (219 tests, 31530 assertions)
  - `composer --working-dir=backend test -- --testsuite=Feature` — OK (126 tests, 1846 assertions)
  - `composer --working-dir=backend test -- --filter=Performance` — OK (38 tests, 3720 assertions)
  - `npm --prefix frontend test -- widget-data-loader.test.ts` — OK (12 tests passed)
  - `npm --prefix frontend test` — OK (48 test files, 217 tests passed)
  - `composer --working-dir=notification test` — OK (38 tests, 144 assertions, 4 skipped by driver check)
  - `npm --prefix frontend run contracts:validate` — OK (OpenAPI spec valid)
  - `npm --prefix frontend run lint && npm --prefix frontend run typecheck` — OK (0 errors)
  - `composer --working-dir=backend lint` — OK (0 errors)
