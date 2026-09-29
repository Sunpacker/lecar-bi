# Phase 6 — Inventory Intelligence

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Add a second major BI area.

## Backend

Current inventory, stock quantity, average sales velocity, days of stock, critical stock, overstock, stock health, warehouse breakdown, product drill-down.

Inventory business rules belong to backend Domain/Application. Use read models as needed.

## Frontend

Inventory dashboard, critical stock, overstock, product details, warehouse/status filters.

## Exit Criteria

Calculations are tested, classifications are deterministic, the frontend does not duplicate rules, and queries are performant. Check against `docs/architecture/05-bounded-contexts.md` and `06-data-and-analytics.md`.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before finishing the phase.

## Progress

- Finalized the OpenAPI 3.0.3 contract for inventory analytics: `GET /analytics/inventory/summary`, `GET /analytics/inventory/items`, and `GET /analytics/inventory/filters` endpoints, and the `InventorySummaryResponse`, `InventorySummary`, `StockHealthBreakdownItem`, `WarehouseStockBreakdownItem`, `InventoryItemsResponse`, `InventoryItem`, and `InventoryFilterOptionsResponse` data schemas in `contracts/openapi/analytics-v1.yaml`.
- Generated current TypeScript API client types in `frontend/src/shared/api/generated/schema.ts`.
- Implemented the backend `InventoryAnalytics` Bounded Context:
  - **Domain:**
    - `StockHealthStatus`: enum with `out_of_stock`, `critical`, `optimal`, and `overstock` values;
    - `InventoryMetrics`: pure domain formulas for sales velocity (daily sales velocity over 30 days), days of stock, deterministic stock health classification using safety stock and reorder point, and share calculation; 100% covered by unit tests without external dependencies.
  - **Application:**
    - DTOs: `InventorySummaryDto`, `InventoryItemDto`, `WarehouseStockDto`, `StockHealthBreakdownDto`, `InventoryFilterOptionsDto`, criteria `InventorySummaryCriteriaDto` and `InventoryItemsCriteriaDto`, paginated result `InventoryItemsPaginatedDto`;
    - Queries & Handlers: `GetInventorySummaryQuery` / `GetInventorySummaryHandler`, `GetInventoryItemsQuery` / `GetInventoryItemsHandler`, `GetInventoryFilterOptionsQuery` / `GetInventoryFilterOptionsHandler`;
    - Read model contract: `InventoryAnalyticsReadModelInterface`.
  - **Infrastructure:**
    - `PostgresInventoryAnalyticsReadModel`: optimized SQL aggregations over the `fact_inventory_daily`, `fact_order_items`, `dim_products`, `dim_warehouses`, and `dim_categories` schema tables with strict `workspace_id` isolation, efficient sales velocity calculation over the last 30 days, and database-level aggregation;
    - `InMemoryInventoryAnalyticsReadModel`: deterministic in-memory implementation for isolated testing;
    - Interface binding registered in `AppServiceProvider`.
  - **Presentation:**
    - Requests: `GetInventorySummaryRequest`, `GetInventoryItemsRequest`, validating allowed sorts and status/warehouse filters;
    - `InventoryAnalyticsController`: `/analytics/inventory/summary`, `/analytics/inventory/items`, and `/analytics/inventory/filters` endpoints with access checks through `WorkspaceAccessGuard` and authentication through `AuthenticateUserIdMiddleware`;
    - Routes registered in `backend/routes/api.php`.
- Implemented the Next.js 15 frontend:
  - `inventoryGateway`: typed API request gateway with automatic user and workspace headers;
  - UI component set:
    - `InventoryKpiCards`: key metric cards (total items, available/reserved, total value, average DOS, critical shortage, out-of-stock);
    - `InventoryHealthBreakdown`: stock health composition visualization with differentiated colors and a distribution indicator;
    - `InventoryWarehouseBreakdown`: stock distribution across company warehouses;
    - `InventoryFiltersBar`: control bar with SKU/name search, warehouse and status selectors, and a reset button;
    - `InventoryItemsTable`: interactive product table with server-side column sorting (name, available quantity, value, sales velocity, days of stock), status badges, risk indicators, and pagination;
  - Inventory dashboard page `frontend/app/(dashboard)/inventory/page.tsx` and `InventoryDashboard` with URL persistence for filters and pagination;
  - Enabled the inventory management page link in the navigation menu (`Sidebar`).
- Automated test suite:
  - Backend: `InventoryMetricsTest` (Domain), `InventoryAnalyticsApplicationTest` (Application), `InventoryAnalyticsReadModelTest` (Infrastructure), `InventoryAnalyticsApiTest` (Feature/API), `ApiContractTest` (OpenAPI Contract);
  - Frontend: `inventory-gateway.test.ts`, `inventory-components.test.tsx`, `inventory-dashboard.test.tsx`.
- The `scripts/verify-integration.sh` integration verification script was extended with checks for filters, summary, product list, and cross-workspace inventory data isolation.

## Completion Verification

Date: 2026-09-22.

Exit criteria fully confirmed:

1. Calculations are covered by unit and integration tests (`InventoryMetricsTest`, `InventoryAnalyticsApplicationTest`).
2. Stock health classification is strictly deterministic on the backend.
3. The frontend does not duplicate business rules and displays metrics calculated by the backend.
4. Queries are performant and use PostgreSQL aggregates with `workspace_id` isolation.
5. Architecture boundaries are respected (`ArchitectureTest` passes; Domain is isolated from Laravel).
6. The full `make check` verification cycle was completed:
   - Redocly validation OpenAPI 3.0.3: OK;
   - Client API generation: OK;
   - Frontend ESLint, Prettier, TypeScript `tsc --noEmit`, Vitest (15 test files, 49 tests), Next.js production build: OK;
   - Backend Composer strict validation, Laravel Pint, Larastan / PHPStan level max, PHPUnit (71 tests, 27 460 assertions): OK.
