# ABC/XYZ Analysis Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the end-to-end ABC/XYZ Analysis vertical slice for inventory intelligence, providing deterministic Pareto revenue classification (ABC), demand variation analysis (XYZ), interactive 3x3 combined matrix (AX..CZ) with inventory value and revenue shares, period selection, category/supplier filters, and product-level drill-down data table with methodology explanations in the UI.

**Architecture:** CQRS-lite read model in Laravel DDD (`InventoryAnalytics` bounded context) using PostgreSQL aggregations over `fact_order_items`, `fact_inventory_daily`, and dimension tables, paired with an isolated, 100% unit-tested domain calculation service (`AbcXyzCalculator`). Frontend uses Next.js 15 (App Router) with typed OpenAPI client, shadcn/ui components, Tailwind CSS styling, responsive 3x3 matrix grid with interactive cell filtering, and product-level drill-down.

**Tech Stack:** Laravel 11 (PHP 8.3), PostgreSQL 16 (Star Schema: `fact_order_items`, `fact_orders`, `fact_inventory_daily`, `dim_products`, `dim_categories`, `dim_suppliers`, `dim_warehouses`), OpenAPI 3.0.3, Next.js 15 (React 19, TypeScript), Tailwind CSS, shadcn/ui, Vitest, PHPUnit 11.

**Spec:** `docs/roadmap/07-abc-xyz-analysis.md`

## Global Constraints

- Domain Layer in `App\Modules\InventoryAnalytics\Domain` MUST NOT depend on Laravel/Illuminate, framework helpers, or Infrastructure layers (`ArchitectureTest`).
- Database aggregations MUST execute in PostgreSQL; DO NOT load raw order item rows into PHP memory for manual aggregation (`docs/architecture/06-data-and-analytics.md`, ADR-014).
- Frontend MUST NOT calculate domain business metrics; all ABC/XYZ classifications, coefficients of variation ($CV$), shares, and matrix aggregations are computed by backend (`docs/architecture/03-frontend-nextjs.md`, ADR-013).
- OpenAPI specification in `contracts/openapi/analytics-v1.yaml` is the single source of truth; code must be generated via `npm --prefix frontend run api:generate` (`docs/architecture/07-api-and-integration.md`, ADR-007).
- Multi-tenancy isolation MUST be enforced on every query via `workspace_id` and verified against `WorkspaceAccessGuard`.
- All UI components MUST adhere to the mandatory stack: `shadcn/ui` + `Tailwind CSS` (ADR-016).

---

### Task 1: OpenAPI Contract for ABC/XYZ Analysis & TypeScript Client Generation

**Files:**
- Modify: `contracts/openapi/analytics-v1.yaml`
- Modify: `backend/tests/Feature/ApiContractTest.php`
- Generated: `frontend/src/shared/api/generated/schema.ts`

**Interfaces:**
- Consumes: Existing OpenAPI schema with `UserIdAuth`, `ErrorResponse`, `PaginationMetadata`
- Produces:
  - `GET /analytics/inventory/abc-xyz/summary`: query parameters `period_days` (30, 90, 180, 365), `warehouse_id`, `category_id`, `supplier_id`; schema `AbcXyzSummaryResponse` with matrix grid and distributions.
  - `GET /analytics/inventory/abc-xyz/items`: query parameters `period_days`, `warehouse_id`, `category_id`, `supplier_id`, `abc_class`, `xyz_class`, `group`, `search`, `page`, `per_page`, `sort_by`, `sort_direction`; schema `AbcXyzItemsResponse`.
  - Updated `GET /analytics/inventory/filters`: includes `categories` and `suppliers` arrays.

- [ ] **Step 1: Write the failing contract test**

Update `backend/tests/Feature/ApiContractTest.php` to assert that the OpenAPI contract contains ABC/XYZ endpoints and schemas:

```php
    public function test_contract_contains_abc_xyz_analysis_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/analytics/inventory/abc-xyz/summary', $contract['paths']);
        self::assertArrayHasKey('/analytics/inventory/abc-xyz/items', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('AbcXyzSummaryResponse', $schemas);
        self::assertArrayHasKey('AbcXyzSummary', $schemas);
        self::assertArrayHasKey('AbcXyzMatrixCell', $schemas);
        self::assertArrayHasKey('AbcDistributionItem', $schemas);
        self::assertArrayHasKey('XyzDistributionItem', $schemas);
        self::assertArrayHasKey('AbcXyzItemsResponse', $schemas);
        self::assertArrayHasKey('AbcXyzProductItem', $schemas);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_abc_xyz_analysis_endpoints`
