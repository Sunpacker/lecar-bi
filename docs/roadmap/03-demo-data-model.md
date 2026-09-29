# Phase 3 — Demo Data Model

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Create a reproducible automotive e-commerce dataset for analytics development without external ingestion.

## Data Areas

Products, categories, brands, warehouses, regions, sales channels, suppliers, orders, order items, inventory snapshots, and required delivery facts.

Create basic dimensions/facts for time, product, region, warehouse, sales, and inventory.

The dataset must demonstrate trends, seasonality, stockouts, overstock, and regional/category differences.

## Exit Criteria

Demo data is generated in a clean environment, analytics works without import, ownership is clear, the schema supports Sales/Inventory, and fixtures are reproducible. Check against `docs/architecture/06-data-and-analytics.md`.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before finishing the phase.

## Progress

- Star Schema migrations implemented (`backend/database/migrations`):
  - Dimensions: `dim_dates` (calendar attributes: year, quarter, month, week, day of week, season, weekend flag), `dim_categories`, `dim_brands`, `dim_regions`, `dim_warehouses`, `dim_sales_channels`, `dim_suppliers`, `dim_products`.
  - Facts: `fact_orders`, `fact_order_items` (including denormalized dimension keys, revenue, cost of goods, and gross profit), `fact_inventory_daily` (snapshots of stock, reservations, available quantity, safety stock, and reorder point), `fact_supplier_deliveries` (supplier orders, planned and actual dates, delays, quantities).
- All dimension and fact tables (except the universal `dim_dates` calendar) are strictly scoped to `workspace_id` with a `workspaces(id)` foreign key and `cascadeOnDelete()`, preventing data leakage between tenants.
- Created the `DemoDataCatalog` automotive product catalog with 8 categories, 10 brands, and 24 products with realistic prices, costs, ABC classes, and seasonal types.
- Developed the deterministic `DemoDatasetGenerator` with a fixed seed:
  - Annual growth trend (+15% YoY);
  - Pronounced seasonality (winter tires and antifreeze peak at 3-4.5 times their baseline in Q4, summer tires and cabin filters in Q2);
  - Regional characteristics (early winter season in Siberia and the Urals);
  - Stockout (`quantity_available <= 0`) and overstock (`days_of_stock > 120`) scenarios;
  - Delayed deliveries and incomplete shipments for future supplier analytics.
- Implemented `DemoDataSeeder`, called from `DatabaseSeeder`, and the `php artisan demo:seed [--seed=42]` console command.
- Phase 3 has no blockers or outstanding criteria.

## Completion Verification

Date: 2026-09-22.

Exit criteria fully confirmed: demo data is generated in a clean environment through `php artisan migrate --force --seed` and `demo:seed`, analytics functions without external import, ownership of every fact and dimension is clear (`workspace_id`), the schema fully supports Sales and Inventory analytics, and fixtures are reproducible. Compliance with `docs/architecture/06-data-and-analytics.md` confirmed.

- `make check` — Redocly OpenAPI validation, TypeScript generation, ESLint, Prettier, TypeScript typecheck, 9 Vitest tests, Next.js production build, Composer strict validation, Pint, PHPStan, and 26 PHPUnit tests (27 023 assertions) passed.
- `docker compose ... build backend` — independent backend image rebuilt successfully.
- `scripts/verify-integration.sh` confirmed in running PostgreSQL:
  1. Correct generation of 4 453 orders for `ws-1` and 4 455 orders for `ws-2`;
  2. Presence of 9 stockout days in inventory snapshots;
  3. Preservation of full workspace isolation;
  4. Working frontend and backend health endpoints.
- `git diff --check` — formatting and diff are correct.
