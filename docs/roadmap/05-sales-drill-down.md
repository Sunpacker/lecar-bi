# Phase 5 — Sales Drill-Down and BI Interaction Model

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Turn the dashboard into an interactive BI tool.

## Functionality

Cross-filtering, drill-down, drill-through, URL-persisted filter state where justified, shared filter model, reset behavior, detail queries, pagination, sorting, clickable visualizations, and detail tables.

## Exit Criteria

Users can move from summary to detail, filter semantics are consistent, refresh preserves the analytical context, large datasets use backend pagination/sorting, and key flows have interaction tests.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before finishing the phase.

## Progress

- Finalized the OpenAPI 3.0.3 contract for detailed sales records: `GET /analytics/sales/records` endpoint, filtering parameters (`date_from`, `date_to`, `category_id`, `region_id`), server pagination (`page`, `per_page`), sorting (`sort_by`, `sort_direction`), and the `SalesRecordsResponse`, `SalesRecordItem`, and `PaginationMetadata` schemas in `contracts/openapi/analytics-v1.yaml`. Generated TypeScript types in `frontend/src/shared/api/generated/schema.ts`.
- Implemented backend application logic in the `SalesAnalytics` Bounded Context:
  - DTOs: `SalesRecordDto`, `SalesRecordsCriteriaDto`, `SalesRecordsPaginatedDto`;
  - Query and Handler: `GetSalesRecordsQuery`, `GetSalesRecordsHandler`;
  - Extended `SalesAnalyticsReadModelInterface` with `getSalesRecords(string $workspaceId, SalesRecordsCriteriaDto $criteria)`.
- Implemented data access infrastructure:
  - `PostgresSalesAnalyticsReadModel`: SQL query joining Star Schema tables (`fact_order_items`, `fact_orders`, `dim_products`, `dim_categories`, `dim_regions`, `dim_brands`), strict `workspace_id` isolation, dimension filtering, a safe sorting whitelist (`order_date`, `order_number`, `product_name`, `total_price`, `quantity`, `gross_profit`), and efficient `LIMIT`/`OFFSET` pagination with `COUNT(*) OVER()`;
  - `InMemorySalesAnalyticsReadModel` for isolated deterministic unit testing.
- Implemented the Presentation layer:
  - Input parameter validation in `GetSalesRecordsRequest`;
  - The `records` method in `SalesAnalyticsController`, with access checks through `WorkspaceAccessGuard` and authentication through `AuthenticateUserIdMiddleware`;
  - The `GET /analytics/sales/records` route in `backend/routes/api.php`.
- Implemented the frontend gateway and components in Next.js 15:
  - The `salesGateway.getRecords` gateway with automatic user and workspace headers;
  - Cross-filtering in visualizations: clickable category (`SalesCategoryBreakdownView`) and region (`SalesRegionalBreakdownView`) breakdowns, highlighting active items and individual filter reset buttons; interactive points on the sales trend chart (`SalesTrendChart`);
  - The `SalesDetailTable` component with server-side column sorting, server-side pagination, order status badges, currency formatting, and loading/empty data states;
  - Integration into `SalesDashboard` with a single filter state model, synchronization with URL query parameters (`useSearchParams` and `window.history.replaceState`), parallel summary and detail record loading, context preservation on page refresh (F5), and a button to reset all filters;
  - Wrapping `SalesDashboard` in `<Suspense>` on the `app/page.tsx` home page.
- Test suite:
  - Handler unit tests in `GetSalesRecordsHandlerTest`;
  - Read model infrastructure tests in `SalesAnalyticsReadModelRecordsTest`;
  - API feature tests in `SalesAnalyticsRecordsApiTest` (authorization, validation, pagination, sorting, filtering, tenant isolation);
  - Frontend gateway unit tests in `sales-gateway.test.ts`;
  - Component and integration interaction tests: `sales-components.test.tsx`, `sales-detail-table.test.tsx`, `sales-dashboard.test.tsx`.

## Completion Verification

Date: 2026-09-22.

Exit criteria fully confirmed: users move seamlessly from summary metrics to detailed order/item data, filter semantics are synchronized and consistent between visualizations and the table, page refresh (F5) preserves the analytical context from the URL, large data queries are handled efficiently by PostgreSQL server-side pagination and sorting, and key user scenarios are covered by interaction tests.

- `make check` — passed:
  - Redocly OpenAPI 3.0.3 validation (0 errors, 0 warnings);
  - openapi-typescript client type generation;
  - ESLint (0 errors, 0 warnings);
  - Prettier check (correct formatting);
  - TypeScript typecheck `tsc --noEmit` without errors;
  - Vitest: 26 tests (including unit, gateway, component, and dashboard interaction tests) passed;
  - Next.js production build (`npm run build`) compiled successfully;
  - Composer strict validation (`./composer.json is valid`);
  - Laravel Pint (formatting tests passed);
  - Larastan / PHPStan level max ([OK] No errors);
  - PHPUnit: 47 tests, 27 164 assertions passed.
- `make build` and `make infra-up` — backend and frontend images rebuilt and containers started in a healthy state.
- `make integration` (`scripts/verify-integration.sh`) successfully confirmed:
  1. Availability and validity of the detail records endpoint `/api/v1/analytics/sales/records?page=1&per_page=10&sort_by=total_price&sort_direction=desc`;
  2. Presence of pagination metadata (`pagination.total`, `pagination.page`, `pagination.per_page`, `pagination.total_pages`) and the `items` record array;
  3. Strict tenant isolation when accessing sales records (403 Forbidden when requesting records for another workspace_id);
  4. Correct rendering of the dashboard and sales detail table on the frontend;
  5. Integrity of the demo data generator in PostgreSQL (4 453 orders in ws-1, 4 455 orders in ws-2).