Expected: FAIL with "Failed asserting that an array has key '/analytics/inventory/abc-xyz/summary'."

- [ ] **Step 3: Update OpenAPI specification and generate TypeScript types**

Add `/analytics/inventory/abc-xyz/summary` and `/analytics/inventory/abc-xyz/items` paths, schemas, and extend `InventoryFilterOptionsResponse` with `categories` and `suppliers` in `contracts/openapi/analytics-v1.yaml`.
Run validation and code generation:
```bash
npm --prefix frontend run contracts:validate
npm --prefix frontend run api:generate
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_abc_xyz_analysis_endpoints`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add contracts/openapi/analytics-v1.yaml backend/tests/Feature/ApiContractTest.php frontend/src/shared/api/generated/schema.ts
git commit -m "feat(contracts): define OpenAPI contract for ABC/XYZ analysis"
```

---

### Task 2: Domain Layer — AbcClass, XyzClass, AbcXyzGroup Enums & AbcXyzCalculator

**Files:**
- Create: `backend/app/Modules/InventoryAnalytics/Domain/AbcClass.php`
- Create: `backend/app/Modules/InventoryAnalytics/Domain/XyzClass.php`
- Create: `backend/app/Modules/InventoryAnalytics/Domain/AbcXyzGroup.php`
- Create: `backend/app/Modules/InventoryAnalytics/Domain/AbcXyzCalculator.php`
- Create: `backend/tests/Unit/Modules/InventoryAnalytics/Domain/AbcXyzCalculatorTest.php`

**Interfaces:**
- Consumes: Pure PHP math, array structures (no framework or DB imports)
- Produces:
  - `AbcClass`: enum `A`, `B`, `C` with `threshold()`, `label()`, `description()`.
  - `XyzClass`: enum `X`, `Y`, `Z` with `threshold()`, `label()`, `description()`.
  - `AbcXyzGroup`: enum `AX`, `AY`, `AZ`, `BX`, `BY`, `BZ`, `CX`, `CY`, `CZ` with `label()`, `recommendation()`, `badgeColor()`.
  - `AbcXyzCalculator`: static methods `calculateVariation(array $periodVolumes): ?float`, `classifyAbc(float $cumulativeShare): AbcClass`, `classifyXyz(?float $cv): XyzClass`, and `computeAnalysis(array $productRecords): array`.

- [ ] **Step 1: Write the failing unit tests for Domain models and calculation**

Create `backend/tests/Unit/Modules/InventoryAnalytics/Domain/AbcXyzCalculatorTest.php` testing:
- Calculation of standard deviation and coefficient of variation ($CV$).
- Handling of edge cases: 0 sales, constant sales (CV=0 -> X), single period, high fluctuation (CV > 0.35 -> Z).
- Pareto cumulative revenue sorting and A/B/C assignment (80% / 95% / 100%).
- Combined matrix grouping (e.g. A + X -> AX).
- Boundary cases: empty product list, all zero sales, products with inventory but zero sales.

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=AbcXyzCalculatorTest`
Expected: FAIL with "Class 'App\Modules\InventoryAnalytics\Domain\AbcXyzCalculator' not found"

- [ ] **Step 3: Implement Domain Enums and AbcXyzCalculator**

Implement:
- `backend/app/Modules/InventoryAnalytics/Domain/AbcClass.php`
- `backend/app/Modules/InventoryAnalytics/Domain/XyzClass.php`
- `backend/app/Modules/InventoryAnalytics/Domain/AbcXyzGroup.php`
- `backend/app/Modules/InventoryAnalytics/Domain/AbcXyzCalculator.php` (pure PHP, strict types, comprehensive boundary guards).

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=AbcXyzCalculatorTest`
Run: `composer --working-dir=backend test -- --filter=ArchitectureTest`
Expected: PASS with 0 architecture violations.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/InventoryAnalytics/Domain/ backend/tests/Unit/Modules/InventoryAnalytics/Domain/
git commit -m "feat(inventory): implement pure Domain ABC/XYZ classification and calculator"
```

