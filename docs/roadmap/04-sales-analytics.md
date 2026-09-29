# Phase 4 — Sales Analytics Vertical Slice

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

The first complete BI scenario from PostgreSQL to UI.

## Backend

Sales summary, revenue, order count, average order value, sales trend, category breakdown, regional breakdown, date/category/region filters.

## Frontend

Sales dashboard, KPI cards, time-series, category/region visualizations, filters, loading/empty/error states.

## Rules

Finalize OpenAPI before parallel frontend/backend implementation. Perform aggregation in the backend/database, not in the browser.

## Exit Criteria

The dashboard uses backend data, filters are synchronized, calculations are covered by tests, the API is contract-tested, and the frontend does not calculate business metrics. Check against `docs/architecture/03-frontend-nextjs.md`, `06-data-and-analytics.md`, and `07-api-and-integration.md`.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before finishing the phase.

## Progress

- Finalized the OpenAPI 3.0.3 contract for sales analytics in `contracts/openapi/analytics-v1.yaml` (`/analytics/sales/overview` and `/analytics/sales/filters`) and generated a typed TypeScript client.
- Implemented the `SalesAnalytics` Bounded Context in the Laravel DDD architecture:
  - **Domain Layer**: `DateRange` and `SalesMetrics` Value Objects (AOV, margin percentage, revenue share calculation), `InvalidDateRangeException`. Pure PHP without framework dependencies, verified by `ArchitectureTest`.
  - **Application Layer**: DTO models (`SalesOverviewDto`, `SalesSummaryDto`, `SalesTrendPointDto`, `SalesCategoryBreakdownDto`, `SalesRegionBreakdownDto`, `SalesFilterOptionsDto`, `SalesFilterCriteriaDto`), Queries and Handlers (`GetSalesOverviewQuery`/`Handler`, `GetSalesFilterOptionsQuery`/`Handler`), the `SalesAnalyticsReadModelInterface` contract.
  - **Infrastructure Layer**: High-performance `PostgresSalesAnalyticsReadModel` with SQL aggregation directly in the database over `fact_order_items`, `dim_categories`, and `dim_regions`, with mandatory `workspace_id` filtering. Implemented deterministic `InMemorySalesAnalyticsReadModel` for unit test isolation.
  - **Presentation Layer**: `SalesAnalyticsController`, FormRequest validation of filter parameters (`GetSalesOverviewRequest`), route registration in `routes/api.php` under `AuthenticateUserIdMiddleware`, and `workspace_id` access control through `WorkspaceAccessGuard`.
- Implemented the `features/sales-analytics` frontend module in Next.js 15:
  - The `salesGateway` client gateway based on `analyticsClient`;
  - KPI cards (`SalesKpiCards`): Revenue, Orders, Average Order Value, Margin;
  - SVG sales trend chart over time (`SalesTrendChart`);
  - Revenue breakdown visualizations by category (`SalesCategoryBreakdownView`) and region (`SalesRegionalBreakdownView`);
  - Synchronized filter bar (`SalesFiltersBar`) for dates, category, and region, with reset;
  - Interactive dashboard (`SalesDashboard`) with loading (skeleton), empty data (empty state), and error (with retry) states;
  - Dashboard integration into the application home page `app/page.tsx`, with workspace switching support.

## Completion Verification

Date: 2026-09-22.

Exit criteria fully confirmed: the dashboard uses real PostgreSQL data, filters are synchronized, calculations are tested, the API is checked against the OpenAPI contract, and the frontend does not calculate business metrics. Compliance with `docs/architecture/03-frontend-nextjs.md`, `06-data-and-analytics.md`, and `07-api-and-integration.md` confirmed.

- `make check` — Redocly OpenAPI validation, TypeScript generation, ESLint, Prettier, TypeScript typecheck, 17 Vitest tests, Next.js production build, Composer strict validation, Pint, PHPStan, and 40 PHPUnit tests (27 117 assertions) passed.
- `docker compose ... build` — backend and frontend images rebuilt successfully.
- `scripts/verify-integration.sh` confirmed in the running environment:
  1. Correct filter and date boundary responses from `/api/v1/analytics/sales/filters`;
  2. Correct aggregated sales data responses from `/api/v1/analytics/sales/overview` using PostgreSQL (4 453 orders for ws-1, revenue 138 367 270 ₽, gross profit 51 310 830 ₽, AOV 31 072.82 ₽);
  3. Preservation of full tenant isolation (an attempt by user-1 to access ws-2 returns 403 Forbidden);
  4. Working sales analytics dashboard and its rendering in the user interface.
- `git diff --check` — formatting and diff are correct.
