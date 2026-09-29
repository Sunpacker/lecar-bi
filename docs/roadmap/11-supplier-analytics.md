# Phase 11 — Supplier Analytics

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Add the third major analytics area to AutoBI — supplier and delivery analytics (Supplier Analytics).

## Functionality

- **Supplier Overview:** key metric cards (total deliveries, On-Time Delivery Rate, Fill Rate, defect rate, total purchase amount).
- **Trends & Status Breakdown:** delivery and reliability trends over time (by month), order status breakdown (on time, delayed, partial, canceled).
- **Supplier Performance Ranking:** supplier ranking and comparison by volume, timeliness, fulfillment rate, defect rate, average lead time, and composite reliability score (`SupplierReliabilityTier`).
- **Deliveries Journal:** detailed delivery journal with search, status and warehouse filters, server-side pagination, and sorting.
- **Filtering:** shared filters for date range, specific supplier, and destination warehouse.

## Exit Criteria

Metric definitions are explicit, calculations are backend-owned, interaction conventions are consistent, and tests cover calculations.

---

## Progress

### Completed

1. **Database migration and demo data generator:**
   - Developed the forward-safe migration `2026_09_23_000040_add_defect_quantity_to_fact_supplier_deliveries.php`, adding `defect_quantity INT DEFAULT 0` to `fact_supplier_deliveries`.
   - Updated the `DemoDatasetGenerator` seed generator to populate defect quantities and delivery statuses deterministically.
   - Covered by migration structure tests in `AnalyticsSchemaMigrationStructureTest`.

2. **OpenAPI 3.0.3 specification and TypeScript client generation:**
   - Designed 4 endpoints in `contracts/openapi/analytics-v1.yaml`:
     - `GET /analytics/suppliers/overview` — summary KPIs, trends, and status breakdown;
     - `GET /analytics/suppliers/filters` — filter options (suppliers, warehouses, statuses, date bounds);
     - `GET /analytics/suppliers/performance` — paginated supplier performance analytics;
     - `GET /analytics/suppliers/deliveries` — paginated delivery register with filters and sorting.
   - Validated the specification using Redocly CLI (`contracts:validate`).
   - Generated up-to-date types in `frontend/src/shared/api/generated/schema.ts`.
   - Covered by contract validation test `ApiContractTest`.

3. **Backend Domain Layer (`App\Modules\SupplierAnalytics\Domain`):**
   - Implemented the pure domain service `SupplierMetrics` (without dependencies on Laravel or external libraries):
     - On-Time Delivery Rate: `(on_time_deliveries / total_deliveries) * 100`;
     - Fill Rate (Delivery Fulfillment Rate): `(received_quantity / ordered_quantity) * 100`;
     - Defect Rate: `(defect_quantity / received_quantity) * 100`;
     - Average Lead Time (days between order and delivery);
     - Composite Reliability Score: `0.5 * OnTime + 0.4 * Fulfillment - 0.1 * Defect`.
   - Domain enums `DeliveryStatus` and `SupplierReliabilityTier` (`EXCELLENT`, `GOOD`, `ACCEPTABLE`, `POOR`).
   - Tests: 100% coverage in `SupplierMetricsTest`, boundary purity confirmed in `ArchitectureTest` (170 assertions).

4. **Backend Application Layer (`App\Modules\SupplierAnalytics\Application`):**
   - `SupplierAnalyticsReadModelInterface` contract.
   - 12 typed DTOs for criteria, overview, trends, performance, and deliveries.
   - CQRS queries and handlers:
     - `GetSupplierOverviewQuery` / `GetSupplierOverviewHandler`;
     - `GetSupplierFilterOptionsQuery` / `GetSupplierFilterOptionsHandler`;
     - `GetSupplierPerformanceQuery` / `GetSupplierPerformanceHandler`;
     - `GetSupplierDeliveriesQuery` / `GetSupplierDeliveriesHandler`.
   - Covered by tests in `SupplierAnalyticsApplicationTest`.

5. **Backend Infrastructure Layer (`App\Modules\SupplierAnalytics\Infrastructure`):**
   - `InMemorySupplierAnalyticsReadModel` for fast isolated tests.
   - `PostgresSupplierAnalyticsReadModel` for production analytics queries against `fact_supplier_deliveries`, `dim_suppliers`, and `dim_warehouses`, isolated by `workspace_id`.
   - Singleton registration in `AppServiceProvider`.
   - Covered by tests in `SupplierAnalyticsReadModelTest`.