---

### Task 3: Application Layer — DTOs, Queries, Handlers & Read Model Interface

**Files:**
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/AbcXyzSummaryDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/AbcXyzSummaryCriteriaDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/AbcXyzMatrixCellDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/AbcDistributionDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/XyzDistributionDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/AbcXyzProductItemDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/AbcXyzItemsCriteriaDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/AbcXyzProductItemsPaginatedDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Queries/GetAbcXyzSummaryQuery.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Queries/GetAbcXyzSummaryHandler.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Queries/GetAbcXyzItemsQuery.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Queries/GetAbcXyzItemsHandler.php`
- Modify: `backend/app/Modules/InventoryAnalytics/Application/Contracts/InventoryAnalyticsReadModelInterface.php`
- Modify: `backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/InMemoryInventoryAnalyticsReadModel.php`
- Create: `backend/tests/Unit/Modules/InventoryAnalytics/Application/AbcXyzApplicationTest.php`

**Interfaces:**
- Consumes: Domain Enums & Calculator
- Produces: Application queries, handlers, DTOs, and extended read model contract.

- [ ] **Step 1: Write the failing application test**

Create `backend/tests/Unit/Modules/InventoryAnalytics/Application/AbcXyzApplicationTest.php` testing `GetAbcXyzSummaryHandler` and `GetAbcXyzItemsHandler` with `InMemoryInventoryAnalyticsReadModel`.

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=AbcXyzApplicationTest`
Expected: FAIL with interface and handler not found.

- [ ] **Step 3: Implement Application DTOs, Queries, Handlers, and In-Memory Read Model**

- Define read model methods in `InventoryAnalyticsReadModelInterface`:
  - `getAbcXyzSummary(string $workspaceId, AbcXyzSummaryCriteriaDto $criteria): AbcXyzSummaryDto`
  - `getAbcXyzItems(string $workspaceId, AbcXyzItemsCriteriaDto $criteria): AbcXyzProductItemsPaginatedDto`
- Implement DTOs, queries, and handlers.
- Implement mock data in `InMemoryInventoryAnalyticsReadModel`.

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=AbcXyzApplicationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/InventoryAnalytics/Application/ backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/InMemoryInventoryAnalyticsReadModel.php backend/tests/Unit/Modules/InventoryAnalytics/Application/
git commit -m "feat(inventory): implement ABC/XYZ Application DTOs, queries, and handlers"
```

---

### Task 4: Infrastructure Layer — PostgreSQL Read Model Implementation & DB Optimization

**Files:**
- Modify: `backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/PostgresInventoryAnalyticsReadModel.php`
- Modify: `backend/tests/Unit/Modules/InventoryAnalytics/Infrastructure/InventoryAnalyticsReadModelTest.php`

**Interfaces:**
- Consumes: Database connection, tables `fact_order_items`, `fact_orders`, `fact_inventory_daily`, `dim_products`, `dim_categories`, `dim_suppliers`, `dim_warehouses`
- Produces: Efficient SQL queries calculating sales totals, periodic monthly bucket volumes, current stock values, and applying `AbcXyzCalculator` to return `AbcXyzSummaryDto` and `AbcXyzProductItemsPaginatedDto`.

- [ ] **Step 1: Write the failing infrastructure integration test**

Add tests to `backend/tests/Unit/Modules/InventoryAnalytics/Infrastructure/InventoryAnalyticsReadModelTest.php` verifying:
- `getAbcXyzSummary` returns 9 matrix cells, ABC and XYZ distributions, and accurate aggregate revenue and stock value.
- `getAbcXyzItems` filters by category, supplier, warehouse, ABC class, XYZ class, combined group, search keyword, and sorts properly with pagination.
- `getFilterOptions` includes available categories and suppliers.

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=InventoryAnalyticsReadModelTest`
Expected: FAIL with method not implemented.

- [ ] **Step 3: Implement PostgreSQL queries in PostgresInventoryAnalyticsReadModel**

