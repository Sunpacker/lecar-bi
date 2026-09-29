# Phase 7 — ABC/XYZ Analysis

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Add a recognizable inventory BI feature: combined ABC/XYZ analysis for assortment segmentation, working capital allocation optimization, and risk identification (dead stock, shortages of key items).

## Functionality

ABC, XYZ, combined matrix, period selection, product-level results, category/supplier filters, and criteria explanations in the UI.

If calculation becomes expensive, move to a projection/scheduled calculation.

## Exit Criteria

The algorithm is documented, boundary cases are covered, product-level results are available, the UI supports matrix exploration, and the calculation strategy is scalable.

---

## Progress

### Completed

1. **OpenAPI Contract (`contracts/openapi/analytics-v1.yaml`):**
   - Added `/analytics/inventory/abc-xyz/summary` and `/analytics/inventory/abc-xyz/items` endpoints.
   - Added `AbcXyzSummaryResponse`, `AbcXyzSummary`, `AbcXyzMatrixCell`, `AbcDistributionItem`, `XyzDistributionItem`, `AbcXyzItemsResponse`, and `AbcXyzProductItem` schemas.
   - Extended `InventoryFilterOptionsResponse` with `categories` and `suppliers` fields.
   - Generated the TypeScript client `frontend/src/shared/api/generated/schema.ts` and updated `ApiContractTest`.
2. **Domain Layer (`App\Modules\InventoryAnalytics\Domain`):**
   - Added Value Objects / Enums: `AbcClass` (A: $\le 80\%$, B: $80-95\%$, C: $>95\%$), `XyzClass` (X: $CV \le 15\%$, Y: $15-35\%$, Z: $>35\%$), `AbcXyzGroup` (AX...CZ with business names and inventory management recommendations).
   - Developed the pure domain calculator `AbcXyzCalculator`, implementing statistics ($CV$, sample standard deviation), Pareto sorting, and aggregation of the 9 matrix segments.
   - Wrote the isolated unit test `AbcXyzCalculatorTest` (7 tests, 66 assertions).
3. **Application Layer (`App\Modules\InventoryAnalytics\Application`):**
   - Added DTOs: `AbcXyzSummaryCriteriaDto`, `AbcXyzItemsCriteriaDto`, `AbcXyzMatrixCellDto`, `AbcDistributionDto`, `XyzDistributionDto`, `AbcXyzSummaryDto`, `AbcXyzProductItemDto`, `AbcXyzProductItemsPaginatedDto`.
   - Developed CQRS Queries and Handlers: `GetAbcXyzSummaryQuery`, `GetAbcXyzSummaryHandler`, `GetAbcXyzItemsQuery`, `GetAbcXyzItemsHandler`.
   - Added methods to `InventoryAnalyticsReadModelInterface` and implemented them in `InMemoryInventoryAnalyticsReadModel`, with `AbcXyzApplicationTest` unit tests.
4. **Infrastructure Layer (`PostgresInventoryAnalyticsReadModel`):**
   - Implemented high-performance PostgreSQL aggregation (`fetchRawAbcXyzProducts`) calculating sales by time interval, stock quantities, and inventory value.
   - Covered by `InventoryAnalyticsReadModelTest`.
5. **Presentation Layer (`App\Modules\InventoryAnalytics\Presentation`):**
   - Created Form Requests `GetAbcXyzSummaryRequest` and `GetAbcXyzItemsRequest`, validating periods ($30, 90, 180, 365$), classes, groups, and sorting.
   - Implemented controllers `InventoryAnalyticsController::abcXyzSummary` and `InventoryAnalyticsController::abcXyzItems`.
   - Registered routes in `routes/api.php`.
   - Added `InventoryAbcXyzApiTest` feature tests (7 tests, 292 assertions), checking workspace isolation and filtering.
6. **Frontend Gateway & UI (`frontend/src/features/inventory-analytics`):**
   - Extended `inventoryGateway` with `getAbcXyzSummary` and `getAbcXyzItems` methods.
   - Developed the interactive 3×3 `AbcXyzMatrixGrid`, with quick catalog filtering by clicking a cell.
   - Created the `AbcXyzMethodologyCard` methodology card with a collapsible explanation of formulas and strategies for 9 groups.
   - Created `AbcXyzFiltersBar` (period, warehouse, category, supplier, quick group selection, text search).
   - Created the `AbcXyzItemsTable` product table with pagination, class badges, and sorting by 8 columns.
   - Developed the root coordinator `AbcXyzView`, navigation tabs `InventoryTabsNav`, and `InventoryTabsContainer`.
   - Integrated tabs into `app/(dashboard)/inventory/page.tsx`.
   - Wrote component tests in `abc-xyz-components.test.tsx` (8 tests).
7. **Documentation and Verification:**
   - Created [docs/architecture/abc-xyz-methodology.md](../architecture/abc-xyz-methodology.md).
   - Updated [docs/architecture/06-data-and-analytics.md](../architecture/06-data-and-analytics.md).
   - Updated the `scripts/verify-integration.sh` integration verification script.

---

## Completion Verification

- **Completion date:** 2026-09-22
- **Status:** Complete (all exit criteria confirmed).

### Exit Criteria Confirmation

1. **Algorithm documented:** `docs/architecture/abc-xyz-methodology.md` describes the mathematical model in detail (revenue-based Pareto analysis, the sample standard deviation formula $s$ and $CV = (s/\bar{x})\times 100\%$, time series discretization) and strategies for all 9 AX...CZ groups. The link is included in `docs/architecture/06-data-and-analytics.md`.
2. **Boundary cases covered:** `AbcXyzCalculatorTest` covers edge cases: an empty catalog ($N=0$), products with no sales during the period, a single time bucket ($N=1$, avoiding division by $n-1$), and zero total revenue.
3. **Product-level results available:** Implemented `/api/v1/analytics/inventory/abc-xyz/items` with pagination, filtering, and sorting, and the `AbcXyzItemsTable` component.
4. **UI supports matrix exploration:** The interactive 3×3 grid in `AbcXyzMatrixGrid` visualizes revenue/stock shares and amounts; clicking any cell filters the catalog by the corresponding group.
5. **Calculation strategy scalable:** Heavy aggregation of sales time series and inventory balances runs in PostgreSQL; the calculation of variation coefficients and cumulative shares in PHP memory is optimized for an arbitrary number of products.

### Automated Check Results (`make check`)

- `npm --prefix frontend run contracts:validate`: OK (OpenAPI 3.0.3 valid)
- `npm --prefix frontend run format:check`: OK (Prettier)
- `npm --prefix frontend run lint`: OK (ESLint 0 errors)
- `npm --prefix frontend run typecheck`: OK (TypeScript 0 errors)
- `npm --prefix frontend test`: OK (17 test files, 60 tests passed)
- `npm --prefix frontend run build`: OK (Next.js 16 production build succeeded)
- `composer --working-dir=backend lint`: OK (Pint + PHPStan level max 0 errors)
- `composer --working-dir=backend test`: OK (90 tests, 27,864 assertions passed)