6. **Backend Presentation Layer (`App\Modules\SupplierAnalytics\Presentation`):**
   - Input validation in `GetSupplierOverviewRequest`, `GetSupplierPerformanceRequest`, and `GetSupplierDeliveriesRequest`.
   - `SupplierAnalyticsController` returning typed JSON responses.
   - Routes registered in `backend/routes/api.php`, protected by `AuthenticateUserIdMiddleware`.
   - Covered by tests in `SupplierAnalyticsApiTest`.

7. **Frontend Supplier Analytics Feature (`frontend/src/features/supplier-analytics`):**
   - `supplierGateway` gateway with `getOverview`, `getFilters`, `getPerformance`, and `getDeliveries` methods and API error handling.
   - Visualization components:
     - `SupplierKpiCards` (5 metrics with currency and percentage formatting);
     - `SupplierTrendsChart` (combined Recharts chart: delivery bars + On-Time % and Fill Rate % lines);
     - `SupplierStatusBreakdown` (card with fulfillment status bars);
     - `SupplierFiltersBar` (quick period presets, supplier and warehouse selection, filter reset).
   - Table views:
     - `SupplierPerformanceTable` (sorting on every column, colored reliability rating badges, KPI indicators);
     - `SupplierDeliveriesTable` (search box, quick status filters, pagination, display of timing and quantity deviations).
   - Interactive `SupplierTabsContainer` dashboard with "Overview", "Supplier Performance", and "Deliveries Journal" tabs, loading indicators, and error handling.

8. **Page and navigation integration:**
   - Created the App Router page `frontend/app/(dashboard)/suppliers/page.tsx` with session checks, workspace context retrieval, and a Suspense skeleton.
   - Enabled the "Suppliers" section (`/suppliers`) in `frontend/src/shared/ui/layout/sidebar.tsx` (removed the `disabled` flag and "Coming soon" badge).
   - Added page unit tests in `page.test.tsx` and updated navigation tests in `sidebar.test.tsx`.

---

## Completion Verification

- **Completion date:** September 23, 2026.
- **Exit Criteria confirmation:**
  - `Metric definitions are explicit`: All formulas (On-Time Delivery, Fill Rate, Defect Rate, Lead Time, Reliability Score) are formalized in `SupplierMetrics` and the OpenAPI schema.
  - `Calculations backend-owned`: The frontend receives precomputed aggregates and percentages from the backend without performing business calculations in the UI.
  - `Interaction conventions are consistent`: The interface follows the project's unified design language (Tailwind, shadcn/ui, Recharts, KPI cards, responsive tables with pagination and sorting, quick filters).
  - `Tests cover calculations`: 100% coverage of domain calculations, queries, API endpoints, and UI components.

### Automated Check Results

1. **OpenAPI Contract:**
   ```bash
   npm --prefix frontend run contracts:validate
   # Result: 0 errors, OpenAPI 3.0.3 valid
   ```
2. **Frontend Linter and Type Checking:**
   ```bash
   npm --prefix frontend run lint
   # Result: 0 errors, 0 warnings
   npm --prefix frontend run typecheck
   # Result: 0 errors
   ```
3. **Frontend Test Suite:**
   ```bash
   npm --prefix frontend test
   # Result: 43 test files passed, 177 tests passed (100%)
   ```
4. **Frontend Production Build:**
   ```bash
   npm --prefix frontend run build
   # Result: Compiled successfully in Turbopack, route /suppliers (Dynamic) built
   ```
5. **Backend Linter:**
   ```bash
   composer --working-dir=backend lint
   # Result: [OK] No errors (Laravel Pint)
   ```
6. **Backend Test Suite:**
   ```bash
   composer --working-dir=backend test
   # Result: 218 tests, 28983 assertions passed (100%)
   ```
7. **DDD Architectural Constraints:**
   ```bash
   ./backend/vendor/bin/phpunit -c backend/phpunit.xml --filter ArchitectureTest
   # Result: 3 tests, 170 assertions passed (0 architectural violations)
   ```