Implement:
- Date window resolution based on `period_days` (30, 90, 180, 365 days).
- Aggregation query: Completed orders sales per product in period, broken down by monthly intervals.
- Inventory snapshot query: Latest snapshot available stock and inventory value.
- Supplier and category linking.
- Delegation of classification to `AbcXyzCalculator`.
- Filter options expansion: fetch categories and suppliers belonging to `workspace_id`.

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=InventoryAnalyticsReadModelTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/PostgresInventoryAnalyticsReadModel.php backend/tests/Unit/Modules/InventoryAnalytics/Infrastructure/InventoryAnalyticsReadModelTest.php
git commit -m "feat(inventory): implement PostgreSQL read model for ABC/XYZ analysis"
```

---

### Task 5: Presentation Layer — HTTP Requests, Controller Endpoints & API Route Registration

**Files:**
- Create: `backend/app/Modules/InventoryAnalytics/Presentation/Requests/GetAbcXyzSummaryRequest.php`
- Create: `backend/app/Modules/InventoryAnalytics/Presentation/Requests/GetAbcXyzItemsRequest.php`
- Modify: `backend/app/Modules/InventoryAnalytics/Presentation/Controllers/InventoryAnalyticsController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Modules/InventoryAnalytics/InventoryAbcXyzApiTest.php`

**Interfaces:**
- Consumes: HTTP GET requests with headers `X-User-Id`, `X-Workspace-Id`
- Produces: JSON responses according to OpenAPI contract schemas `AbcXyzSummaryResponse` and `AbcXyzItemsResponse`.

- [ ] **Step 1: Write the failing API feature tests**

Create `backend/tests/Feature/Modules/InventoryAnalytics/InventoryAbcXyzApiTest.php` testing:
- Unauthenticated access returns 401.
- Unauthorized workspace access returns 403.
- `GET /api/v1/analytics/inventory/abc-xyz/summary` returns 200 with matrix structure.
- `GET /api/v1/analytics/inventory/abc-xyz/items` returns 200 with paginated product items.
- Validation: invalid `period_days` (e.g. 500) returns 422.
- Multi-tenancy isolation: workspace A cannot see workspace B products or sales.

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=InventoryAbcXyzApiTest`
Expected: FAIL with 404 Route Not Found.

- [ ] **Step 3: Implement HTTP requests, controller methods, and route registration**

Implement `GetAbcXyzSummaryRequest`, `GetAbcXyzItemsRequest`, add controller actions `abcXyzSummary` and `abcXyzItems` to `InventoryAnalyticsController`, and register routes in `backend/routes/api.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=InventoryAbcXyzApiTest`
Run: `composer --working-dir=backend test` (all 71+ tests pass)
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/InventoryAnalytics/Presentation/ backend/routes/api.php backend/tests/Feature/Modules/InventoryAnalytics/InventoryAbcXyzApiTest.php
git commit -m "feat(inventory): add HTTP API endpoints and validation for ABC/XYZ analysis"
```

---

### Task 6: Frontend API Gateway & Typed Methods

**Files:**
- Modify: `frontend/src/features/inventory-analytics/api/inventory-gateway.ts`
- Modify: `frontend/src/features/inventory-analytics/api/inventory-gateway.test.ts`

**Interfaces:**
- Consumes: OpenAPI generated client and types in `frontend/src/shared/api/generated/schema.ts`
- Produces: `inventoryGateway.getAbcXyzSummary(userId, workspaceId, params)` and `inventoryGateway.getAbcXyzItems(userId, workspaceId, params)`.

- [ ] **Step 1: Write the failing gateway test**

Update `frontend/src/features/inventory-analytics/api/inventory-gateway.test.ts` to test `getAbcXyzSummary` and `getAbcXyzItems`.

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- src/features/inventory-analytics/api/inventory-gateway.test.ts`
Expected: FAIL with "inventoryGateway.getAbcXyzSummary is not a function"

- [ ] **Step 3: Implement gateway methods in inventory-gateway.ts**

Extend `inventoryGateway` with typed methods for fetching ABC/XYZ summary and items, including query param serialization.

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- src/features/inventory-analytics/api/inventory-gateway.test.ts`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/inventory-analytics/api/inventory-gateway.ts frontend/src/features/inventory-analytics/api/inventory-gateway.test.ts
git commit -m "feat(frontend): extend inventoryGateway with ABC/XYZ analysis methods"
```

