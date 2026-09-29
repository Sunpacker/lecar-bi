# 06. Data and Analytics Architecture

## General Approach

AutoBI is a read-heavy system.

The architecture must be optimized not only for transactional use cases but also for a large number of aggregated analytical queries.

## Model Separation

Distinguish between:

- the domain model;
- the storage model;
- the analytical model;
- read models;
- API representation.

There is no requirement to make all these layers use the same data structure.

## PostgreSQL

PostgreSQL is the analytics service's primary persistent store.

It stores:

- settings;
- user dashboards;
- alert rules;
- imported data;
- staging data;
- analytical facts;
- dimensions;
- technical tables.

## Logical Data Separation

The following should be logically separated:

- core data;
- staging;
- analytics.

This makes the data lifecycle easier to understand.

## Star Schema

Analytical datasets may use elements of a Star Schema.

The main idea:

- facts store measurable events;
- dimensions describe context;
- analytical queries are built around aggregating facts by dimensions.

The approach is especially useful for:

- sales;
- inventory snapshots;
- deliveries;
- time-based breakdowns;
- regions;
- products;
- suppliers;
- warehouses.

## Read Models

Specialized read models must be created for complex dashboards.

They may use:

- optimized SQL queries;
- aggregate tables;
- materialized views;
- precomputed metrics;
- caching.

A read model does not have to be a domain entity.

## CQRS-lite

The project uses lightweight command/query separation.

Commands change system state.

Queries retrieve data and may use specialized analytical models.

Separate physical databases for the command and query sides are not required at an early stage.

## Performance and Selective Caching

Efficient execution in the DBMS is the priority when building analytical queries.

Do not load large volumes of analytical data into PHP memory merely to aggregate them afterward.

Phase 16 introduced an engineering performance contract:

1. **Covering and composite B-tree indexes in PostgreSQL:**
   - The `idx_foi_ws_order_covering` index on `fact_order_items (workspace_id, order_id) INCLUDE (total_price, gross_profit)` enabled `Index Only Scan` for key sales aggregates (`SALES-01`, dashboards `DASH-01`, `DASH-02`) without sort spills to disk (`Temp Read/Written: 0`), reducing p95 from 1,947 ms to 370 ms on 100k+ orders.
   - Composite indexes for filtering by dates, categories, regions, and stock levels brought all 23 scenarios within the defined budgets without external analytics stores.
2. **Evidence-based decision on materialized views / projections:**
   - Based on measurements using the reference `large` profile (100k orders, 300k line items, 500k stock records), dedicated projection tables were **rejected**: all scenarios met their budgets (p95 ≤ 1,000 ms for summaries, ≤ 1,500 ms for lists) using SQL and indexes alone.
   - This preserved architectural simplicity, eliminated write amplification during batch imports, and prevented data divergence.
3. **Selective Versioned Cache (Redis):**
   - Only deterministic, low-cardinality aggregates and filter option lists are cached (an allowlist of 7 methods).
   - Search queries and paginated record lists are strictly excluded from caching.
   - The cache resides strictly within Read Model infrastructure decorators, behind the workspace authorization and validation boundary.
   - Invalidation uses monotonic dataset versions in the `analytics_dataset_versions` table. Importing new facts increments the version; old keys become unreachable and expire by TTL (120–300 s), without blocking `KEYS *` or `Cache::flush()` operations.
   - Fail-Open semantics: a Redis failure gracefully falls back to the latency of a direct PostgreSQL query without a user-facing 500 error.

## Assortment Analytics and Segmentation (ABC/XYZ)

Combined ABC/XYZ analysis is used to segment the assortment and optimize inventory composition:

- **ABC analysis** classifies items by their contribution to total revenue based on the Pareto principle (A: 80%, B: 15%, C: 5%).
- **XYZ analysis** classifies items by demand stability using the sample coefficient of variation $CV = \frac{s}{\bar{x}} \times 100\%$ (X: $\le 15\%$, Y: $15\% - 35\%$, Z: $> 35\%$).
- The $3 \times 3$ matrix combines 9 groups (AX...CZ) and defines differentiated policies for replenishment, safety stock sizing, and reducing dead stock risk.

The detailed mathematical model, thresholds, and business strategies are described in the [Combined ABC/XYZ Analysis Methodology](abc-xyz-methodology.md).

## Database Ownership

The analytics service owns its database.

Future microservices must not access this database directly.

If another service needs data, it is delivered through APIs or events.