---

### Task 7: Frontend UI Components — Matrix Grid, Explanation Guide, Filters & Table

**Files:**
- Create: `frontend/src/features/inventory-analytics/ui/abc-xyz-matrix-grid.tsx`
- Create: `frontend/src/features/inventory-analytics/ui/abc-xyz-methodology-card.tsx`
- Create: `frontend/src/features/inventory-analytics/ui/abc-xyz-filters-bar.tsx`
- Create: `frontend/src/features/inventory-analytics/ui/abc-xyz-items-table.tsx`
- Create: `frontend/src/features/inventory-analytics/ui/abc-xyz-view.tsx`
- Create: `frontend/src/features/inventory-analytics/ui/abc-xyz-components.test.tsx`

**Interfaces:**
- Consumes: `inventoryGateway`, shadcn/ui components (`Card`, `Badge`, `Button`, `Table`, `Select`, `Input`, `Tooltip`, `Dialog`/`Accordion`), Tailwind CSS
- Produces:
  - `AbcXyzMatrixGrid`: 3x3 interactive matrix with cell stats and click-to-filter.
  - `AbcXyzMethodologyCard`: informative criteria guide explaining ABC (80/15/5), XYZ ($CV \le 15\%$, $15-35\%$, $>35\%$), and management strategies for all 9 groups.
  - `AbcXyzFiltersBar`: period picker (30, 90, 180, 365 days), category, supplier, warehouse dropdowns, search input, reset.
  - `AbcXyzItemsTable`: product-level drilldown with sorting, pagination, and visual badges.
  - `AbcXyzView`: composite view coordinating state, filters, URL params, and data loading.

- [ ] **Step 1: Write the failing UI component tests**

Create `frontend/src/features/inventory-analytics/ui/abc-xyz-components.test.tsx` testing matrix cell rendering, badge styling, table rendering, filter interactions, and methodology card toggle.

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- src/features/inventory-analytics/ui/abc-xyz-components.test.tsx`
Expected: FAIL with module not found.

- [ ] **Step 3: Implement ABC/XYZ UI components**

Implement:
- `abc-xyz-matrix-grid.tsx`: Clean 3x3 layout with columns X, Y, Z and rows A, B, C; hover effects, active selected cell border, percentage of inventory value and revenue displayed clearly.
- `abc-xyz-methodology-card.tsx`: Tabbed or collapsible criteria explanations with clear automotive examples and inventory management recommendations.
- `abc-xyz-filters-bar.tsx`: Responsive filter bar using shadcn/ui components.
- `abc-xyz-items-table.tsx`: Data table with columns SKU, Name, Category, Supplier, Revenue, Revenue Share %, ABC, $CV$, XYZ, Group Badge, Stock, Inventory Value.
- `abc-xyz-view.tsx`: Main view with URL parameter synchronization.

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- src/features/inventory-analytics/ui/abc-xyz-components.test.tsx`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/inventory-analytics/ui/
git commit -m "feat(frontend): implement ABC/XYZ interactive matrix grid, methodology guide, and data table"
```

---

### Task 8: Page Integration — Inventory Navigation Tabs & Route Integration

**Files:**
- Modify: `frontend/app/(dashboard)/inventory/page.tsx`
- Create: `frontend/src/features/inventory-analytics/ui/inventory-tabs-nav.tsx`
- Modify: `frontend/src/features/inventory-analytics/ui/inventory-dashboard.test.tsx`

**Interfaces:**
- Consumes: Next.js App Router query params (`tab=overview` vs `tab=abc-xyz`)
- Produces: Seamless switching between "Обзор остатков" (Stock health, DOS, velocity) and "ABC/XYZ Анализ" (Matrix, revenue & variation Pareto, product drilldown).

- [ ] **Step 1: Write the failing dashboard test**

Update `frontend/src/features/inventory-analytics/ui/inventory-dashboard.test.tsx` to verify tab switching between Inventory Intelligence Overview and ABC/XYZ Analysis.

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- src/features/inventory-analytics/ui/inventory-dashboard.test.tsx`
Expected: FAIL with tab component missing.

- [ ] **Step 3: Implement tab navigation and page integration**

Implement `InventoryTabsNav` and integrate into `app/(dashboard)/inventory/page.tsx` so users can seamlessly switch tabs while maintaining workspace context.

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- src/features/inventory-analytics/ui/inventory-dashboard.test.tsx`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add frontend/app/(dashboard)/inventory/page.tsx frontend/src/features/inventory-analytics/ui/
git commit -m "feat(inventory): add tabbed navigation between stock health overview and ABC/XYZ analysis"
```

---

### Task 9: Algorithm Documentation, Integration Verification & Roadmap Update

**Files:**
- Create: `docs/architecture/abc-xyz-methodology.md`
- Modify: `docs/architecture/06-data-and-analytics.md`
- Modify: `scripts/verify-integration.sh`
- Modify: `docs/roadmap/07-abc-xyz-analysis.md`

**Interfaces:**
- Consumes: System architecture documentation, bash verification script, roadmap tracking
- Produces: Complete algorithm documentation with formulas and boundary case descriptions, updated integration script, and documented progress in Phase 7.

- [ ] **Step 1: Write algorithm documentation**

Create `docs/architecture/abc-xyz-methodology.md` documenting:
- Mathematical definition of ABC Pareto analysis (80/15/5 revenue boundaries).
- Mathematical definition of XYZ demand variation coefficient ($CV = \sigma / \mu$, intervals, thresholds: $X \le 15\%$, $15\% < Y \le 35\%$, $Z > 35\%$).
- Complete boundary case specification (zero sales, single bucket, zero total workspace revenue, tie-breaking).
- 9-cell matrix strategic inventory recommendations.
- Reference this document in `docs/architecture/06-data-and-analytics.md`.

- [ ] **Step 2: Update verification script**

Update `scripts/verify-integration.sh` with curl checks against:
- `GET /api/v1/analytics/inventory/abc-xyz/summary?period_days=90`
- `GET /api/v1/analytics/inventory/abc-xyz/items?period_days=90&per_page=5`
- Cross-workspace isolation check for ABC/XYZ data.

- [ ] **Step 3: Run full verification suite**

Run:
```bash
make check
```
Verify that contracts, frontend, and backend checks all pass with zero warnings and zero failures.

- [ ] **Step 4: Update roadmap document**

Update `docs/roadmap/07-abc-xyz-analysis.md` with:
- Summary of implemented components across Domain, Application, Infrastructure, Presentation, and Next.js Frontend.
- Confirmation of Exit Criteria fulfillment.

- [ ] **Step 5: Commit**

```bash
git add docs/architecture/ scripts/verify-integration.sh docs/roadmap/07-abc-xyz-analysis.md
git commit -m "docs(inventory): document ABC/XYZ algorithm methodology and update roadmap"
```

---

## Plan Self-Review Checklist

1. **Spec Coverage:**
   - ABC analysis: Covered (Task 1, 2, 3, 4, 7).
   - XYZ analysis: Covered (Task 1, 2, 3, 4, 7).
   - Combined matrix (9 cells AX..CZ): Covered (Task 1, 2, 3, 4, 7).
   - Period selection: Covered (Task 1, 3, 4, 5, 7).
   - Product-level results: Covered (Task 1, 3, 4, 5, 7).
   - Category / Supplier filters: Covered (Task 1, 3, 4, 5, 7).
   - UI explanation of criteria: Covered (Task 7: `AbcXyzMethodologyCard`).
   - Scalable calculation strategy: Covered (PostgreSQL monthly aggregations + Domain calculator).
   - Exit criteria: Algorithm documented, boundary cases covered, product-level results, interactive UI matrix, scalable strategy.

2. **Placeholder Scan:**
   - No "TODO", "TBD", "implement later", or vague placeholders.
   - All files have exact paths, specific test cases, and clear step-by-step commands.

3. **Type and Interface Consistency:**
   - Consistent naming across tasks: `AbcClass`, `XyzClass`, `AbcXyzGroup`, `AbcXyzCalculator`, `AbcXyzSummaryDto`, `AbcXyzProductItemDto`, `inventoryGateway.getAbcXyzSummary`, `AbcXyzMatrixGrid`, `AbcXyzView`.
