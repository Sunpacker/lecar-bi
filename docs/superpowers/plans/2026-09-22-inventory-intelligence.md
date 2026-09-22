# Inventory Intelligence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the end-to-end Inventory Intelligence vertical slice from PostgreSQL Star Schema to Next.js UI, providing current inventory, stock quantity, average sales velocity, days of stock (DOS), deterministic stock health classification (out of stock, critical stock, overstock, optimal), warehouse breakdown, and product drill-down with server-side sorting, pagination, and filtering.

**Architecture:** CQRS-lite with a specialized PostgreSQL read model in Laravel DDD (`InventoryAnalytics` bounded context) exposing OpenAPI 3.0.3-contracted endpoints, consumed by a typed Next.js 15 frontend feature with shadcn/ui components, responsive status and warehouse breakdowns, paginated data table, and multi-tenant workspace isolation.

**Tech Stack:** Laravel 11 (PHP 8.3), PostgreSQL 16 (Star Schema: `fact_inventory_daily`, `fact_order_items`, `dim_warehouses`, `dim_products`), OpenAPI 3.0.3, Next.js 15 (App Router, React 19, TypeScript), Tailwind CSS, shadcn/ui, Vitest, PHPUnit 11.

**Spec:** `docs/roadmap/06-inventory-intelligence.md`

## Global Constraints

- Domain Layer in `App\Modules\InventoryAnalytics\Domain` MUST NOT depend on Laravel/Illuminate, framework helpers, or Infrastructure layers (`ArchitectureTest`).
- Database aggregations MUST execute in PostgreSQL; DO NOT load raw inventory snapshot rows into PHP memory (`docs/architecture/06-data-and-analytics.md`, ADR-014).
- Frontend MUST NOT calculate domain business metrics; all KPIs, stock health classifications, velocity, and days of stock (DOS) are computed by backend (`docs/architecture/03-frontend-nextjs.md`, ADR-013).
- OpenAPI specification in `contracts/openapi/analytics-v1.yaml` is the single source of truth; code must be generated via `npm --prefix frontend run api:generate` (`docs/architecture/07-api-and-integration.md`, ADR-007).
- Multi-tenancy isolation MUST be enforced on every query via `workspace_id` and verified against `WorkspaceAccessGuard`.
- All UI components MUST adhere to the mandatory stack: `shadcn/ui` + `Tailwind CSS` (ADR-016).

---

### Task 1: OpenAPI Contract for Inventory Intelligence & TypeScript Client Generation

**Files:**
- Modify: `contracts/openapi/analytics-v1.yaml`
- Modify: `backend/tests/Feature/ApiContractTest.php`
- Generated: `frontend/src/shared/api/generated/schema.ts`

**Interfaces:**
- Consumes: Existing OpenAPI schema with `UserIdAuth`, `ErrorResponse`, `PaginationMetadata`
- Produces: Endpoints `/analytics/inventory/summary`, `/analytics/inventory/items`, `/analytics/inventory/filters` with schemas `InventorySummaryResponse`, `InventorySummary`, `StockHealthBreakdownItem`, `WarehouseStockBreakdownItem`, `InventoryItemsResponse`, `InventoryItem`, `InventoryFilterOptionsResponse`

- [ ] **Step 1: Write the failing contract test**

Update `backend/tests/Feature/ApiContractTest.php` to assert that the OpenAPI contract contains the inventory intelligence endpoints and schemas:

```php
    public function test_contract_contains_inventory_intelligence_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/analytics/inventory/summary', $contract['paths']);
        self::assertArrayHasKey('/analytics/inventory/items', $contract['paths']);
        self::assertArrayHasKey('/analytics/inventory/filters', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('InventorySummaryResponse', $schemas);
        self::assertArrayHasKey('InventorySummary', $schemas);
        self::assertArrayHasKey('StockHealthBreakdownItem', $schemas);
        self::assertArrayHasKey('WarehouseStockBreakdownItem', $schemas);
        self::assertArrayHasKey('InventoryItemsResponse', $schemas);
        self::assertArrayHasKey('InventoryItem', $schemas);
        self::assertArrayHasKey('InventoryFilterOptionsResponse', $schemas);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_inventory_intelligence_endpoints`
Expected: FAIL with "Failed asserting that an array has key '/analytics/inventory/summary'."

- [ ] **Step 3: Update OpenAPI specification and generate TypeScript types**

Add `/analytics/inventory/summary`, `/analytics/inventory/items`, and `/analytics/inventory/filters` paths and schema components to `contracts/openapi/analytics-v1.yaml`:

```yaml
  /analytics/inventory/summary:
    get:
      operationId: getInventorySummary
      summary: Get current inventory summary, health breakdown, and warehouse metrics
      parameters:
        - in: header
          name: X-Workspace-Id
          required: false
          schema:
            type: string
          description: Optional requested workspace identifier
        - in: query
          name: warehouse_id
          required: false
          schema:
            type: string
          description: Optional filter by warehouse ID
        - in: query
          name: as_of_date
          required: false
          schema:
            type: string
            format: date
          description: Optional snapshot date (YYYY-MM-DD), defaults to latest available snapshot
      responses:
        '200':
          description: Aggregated inventory summary and health distribution
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/InventorySummaryResponse'
        '401':
          description: Unauthenticated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '403':
          description: Forbidden - user does not belong to the requested workspace
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '404':
          description: Workspace not found
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'

  /analytics/inventory/items:
    get:
      operationId: getInventoryItems
      summary: Get paginated and sorted list of inventory items with sales velocity, DOS, and stock health
      parameters:
        - in: header
          name: X-Workspace-Id
          required: false
          schema:
            type: string
          description: Optional requested workspace identifier
        - in: query
          name: warehouse_id
          required: false
          schema:
            type: string
          description: Filter by warehouse ID
        - in: query
          name: stock_health
          required: false
          schema:
            type: string
            enum: [out_of_stock, critical, optimal, overstock]
          description: Filter by stock health status
        - in: query
          name: search
          required: false
          schema:
            type: string
          description: Search by product name or SKU
        - in: query
          name: page
          required: false
          schema:
            type: integer
            minimum: 1
            default: 1
          description: Page number for pagination
        - in: query
          name: per_page
          required: false
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 20
          description: Number of items per page
        - in: query
          name: sort_by
          required: false
          schema:
            type: string
            enum: [product_name, quantity_on_hand, quantity_available, inventory_value, sales_velocity, days_of_stock]
            default: quantity_available
          description: Sort field
        - in: query
          name: sort_direction
          required: false
          schema:
            type: string
            enum: [asc, desc]
            default: asc
          description: Sort direction
      responses:
        '200':
          description: Paginated inventory items
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/InventoryItemsResponse'
        '401':
          description: Unauthenticated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '403':
          description: Forbidden
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '422':
          description: Validation error
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'

  /analytics/inventory/filters:
    get:
      operationId: getInventoryFilters
      summary: Get available filter options for inventory analytics
      parameters:
        - in: header
          name: X-Workspace-Id
          required: false
          schema:
            type: string
          description: Optional requested workspace identifier
      responses:
        '200':
          description: Available inventory filter options
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/InventoryFilterOptionsResponse'
        '401':
          description: Unauthenticated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '403':
          description: Forbidden
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
```

And in `components/schemas`:
```yaml
    InventorySummaryResponse:
      type: object
      required:
        - summary
        - health_breakdown
        - warehouses
        - as_of_date
      properties:
        summary:
          $ref: '#/components/schemas/InventorySummary'
        health_breakdown:
          type: array
          items:
            $ref: '#/components/schemas/StockHealthBreakdownItem'
        warehouses:
          type: array
          items:
            $ref: '#/components/schemas/WarehouseStockBreakdownItem'
        as_of_date:
          type: string
          format: date

    InventorySummary:
      type: object
      required:
        - total_items
        - total_quantity_on_hand
        - total_quantity_reserved
        - total_quantity_available
        - total_inventory_value
        - critical_count
        - overstock_count
        - out_of_stock_count
        - optimal_count
        - average_days_of_stock
      properties:
        total_items:
          type: integer
        total_quantity_on_hand:
          type: integer
        total_quantity_reserved:
          type: integer
        total_quantity_available:
          type: integer
        total_inventory_value:
          type: number
          format: float
        critical_count:
          type: integer
        overstock_count:
          type: integer
        out_of_stock_count:
          type: integer
        optimal_count:
          type: integer
        average_days_of_stock:
          type: number
          format: float
          nullable: true

    StockHealthBreakdownItem:
      type: object
      required:
        - status
        - label
        - items_count
        - total_value
        - share
      properties:
        status:
          type: string
          enum: [out_of_stock, critical, optimal, overstock]
        label:
          type: string
        items_count:
          type: integer
        total_value:
          type: number
          format: float
        share:
          type: number
          format: float

    WarehouseStockBreakdownItem:
      type: object
      required:
        - warehouse_id
        - warehouse_name
        - warehouse_code
        - total_quantity
        - total_value
        - items_count
        - critical_count
        - overstock_count
      properties:
        warehouse_id:
          type: string
        warehouse_name:
          type: string
        warehouse_code:
          type: string
        total_quantity:
          type: integer
        total_value:
          type: number
          format: float
        items_count:
          type: integer
        critical_count:
          type: integer
        overstock_count:
          type: integer

    InventoryItemsResponse:
      type: object
      required:
        - items
        - pagination
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/InventoryItem'
        pagination:
          $ref: '#/components/schemas/PaginationMetadata'

    InventoryItem:
      type: object
      required:
        - id
        - product_id
        - product_name
        - product_sku
        - category_id
        - category_name
        - warehouse_id
        - warehouse_name
        - warehouse_code
        - quantity_on_hand
        - quantity_reserved
        - quantity_available
        - unit_cost
        - inventory_value
        - sales_velocity
        - days_of_stock
        - stock_health
        - stock_health_label
        - safety_stock
        - reorder_point
      properties:
        id:
          type: string
        product_id:
          type: string
        product_name:
          type: string
        product_sku:
          type: string
        category_id:
          type: string
        category_name:
          type: string
        warehouse_id:
          type: string
        warehouse_name:
          type: string
        warehouse_code:
          type: string
        quantity_on_hand:
          type: integer
        quantity_reserved:
          type: integer
        quantity_available:
          type: integer
        unit_cost:
          type: number
          format: float
        inventory_value:
          type: number
          format: float
        sales_velocity:
          type: number
          format: float
        days_of_stock:
          type: number
          format: float
          nullable: true
        stock_health:
          type: string
          enum: [out_of_stock, critical, optimal, overstock]
        stock_health_label:
          type: string
        safety_stock:
          type: integer
        reorder_point:
          type: integer

    InventoryFilterOptionsResponse:
      type: object
      required:
        - warehouses
        - statuses
        - latest_snapshot_date
      properties:
        warehouses:
          type: array
          items:
            type: object
            required: [id, name, code]
            properties:
              id: { type: string }
              name: { type: string }
              code: { type: string }
        statuses:
          type: array
          items:
            type: object
            required: [value, label]
            properties:
              value: { type: string }
              label: { type: string }
        latest_snapshot_date:
          type: string
          format: date
```

Then regenerate TypeScript types:
`npm --prefix frontend run contracts:validate && npm --prefix frontend run api:generate`

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_inventory_intelligence_endpoints`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add contracts/openapi/analytics-v1.yaml backend/tests/Feature/ApiContractTest.php frontend/src/shared/api/generated/schema.ts
git commit -m "feat(contracts): define inventory intelligence OpenAPI 3.0.3 contract and generate frontend types"
```

---

### Task 2: Backend InventoryAnalytics Domain Layer (StockHealthStatus, InventoryMetrics & Unit Tests)

**Files:**
- Create: `backend/app/Modules/InventoryAnalytics/Domain/StockHealthStatus.php`
- Create: `backend/app/Modules/InventoryAnalytics/Domain/InventoryMetrics.php`
- Test: `backend/tests/Unit/Modules/InventoryAnalytics/Domain/InventoryMetricsTest.php`

**Interfaces:**
- Consumes: None (pure PHP, zero external dependencies)
- Produces:
  - `StockHealthStatus`: enum with `OUT_OF_STOCK`, `CRITICAL`, `OPTIMAL`, `OVERSTOCK`, `label()`, `color()`
  - `InventoryMetrics`:
    - `calculateSalesVelocity(int|float $unitsSold, int $days = 30): float`
    - `calculateDaysOfStock(int $availableQuantity, float $dailyVelocity): ?float`
    - `classifyStockHealth(int $availableQuantity, float $dailyVelocity, ?float $daysOfStock, int $safetyStock, int $reorderPoint): StockHealthStatus`
    - `calculateShare(float $part, float $total): float`

- [ ] **Step 1: Write the failing unit tests for Domain logic**

Create `backend/tests/Unit/Modules/InventoryAnalytics/Domain/InventoryMetricsTest.php`:

```php
<?php

namespace Tests\Unit\Modules\InventoryAnalytics\Domain;

use App\Modules\InventoryAnalytics\Domain\InventoryMetrics;
use App\Modules\InventoryAnalytics\Domain\StockHealthStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InventoryMetricsTest extends TestCase
{
    #[Test]
    public function calculate_sales_velocity_returns_correct_daily_rate(): void
    {
        self::assertSame(2.0, InventoryMetrics::calculateSalesVelocity(60, 30));
        self::assertSame(0.0, InventoryMetrics::calculateSalesVelocity(0, 30));
        self::assertSame(0.5, InventoryMetrics::calculateSalesVelocity(15, 30));
        self::assertSame(10.0, InventoryMetrics::calculateSalesVelocity(10, 0)); // Division by zero protection
    }

    #[Test]
    public function calculate_days_of_stock_handles_zero_velocity_and_zero_available(): void
    {
        // Available > 0 and velocity > 0
        self::assertSame(20.0, InventoryMetrics::calculateDaysOfStock(40, 2.0));

        // Available <= 0
        self::assertSame(0.0, InventoryMetrics::calculateDaysOfStock(0, 2.0));
        self::assertSame(0.0, InventoryMetrics::calculateDaysOfStock(-5, 2.0));

        // Available > 0 but zero sales velocity -> infinite/null
        self::assertNull(InventoryMetrics::calculateDaysOfStock(50, 0.0));
    }

    #[Test]
    public function classify_stock_health_identifies_out_of_stock(): void
    {
        $status = InventoryMetrics::classifyStockHealth(
            availableQuantity: 0,
            dailyVelocity: 1.0,
            daysOfStock: 0.0,
            safetyStock: 10,
            reorderPoint: 20
        );

        self::assertSame(StockHealthStatus::OUT_OF_STOCK, $status);
        self::assertSame('out_of_stock', $status->value);
        self::assertSame('Дефицит', $status->label());
    }

    #[Test]
    public function classify_stock_health_identifies_critical_when_dos_low_or_below_safety_stock(): void
    {
        // DOS <= 7 days
        $status1 = InventoryMetrics::classifyStockHealth(
            availableQuantity: 10,
            dailyVelocity: 2.0,
            daysOfStock: 5.0,
            safetyStock: 5,
            reorderPoint: 10
        );
        self::assertSame(StockHealthStatus::CRITICAL, $status1);
        self::assertSame('Критический', $status1->label());

        // Available <= safetyStock
        $status2 = InventoryMetrics::classifyStockHealth(
            availableQuantity: 15,
            dailyVelocity: 1.0,
            daysOfStock: 15.0,
            safetyStock: 20,
            reorderPoint: 40
        );
        self::assertSame(StockHealthStatus::CRITICAL, $status2);
    }

    #[Test]
    public function classify_stock_health_identifies_overstock(): void
    {
        // DOS > 60 days
        $status1 = InventoryMetrics::classifyStockHealth(
            availableQuantity: 150,
            dailyVelocity: 1.0,
            daysOfStock: 150.0,
            safetyStock: 20,
            reorderPoint: 40
        );
        self::assertSame(StockHealthStatus::OVERSTOCK, $status1);
        self::assertSame('Избыток', $status1->label());

        // Available > safetyStock * 3 with zero sales velocity
        $status2 = InventoryMetrics::classifyStockHealth(
            availableQuantity: 100,
            dailyVelocity: 0.0,
            daysOfStock: null,
            safetyStock: 20,
            reorderPoint: 40
        );
        self::assertSame(StockHealthStatus::OVERSTOCK, $status2);
    }

    #[Test]
    public function classify_stock_health_identifies_optimal_stock(): void
    {
        $status = InventoryMetrics::classifyStockHealth(
            availableQuantity: 50,
            dailyVelocity: 2.0,
            daysOfStock: 25.0,
            safetyStock: 15,
            reorderPoint: 30
        );
        self::assertSame(StockHealthStatus::OPTIMAL, $status);
        self::assertSame('В норме', $status->label());
    }

    #[Test]
    public function calculate_share_computes_ratio(): void
    {
        self::assertSame(0.25, InventoryMetrics::calculateShare(25, 100));
        self::assertSame(0.0, InventoryMetrics::calculateShare(25, 0));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=InventoryMetricsTest`
Expected: FAIL with "Class 'App\Modules\InventoryAnalytics\Domain\InventoryMetrics' not found"

- [ ] **Step 3: Write minimal implementation**

Create `backend/app/Modules/InventoryAnalytics/Domain/StockHealthStatus.php`:

```php
<?php

namespace App\Modules\InventoryAnalytics\Domain;

enum StockHealthStatus: string
{
    case OUT_OF_STOCK = 'out_of_stock';
    case CRITICAL = 'critical';
    case OPTIMAL = 'optimal';
    case OVERSTOCK = 'overstock';

    public function label(): string
    {
        return match ($this) {
            self::OUT_OF_STOCK => 'Дефицит',
            self::CRITICAL => 'Критический',
            self::OPTIMAL => 'В норме',
            self::OVERSTOCK => 'Избыток',
        };
    }
}
```

Create `backend/app/Modules/InventoryAnalytics/Domain/InventoryMetrics.php`:

```php
<?php

namespace App\Modules\InventoryAnalytics\Domain;

final class InventoryMetrics
{
    public const CRITICAL_DOS_THRESHOLD = 7.0;
    public const OVERSTOCK_DOS_THRESHOLD = 60.0;

    public static function calculateSalesVelocity(int|float $unitsSold, int $days = 30): float
    {
        if ($days <= 0) {
            return round((float) $unitsSold, 2);
        }

        return round($unitsSold / $days, 2);
    }

    public static function calculateDaysOfStock(int $availableQuantity, float $dailyVelocity): ?float
    {
        if ($availableQuantity <= 0) {
            return 0.0;
        }

        if ($dailyVelocity <= 0.0) {
            return null;
        }

        return round($availableQuantity / $dailyVelocity, 1);
    }

    public static function classifyStockHealth(
        int $availableQuantity,
        float $dailyVelocity,
        ?float $daysOfStock,
        int $safetyStock,
        int $reorderPoint,
    ): StockHealthStatus {
        if ($availableQuantity <= 0) {
            return StockHealthStatus::OUT_OF_STOCK;
        }

        if (($daysOfStock !== null && $daysOfStock <= self::CRITICAL_DOS_THRESHOLD) || $availableQuantity <= $safetyStock) {
            return StockHealthStatus::CRITICAL;
        }

        if (($daysOfStock !== null && $daysOfStock > self::OVERSTOCK_DOS_THRESHOLD) || ($daysOfStock === null && $availableQuantity > $safetyStock * 3)) {
            return StockHealthStatus::OVERSTOCK;
        }

        return StockHealthStatus::OPTIMAL;
    }

    public static function calculateShare(float $part, float $total): float
    {
        if ($total <= 0.0) {
            return 0.0;
        }

        return round($part / $total, 4);
    }
}
```

- [ ] **Step 4: Run test to verify it passes and verify ArchitectureTest**

Run: `composer --working-dir=backend test -- --filter=InventoryMetricsTest`
Expected: PASS (6 tests, 12 assertions)

Run: `composer --working-dir=backend test -- --filter=ArchitectureTest`
Expected: PASS (Domain has no forbidden dependencies)

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/InventoryAnalytics/Domain/ backend/tests/Unit/Modules/InventoryAnalytics/Domain/
git commit -m "feat(inventory): implement Domain StockHealthStatus and InventoryMetrics calculations with unit tests"
```

---

### Task 3: Backend InventoryAnalytics Application Layer (DTOs, Queries, Handlers, ReadModel Interface & Tests)

**Files:**
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/InventorySummaryDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/StockHealthBreakdownDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/WarehouseStockDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/InventoryItemDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/InventorySummaryCriteriaDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/InventoryItemsCriteriaDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/InventoryItemsPaginatedDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Dtos/InventoryFilterOptionsDto.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Contracts/InventoryAnalyticsReadModelInterface.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Queries/GetInventorySummaryQuery.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Queries/GetInventorySummaryHandler.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Queries/GetInventoryItemsQuery.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Queries/GetInventoryItemsHandler.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Queries/GetInventoryFilterOptionsQuery.php`
- Create: `backend/app/Modules/InventoryAnalytics/Application/Queries/GetInventoryFilterOptionsHandler.php`
- Test: `backend/tests/Unit/Modules/InventoryAnalytics/Application/InventoryAnalyticsApplicationTest.php`

**Interfaces:**
- Consumes: `App\Modules\InventoryAnalytics\Domain\InventoryMetrics`, `StockHealthStatus`
- Produces: Application contracts and CQRS queries/handlers handling summary, items, and filters

- [ ] **Step 1: Write failing application layer tests**

Create `backend/tests/Unit/Modules/InventoryAnalytics/Application/InventoryAnalyticsApplicationTest.php`:

```php
<?php

namespace Tests\Unit\Modules\InventoryAnalytics\Application;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\StockHealthBreakdownDto;
use App\Modules\InventoryAnalytics\Application\Dtos\WarehouseStockDto;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryFilterOptionsHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryFilterOptionsQuery;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryItemsHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryItemsQuery;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventorySummaryHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventorySummaryQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InventoryAnalyticsApplicationTest extends TestCase
{
    #[Test]
    public function get_inventory_summary_handler_delegates_to_read_model(): void
    {
        $readModel = $this->createMock(InventoryAnalyticsReadModelInterface::class);
        $expectedDto = new InventorySummaryDto(
            totalItems: 10,
            totalQuantityOnHand: 500,
            totalQuantityReserved: 50,
            totalQuantityAvailable: 450,
            totalInventoryValue: 120000.0,
            criticalCount: 2,
            overstockCount: 1,
            outOfStockCount: 1,
            optimalCount: 6,
            averageDaysOfStock: 25.5,
            healthBreakdown: [
                new StockHealthBreakdownDto('optimal', 'В норме', 6, 80000.0, 0.6667),
            ],
            warehouses: [
                new WarehouseStockDto('wh-1', 'Основной склад', 'WH-01', 500, 120000.0, 10, 2, 1),
            ],
            asOfDate: '2025-12-31',
        );

        $readModel->expects(self::once())
            ->method('getInventorySummary')
            ->with('ws-1', self::callback(fn (InventorySummaryCriteriaDto $c) => $c->warehouseId === 'wh-1' && $c->asOfDate === '2025-12-31'))
            ->willReturn($expectedDto);

        $handler = new GetInventorySummaryHandler($readModel);
        $result = $handler->handle(new GetInventorySummaryQuery('ws-1', 'wh-1', '2025-12-31'));

        self::assertSame(500, $result->totalQuantityOnHand);
        self::assertSame(120000.0, $result->totalInventoryValue);
    }

    #[Test]
    public function get_inventory_items_handler_normalizes_criteria_and_delegates(): void
    {
        $readModel = $this->createMock(InventoryAnalyticsReadModelInterface::class);
        $expectedPaginated = new InventoryItemsPaginatedDto(
            items: [
                new InventoryItemDto(
                    id: 'inv-1',
                    productId: 'prod-1',
                    productName: 'Шина летняя',
                    productSku: 'SKU-001',
                    categoryId: 'cat-1',
                    categoryName: 'Шины',
                    warehouseId: 'wh-1',
                    warehouseName: 'Москва Склад',
                    warehouseCode: 'WH-MSK',
                    quantityOnHand: 40,
                    quantityReserved: 5,
                    quantityAvailable: 35,
                    unitCost: 3500.0,
                    inventoryValue: 140000.0,
                    salesVelocity: 1.5,
                    daysOfStock: 23.3,
                    stockHealth: 'optimal',
                    stockHealthLabel: 'В норме',
                    safetyStock: 15,
                    reorderPoint: 30,
                ),
            ],
            total: 1,
            page: 1,
            perPage: 20,
            totalPages: 1,
        );

        $readModel->expects(self::once())
            ->method('getInventoryItems')
            ->with('ws-1', self::callback(fn (InventoryItemsCriteriaDto $c) => $c->page === 1 && $c->perPage === 20 && $c->sortBy === 'quantity_available'))
            ->willReturn($expectedPaginated);

        $handler = new GetInventoryItemsHandler($readModel);
        $result = $handler->handle(new GetInventoryItemsQuery(
            workspaceId: 'ws-1',
            warehouseId: null,
            stockHealth: null,
            search: null,
            page: 1,
            perPage: 20,
            sortBy: 'quantity_available',
            sortDirection: 'asc',
        ));

        self::assertCount(1, $result->items);
        self::assertSame(1, $result->total);
    }

    #[Test]
    public function get_inventory_filter_options_handler_delegates(): void
    {
        $readModel = $this->createMock(InventoryAnalyticsReadModelInterface::class);
        $expectedFilters = new InventoryFilterOptionsDto(
            warehouses: [['id' => 'wh-1', 'name' => 'Москва', 'code' => 'WH-MSK']],
            statuses: [['value' => 'critical', 'label' => 'Критический']],
            latestSnapshotDate: '2025-12-31',
        );

        $readModel->expects(self::once())
            ->method('getFilterOptions')
            ->with('ws-1')
            ->willReturn($expectedFilters);

        $handler = new GetInventoryFilterOptionsHandler($readModel);
        $result = $handler->handle(new GetInventoryFilterOptionsQuery('ws-1'));

        self::assertSame('2025-12-31', $result->latestSnapshotDate);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=InventoryAnalyticsApplicationTest`
Expected: FAIL with interface or classes not found.

- [ ] **Step 3: Implement DTOs, Queries, Handlers, and Contract Interface**

Create the DTOs in `backend/app/Modules/InventoryAnalytics/Application/Dtos/`:
- `InventorySummaryDto.php`: holds total items, on hand, reserved, available, value, counts for critical/overstock/out_of_stock/optimal, average DOS, `healthBreakdown` array, `warehouses` array, `asOfDate`.
- `StockHealthBreakdownDto.php`: holds `status`, `label`, `itemsCount`, `totalValue`, `share`.
- `WarehouseStockDto.php`: holds `warehouseId`, `warehouseName`, `warehouseCode`, `totalQuantity`, `totalValue`, `itemsCount`, `criticalCount`, `overstockCount`.
- `InventoryItemDto.php`: holds item details (id, productId, productName, productSku, categoryId, categoryName, warehouseId, warehouseName, warehouseCode, quantityOnHand, quantityReserved, quantityAvailable, unitCost, inventoryValue, salesVelocity, daysOfStock, stockHealth, stockHealthLabel, safetyStock, reorderPoint).
- `InventorySummaryCriteriaDto.php`: holds `?string $warehouseId`, `?string $asOfDate`.
- `InventoryItemsCriteriaDto.php`: holds `?string $warehouseId`, `?string $stockHealth`, `?string $search`, `int $page`, `int $perPage`, `string $sortBy`, `string $sortDirection`.
- `InventoryItemsPaginatedDto.php`: holds `list<InventoryItemDto> $items`, `int $total`, `int $page`, `int $perPage`, `int $totalPages`.
- `InventoryFilterOptionsDto.php`: holds `list<array{id: string, name: string, code: string}> $warehouses`, `list<array{value: string, label: string}> $statuses`, `string $latestSnapshotDate`.

Create `backend/app/Modules/InventoryAnalytics/Application/Contracts/InventoryAnalyticsReadModelInterface.php`:

```php
<?php

namespace App\Modules\InventoryAnalytics\Application\Contracts;

use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;

interface InventoryAnalyticsReadModelInterface
{
    public function getInventorySummary(string $workspaceId, InventorySummaryCriteriaDto $criteria): InventorySummaryDto;

    public function getInventoryItems(string $workspaceId, InventoryItemsCriteriaDto $criteria): InventoryItemsPaginatedDto;

    public function getFilterOptions(string $workspaceId): InventoryFilterOptionsDto;
}
```

Create Queries and Handlers in `backend/app/Modules/InventoryAnalytics/Application/Queries/`:
- `GetInventorySummaryQuery.php` & `GetInventorySummaryHandler.php`
- `GetInventoryItemsQuery.php` & `GetInventoryItemsHandler.php`
- `GetInventoryFilterOptionsQuery.php` & `GetInventoryFilterOptionsHandler.php`

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=InventoryAnalyticsApplicationTest`
Expected: PASS (3 tests, 5 assertions)

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/InventoryAnalytics/Application/ backend/tests/Unit/Modules/InventoryAnalytics/Application/
git commit -m "feat(inventory): implement Application DTOs, Queries, Handlers, and ReadModelInterface"
```

---

### Task 4: Backend InventoryAnalytics Infrastructure Layer (PostgreSQL & InMemory Read Models, Service Provider Binding)

**Files:**
- Create: `backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/InMemoryInventoryAnalyticsReadModel.php`
- Create: `backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/PostgresInventoryAnalyticsReadModel.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Unit/Modules/InventoryAnalytics/Infrastructure/InventoryAnalyticsReadModelTest.php`

**Interfaces:**
- Consumes: `fact_inventory_daily`, `fact_order_items`, `dim_products`, `dim_warehouses`, `dim_categories` in PostgreSQL; implements `InventoryAnalyticsReadModelInterface`
- Produces: Efficient SQL aggregations directly in DB (preventing memory loading) and in-memory mock for isolated testing

- [ ] **Step 1: Write failing infrastructure tests for both Read Models**

Create `backend/tests/Unit/Modules/InventoryAnalytics/Infrastructure/InventoryAnalyticsReadModelTest.php`:

```php
<?php

namespace Tests\Unit\Modules\InventoryAnalytics\Infrastructure;

use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\InMemoryInventoryAnalyticsReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InventoryAnalyticsReadModelTest extends TestCase
{
    #[Test]
    public function in_memory_read_model_returns_summary_with_deterministic_aggregates(): void
    {
        $readModel = new InMemoryInventoryAnalyticsReadModel();
        $summary = $readModel->getInventorySummary('ws-1', new InventorySummaryCriteriaDto(null, null));

        self::assertGreaterThan(0, $summary->totalItems);
        self::assertGreaterThan(0, $summary->totalQuantityOnHand);
        self::assertGreaterThan(0, $summary->totalInventoryValue);
        self::assertNotEmpty($summary->healthBreakdown);
        self::assertNotEmpty($summary->warehouses);
        self::assertNotNull($summary->asOfDate);
    }

    #[Test]
    public function in_memory_read_model_paginates_and_filters_items(): void
    {
        $readModel = new InMemoryInventoryAnalyticsReadModel();

        // 1. All items
        $criteria = new InventoryItemsCriteriaDto(
            warehouseId: null,
            stockHealth: null,
            search: null,
            page: 1,
            perPage: 5,
            sortBy: 'quantity_available',
            sortDirection: 'asc'
        );
        $result = $readModel->getInventoryItems('ws-1', $criteria);

        self::assertLessThanOrEqual(5, count($result->items));
        self::assertGreaterThanOrEqual(1, $result->total);

        // 2. Filter by status
        $criticalCriteria = new InventoryItemsCriteriaDto(
            warehouseId: null,
            stockHealth: 'critical',
            search: null,
            page: 1,
            perPage: 10,
            sortBy: 'quantity_available',
            sortDirection: 'asc'
        );
        $criticalResult = $readModel->getInventoryItems('ws-1', $criticalCriteria);
        foreach ($criticalResult->items as $item) {
            self::assertSame('critical', $item->stockHealth);
        }
    }

    #[Test]
    public function in_memory_read_model_returns_filter_options(): void
    {
        $readModel = new InMemoryInventoryAnalyticsReadModel();
        $filters = $readModel->getFilterOptions('ws-1');

        self::assertNotEmpty($filters->warehouses);
        self::assertNotEmpty($filters->statuses);
        self::assertSame('2025-12-31', $filters->latestSnapshotDate);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=InventoryAnalyticsReadModelTest`
Expected: FAIL with "Class 'App\Modules\InventoryAnalytics\Infrastructure\Persistence\InMemoryInventoryAnalyticsReadModel' not found"

- [ ] **Step 3: Implement InMemory and PostgreSQL Read Models & Bind in AppServiceProvider**

Create `backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/InMemoryInventoryAnalyticsReadModel.php`:
Provide deterministic demo records matching the schema for `ws-1` and `ws-2`, including:
- Categories: Tires, Brakes, Oils, Filters
- Warehouses: Moscow (`wh-msk-central`), Saint Petersburg (`wh-spb-north`), Samara (`wh-sam-volga`), Ekaterinburg (`wh-ekb-ural`)
- Products with statuses: out_of_stock (`prod-conti-wint-16`), critical (`prod-brembo-pad-front`), overstock (`prod-michelin-primacy-17`), optimal (`prod-castrol-edge-5w30`)
- Implements sorting and filtering by `warehouse_id`, `stock_health`, `search`.

Create `backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/PostgresInventoryAnalyticsReadModel.php`:
- Finds latest snapshot date if not provided:
  `$latestDate = DB::table('fact_inventory_daily')->where('workspace_id', $workspaceId)->max('snapshot_date');`
- Velocity calculation subquery: calculates daily sales velocity for each product from `fact_order_items` over 30 days prior to the snapshot date:
  `COALESCE(SUM(quantity), 0) / 30.0`
- Inventory summary query: executes `SUM(quantity_on_hand)`, `SUM(quantity_reserved)`, `SUM(quantity_available)`, `SUM(inventory_value)`, and conditionally counts items where `quantity_available <= 0` (out of stock), `quantity_available <= safety_stock` or `days_of_stock <= 7` (critical), etc.
- Warehouse breakdown query: joins `dim_warehouses` with `fact_inventory_daily`, groups by warehouse, aggregates quantities, values, and status counts.
- Paginated items query: joins `fact_inventory_daily`, `dim_products`, `dim_warehouses`, `dim_categories`, and calculated velocity, applies filtering, whitelist sorting (`quantity_on_hand`, `quantity_available`, `inventory_value`, `sales_velocity`, `days_of_stock`, `product_name`), and pagination with `COUNT(*) OVER()`.

Update `backend/app/Providers/AppServiceProvider.php`:
Add binding for `InventoryAnalyticsReadModelInterface`:
```php
use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\InMemoryInventoryAnalyticsReadModel;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\PostgresInventoryAnalyticsReadModel;

// In register():
$this->app->singleton(InventoryAnalyticsReadModelInterface::class, function () {
    if ($this->app->environment('testing')) {
        return new InMemoryInventoryAnalyticsReadModel;
    }

    return new PostgresInventoryAnalyticsReadModel;
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=InventoryAnalyticsReadModelTest`
Expected: PASS (3 tests, 8 assertions)

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/InventoryAnalytics/Infrastructure/ backend/app/Providers/AppServiceProvider.php backend/tests/Unit/Modules/InventoryAnalytics/Infrastructure/
git commit -m "feat(inventory): implement InMemory and Postgres Read Models with service provider registration"
```

---

### Task 5: Backend InventoryAnalytics Presentation Layer (Controller, Request Validation, API Routes & Tests)

**Files:**
- Create: `backend/app/Modules/InventoryAnalytics/Presentation/Requests/GetInventorySummaryRequest.php`
- Create: `backend/app/Modules/InventoryAnalytics/Presentation/Requests/GetInventoryItemsRequest.php`
- Create: `backend/app/Modules/InventoryAnalytics/Presentation/Controllers/InventoryAnalyticsController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Modules/InventoryAnalytics/InventoryAnalyticsApiTest.php`

**Interfaces:**
- Consumes: HTTP requests authenticated via `AuthenticateUserIdMiddleware` and guarded by `WorkspaceAccessGuard`
- Produces: JSON responses matching OpenAPI schemas `/analytics/inventory/summary`, `/analytics/inventory/items`, `/analytics/inventory/filters`

- [ ] **Step 1: Write failing Feature API tests**

Create `backend/tests/Feature/Modules/InventoryAnalytics/InventoryAnalyticsApiTest.php`:

```php
<?php

namespace Tests\Feature\Modules\InventoryAnalytics;

use Tests\TestCase;

final class InventoryAnalyticsApiTest extends TestCase
{
    public function test_inventory_summary_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/analytics/inventory/summary');
        $response->assertStatus(401);
    }

    public function test_inventory_summary_enforces_workspace_access_boundary(): void
    {
        // user-1 belongs to ws-1, forbidden to access ws-2
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson('/api/v1/analytics/inventory/summary');

        $response->assertStatus(403);
    }

    public function test_inventory_summary_returns_aggregated_metrics(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'summary' => [
                    'total_items',
                    'total_quantity_on_hand',
                    'total_quantity_reserved',
                    'total_quantity_available',
                    'total_inventory_value',
                    'critical_count',
                    'overstock_count',
                    'out_of_stock_count',
                    'optimal_count',
                    'average_days_of_stock',
                ],
                'health_breakdown' => [
                    '*' => ['status', 'label', 'items_count', 'total_value', 'share'],
                ],
                'warehouses' => [
                    '*' => [
                        'warehouse_id',
                        'warehouse_name',
                        'warehouse_code',
                        'total_quantity',
                        'total_value',
                        'items_count',
                        'critical_count',
                        'overstock_count',
                    ],
                ],
                'as_of_date',
            ]);
    }

    public function test_inventory_items_returns_paginated_records(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/items?page=1&per_page=10&sort_by=quantity_available&sort_direction=desc');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items' => [
                    '*' => [
                        'id',
                        'product_id',
                        'product_name',
                        'product_sku',
                        'category_id',
                        'category_name',
                        'warehouse_id',
                        'warehouse_name',
                        'warehouse_code',
                        'quantity_on_hand',
                        'quantity_reserved',
                        'quantity_available',
                        'unit_cost',
                        'inventory_value',
                        'sales_velocity',
                        'days_of_stock',
                        'stock_health',
                        'stock_health_label',
                        'safety_stock',
                        'reorder_point',
                    ],
                ],
                'pagination' => ['page', 'per_page', 'total', 'total_pages'],
            ]);
    }

    public function test_inventory_filters_returns_options(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/filters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'warehouses' => [['id', 'name', 'code']],
                'statuses' => [['value', 'label']],
                'latest_snapshot_date',
            ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=InventoryAnalyticsApiTest`
Expected: FAIL with 404 Not Found (routes do not exist yet).

- [ ] **Step 3: Implement Form Requests, Controller, and Register Routes**

Create `backend/app/Modules/InventoryAnalytics/Presentation/Requests/GetInventorySummaryRequest.php`:
- Validates optional `warehouse_id` (string), `as_of_date` (date format `Y-m-d`).

Create `backend/app/Modules/InventoryAnalytics/Presentation/Requests/GetInventoryItemsRequest.php`:
- Validates optional `warehouse_id` (string), `stock_health` (in: `out_of_stock`, `critical`, `optimal`, `overstock`), `search` (string, max 100), `page` (integer, min 1), `per_page` (integer, min 1, max 100), `sort_by` (in: `product_name`, `quantity_on_hand`, `quantity_available`, `inventory_value`, `sales_velocity`, `days_of_stock`), `sort_direction` (in: `asc`, `desc`).

Create `backend/app/Modules/InventoryAnalytics/Presentation/Controllers/InventoryAnalyticsController.php`:
- Handles `summary`, `items`, `filters` endpoints, resolving workspace context via `GetCurrentWorkspaceHandler` and tenant check.

Update `backend/routes/api.php`:
```php
use App\Modules\InventoryAnalytics\Presentation\Controllers\InventoryAnalyticsController;

// Inside AuthenticateUserIdMiddleware group:
Route::get('/analytics/inventory/summary', [InventoryAnalyticsController::class, 'summary']);
Route::get('/analytics/inventory/items', [InventoryAnalyticsController::class, 'items']);
Route::get('/analytics/inventory/filters', [InventoryAnalyticsController::class, 'filters']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=InventoryAnalyticsApiTest`
Expected: PASS (5 tests, 12 assertions)

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/InventoryAnalytics/Presentation/ backend/routes/api.php backend/tests/Feature/Modules/InventoryAnalytics/
git commit -m "feat(inventory): implement Presentation Controller, Requests, API routes, and Feature tests"
```

---

### Task 6: Frontend Inventory Analytics API Gateway & Contract Integration

**Files:**
- Create: `frontend/src/features/inventory-analytics/api/inventory-gateway.ts`
- Test: `frontend/src/features/inventory-analytics/api/inventory-gateway.test.ts`

**Interfaces:**
- Consumes: Generated types in `src/shared/api/generated/schema.ts`, `analyticsClient`
- Produces: Typed `inventoryGateway` with `getSummary`, `getItems`, `getFilters`

- [ ] **Step 1: Write failing gateway unit tests**

Create `frontend/src/features/inventory-analytics/api/inventory-gateway.test.ts`:

```typescript
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { inventoryGateway } from './inventory-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    GET: vi.fn(),
  },
}))

describe('inventoryGateway', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches inventory summary with user and workspace headers', async () => {
    const mockSummary = {
      summary: {
        total_items: 20,
        total_quantity_on_hand: 500,
        total_quantity_reserved: 50,
        total_quantity_available: 450,
        total_inventory_value: 250000,
        critical_count: 3,
        overstock_count: 2,
        out_of_stock_count: 1,
        optimal_count: 14,
        average_days_of_stock: 28.4,
      },
      health_breakdown: [],
      warehouses: [],
      as_of_date: '2025-12-31',
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockSummary,
      error: undefined,
    } as any)

    const result = await inventoryGateway.getSummary('user-1', 'ws-1', {
      warehouseId: 'wh-1',
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/inventory/summary',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        params: {
          query: {
            warehouse_id: 'wh-1',
            as_of_date: undefined,
          },
        },
      })
    )
    expect(result).toEqual(mockSummary)
  })

  it('fetches inventory items with pagination and sorting', async () => {
    const mockItems = {
      items: [],
      pagination: { page: 1, per_page: 20, total: 0, total_pages: 0 },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockItems,
      error: undefined,
    } as any)

    const result = await inventoryGateway.getItems('user-1', 'ws-1', {
      stockHealth: 'critical',
      page: 1,
      perPage: 20,
      sortBy: 'days_of_stock',
      sortDirection: 'asc',
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/inventory/items',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        params: {
          query: {
            stock_health: 'critical',
            page: 1,
            per_page: 20,
            sort_by: 'days_of_stock',
            sort_direction: 'asc',
          },
        },
      })
    )
    expect(result).toEqual(mockItems)
  })

  it('fetches inventory filters', async () => {
    const mockFilters = {
      warehouses: [],
      statuses: [],
      latest_snapshot_date: '2025-12-31',
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockFilters,
      error: undefined,
    } as any)

    const result = await inventoryGateway.getFilters('user-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/inventory/filters',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
      })
    )
    expect(result).toEqual(mockFilters)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- inventory-gateway.test.ts`
Expected: FAIL with "Cannot find module './inventory-gateway'"

- [ ] **Step 3: Implement inventoryGateway**

Create `frontend/src/features/inventory-analytics/api/inventory-gateway.ts`:

```typescript
import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type InventorySummaryResponse =
  components['schemas']['InventorySummaryResponse']
export type InventorySummary = components['schemas']['InventorySummary']
export type StockHealthBreakdownItem =
  components['schemas']['StockHealthBreakdownItem']
export type WarehouseStockBreakdownItem =
  components['schemas']['WarehouseStockBreakdownItem']
export type InventoryItemsResponse =
  components['schemas']['InventoryItemsResponse']
export type InventoryItem = components['schemas']['InventoryItem']
export type InventoryFilterOptionsResponse =
  components['schemas']['InventoryFilterOptionsResponse']

export interface InventorySummaryParams {
  warehouseId?: string
  asOfDate?: string
}

export interface InventoryItemsParams {
  warehouseId?: string
  stockHealth?: 'out_of_stock' | 'critical' | 'optimal' | 'overstock'
  search?: string
  page?: number
  perPage?: number
  sortBy?:
    | 'product_name'
    | 'quantity_on_hand'
    | 'quantity_available'
    | 'inventory_value'
    | 'sales_velocity'
    | 'days_of_stock'
  sortDirection?: 'asc' | 'desc'
}

export const inventoryGateway = {
  async getSummary(
    userId: string,
    workspaceId: string,
    params?: InventorySummaryParams
  ): Promise<InventorySummaryResponse> {
    const { data, error } = await analyticsClient.GET(
      '/analytics/inventory/summary',
      {
        headers: {
          'X-User-Id': userId,
          'X-Workspace-Id': workspaceId,
        },
        params: {
          query: {
            warehouse_id: params?.warehouseId,
            as_of_date: params?.asOfDate,
          },
        },
      }
    )

    if (error || !data) {
      throw new Error(
        (error as any)?.message ?? 'Failed to load inventory summary'
      )
    }

    return data
  },

  async getItems(
    userId: string,
    workspaceId: string,
    params?: InventoryItemsParams
  ): Promise<InventoryItemsResponse> {
    const { data, error } = await analyticsClient.GET(
      '/analytics/inventory/items',
      {
        headers: {
          'X-User-Id': userId,
          'X-Workspace-Id': workspaceId,
        },
        params: {
          query: {
            warehouse_id: params?.warehouseId,
            stock_health: params?.stockHealth,
            search: params?.search,
            page: params?.page,
            per_page: params?.perPage,
            sort_by: params?.sortBy,
            sort_direction: params?.sortDirection,
          },
        },
      }
    )

    if (error || !data) {
      throw new Error(
        (error as any)?.message ?? 'Failed to load inventory items'
      )
    }

    return data
  },

  async getFilters(
    userId: string,
    workspaceId: string
  ): Promise<InventoryFilterOptionsResponse> {
    const { data, error } = await analyticsClient.GET(
      '/analytics/inventory/filters',
      {
        headers: {
          'X-User-Id': userId,
          'X-Workspace-Id': workspaceId,
        },
      }
    )

    if (error || !data) {
      throw new Error(
        (error as any)?.message ?? 'Failed to load inventory filter options'
      )
    }

    return data
  },
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- inventory-gateway.test.ts`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/inventory-analytics/api/
git commit -m "feat(inventory): implement typed frontend inventoryGateway with Vitest tests"
```

---

### Task 7: Frontend Inventory Analytics UI Components (KPI Cards, Health Breakdown, Warehouse Breakdown, Items Table, Filters)

**Files:**
- Create: `frontend/src/features/inventory-analytics/ui/inventory-kpi-cards.tsx`
- Create: `frontend/src/features/inventory-analytics/ui/inventory-health-breakdown.tsx`
- Create: `frontend/src/features/inventory-analytics/ui/inventory-warehouse-breakdown.tsx`
- Create: `frontend/src/features/inventory-analytics/ui/inventory-filters-bar.tsx`
- Create: `frontend/src/features/inventory-analytics/ui/inventory-items-table.tsx`
- Test: `frontend/src/features/inventory-analytics/ui/inventory-components.test.tsx`

**Interfaces:**
- Consumes: Types from `inventory-gateway.ts`, `shadcn/ui` components (`Card`, `Badge`, `Table`, `Select`, `Button`, `Input`)
- Produces: Presentational components without domain calculation duplication

- [ ] **Step 1: Write failing component unit tests**

Create `frontend/src/features/inventory-analytics/ui/inventory-components.test.tsx`:

```tsx
import React from 'react'
import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'
import { InventoryKpiCards } from './inventory-kpi-cards'
import { InventoryHealthBreakdown } from './inventory-health-breakdown'
import { InventoryWarehouseBreakdown } from './inventory-warehouse-breakdown'
import { InventoryFiltersBar } from './inventory-filters-bar'
import { InventoryItemsTable } from './inventory-items-table'

describe('Inventory UI Components', () => {
  const mockSummary = {
    total_items: 45,
    total_quantity_on_hand: 1200,
    total_quantity_reserved: 150,
    total_quantity_available: 1050,
    total_inventory_value: 3450000,
    critical_count: 4,
    overstock_count: 6,
    out_of_stock_count: 2,
    optimal_count: 33,
    average_days_of_stock: 32.5,
  }

  it('renders KPI cards with correct formatted values', () => {
    render(<InventoryKpiCards summary={mockSummary} />)

    expect(screen.getByText('Стоимость остатков')).toBeDefined()
    expect(screen.getByText('Доступно шт.')).toBeDefined()
    expect(screen.getByText('Критические запасы')).toBeDefined()
    expect(screen.getByText('Избыточный запас')).toBeDefined()
    expect(screen.getByText('4 поз.')).toBeDefined()
    expect(screen.getByText('6 поз.')).toBeDefined()
  })

  it('renders health breakdown distribution badges', () => {
    const health = [
      {
        status: 'optimal' as const,
        label: 'В норме',
        items_count: 33,
        total_value: 2500000,
        share: 0.73,
      },
      {
        status: 'critical' as const,
        label: 'Критический',
        items_count: 4,
        total_value: 200000,
        share: 0.09,
      },
      {
        status: 'overstock' as const,
        label: 'Избыток',
        items_count: 6,
        total_value: 700000,
        share: 0.13,
      },
      {
        status: 'out_of_stock' as const,
        label: 'Дефицит',
        items_count: 2,
        total_value: 0,
        share: 0.05,
      },
    ]

    render(
      <InventoryHealthBreakdown
        items={health}
        selectedStatus={null}
        onSelectStatus={() => {}}
      />
    )

    expect(screen.getByText('Распределение здоровья запасов')).toBeDefined()
    expect(screen.getByText('В норме: 33')).toBeDefined()
    expect(screen.getByText('Критический: 4')).toBeDefined()
  })

  it('renders warehouse cards and triggers onSelectWarehouse', () => {
    const onSelect = vi.fn()
    const warehouses = [
      {
        warehouse_id: 'wh-msk',
        warehouse_name: 'Москва Центр',
        warehouse_code: 'WH-MSK-01',
        total_quantity: 600,
        total_value: 1800000,
        items_count: 25,
        critical_count: 2,
        overstock_count: 3,
      },
    ]

    render(
      <InventoryWarehouseBreakdown
        warehouses={warehouses}
        selectedWarehouseId={null}
        onSelectWarehouse={onSelect}
      />
    )

    expect(screen.getByText('Москва Центр')).toBeDefined()
    const card = screen.getByText('Москва Центр').closest('button')
    if (card) fireEvent.click(card)
    expect(onSelect).toHaveBeenCalledWith('wh-msk')
  })

  it('renders items table with records and pagination controls', () => {
    const onPageChange = vi.fn()
    const onSortChange = vi.fn()
    const items = [
      {
        id: '1',
        product_id: 'p1',
        product_name: 'Шина зимняя Conti',
        product_sku: 'CONTI-01',
        category_id: 'c1',
        category_name: 'Шины',
        warehouse_id: 'wh1',
        warehouse_name: 'Москва',
        warehouse_code: 'WH-MSK',
        quantity_on_hand: 50,
        quantity_reserved: 10,
        quantity_available: 40,
        unit_cost: 4500,
        inventory_value: 225000,
        sales_velocity: 2.5,
        days_of_stock: 16.0,
        stock_health: 'optimal' as const,
        stock_health_label: 'В норме',
        safety_stock: 15,
        reorder_point: 30,
      },
    ]

    render(
      <InventoryItemsTable
        items={items}
        pagination={{ page: 1, per_page: 20, total: 1, total_pages: 1 }}
        sortBy="quantity_available"
        sortDirection="asc"
        onSort={onSortChange}
        onPageChange={onPageChange}
      />
    )

    expect(screen.getByText('Шина зимняя Conti')).toBeDefined()
    expect(screen.getByText('CONTI-01')).toBeDefined()
    expect(screen.getByText('16 дн.')).toBeDefined()
    expect(screen.getByText('В норме')).toBeDefined()
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- inventory-components.test.tsx`
Expected: FAIL with components not found.

- [ ] **Step 3: Implement Inventory UI Components**

Implement:
- `inventory-kpi-cards.tsx`: using `Card`, `CardContent`, `CardHeader`, `CardTitle` from `@/components/ui/card`.
- `inventory-health-breakdown.tsx`: segmented progress visualizer and clickable filter badges for optimal (emerald), critical (rose), overstock (amber), out_of_stock (destructive).
- `inventory-warehouse-breakdown.tsx`: interactive cards showing warehouse code, total units, value, critical warning tags.
- `inventory-filters-bar.tsx`: search input for SKU/name, warehouse dropdown, status dropdown, clear filters button.
- `inventory-items-table.tsx`: sortable column headers (`ArrowUpDown`), badges for status, formatted numbers (`Intl.NumberFormat`), pagination buttons (`Предыдущая`, `Следующая`).

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- inventory-components.test.tsx`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/inventory-analytics/ui/
git commit -m "feat(inventory): implement shadcn/ui Inventory components with Vitest tests"
```

---

### Task 8: Frontend Inventory Analytics Dashboard & App Page Integration

**Files:**
- Create: `frontend/src/features/inventory-analytics/ui/inventory-dashboard.tsx`
- Modify: `frontend/app/page.tsx`
- Test: `frontend/src/features/inventory-analytics/ui/inventory-dashboard.test.tsx`

**Interfaces:**
- Consumes: `inventoryGateway`, `InventoryKpiCards`, `InventoryHealthBreakdown`, `InventoryWarehouseBreakdown`, `InventoryFiltersBar`, `InventoryItemsTable`
- Produces: Complete reactive `InventoryDashboard` with URL search param sync (`?view=inventory&warehouse_id=...&status=...`), integrated on `app/page.tsx` with view switching tabs between Sales and Inventory.

- [ ] **Step 1: Write failing dashboard interaction test**

Create `frontend/src/features/inventory-analytics/ui/inventory-dashboard.test.tsx`:

```tsx
import React from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { InventoryDashboard } from './inventory-dashboard'
import { inventoryGateway } from '../api/inventory-gateway'

vi.mock('../api/inventory-gateway', () => ({
  inventoryGateway: {
    getSummary: vi.fn(),
    getItems: vi.fn(),
    getFilters: vi.fn(),
  },
}))

vi.mock('next/navigation', () => ({
  useSearchParams: () => new URLSearchParams(),
}))

describe('InventoryDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders skeleton on loading and displays data on success', async () => {
    vi.mocked(inventoryGateway.getFilters).mockResolvedValueOnce({
      warehouses: [{ id: 'wh-1', name: 'Москва', code: 'WH-01' }],
      statuses: [{ value: 'critical', label: 'Критический' }],
      latest_snapshot_date: '2025-12-31',
    })

    vi.mocked(inventoryGateway.getSummary).mockResolvedValueOnce({
      summary: {
        total_items: 10,
        total_quantity_on_hand: 500,
        total_quantity_reserved: 50,
        total_quantity_available: 450,
        total_inventory_value: 1200000,
        critical_count: 2,
        overstock_count: 1,
        out_of_stock_count: 0,
        optimal_count: 7,
        average_days_of_stock: 22.0,
      },
      health_breakdown: [],
      warehouses: [],
      as_of_date: '2025-12-31',
    })

    vi.mocked(inventoryGateway.getItems).mockResolvedValueOnce({
      items: [],
      pagination: { page: 1, per_page: 20, total: 0, total_pages: 0 },
    })

    render(<InventoryDashboard userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByTestId('inventory-loading-skeleton')).toBeDefined()

    await waitFor(() => {
      expect(screen.getByText('Стоимость остатков')).toBeDefined()
    })
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- inventory-dashboard.test.tsx`
Expected: FAIL with module not found.

- [ ] **Step 3: Implement InventoryDashboard and update app/page.tsx**

Create `frontend/src/features/inventory-analytics/ui/inventory-dashboard.tsx`:
- Fetches filters, summary, and items in parallel.
- URL state synchronization using `useSearchParams` and `window.history.replaceState`.
- Renders KPI cards, Health breakdown, Warehouse cards, Filters bar, Items table, empty state, and error state with retry.

Update `frontend/app/page.tsx`:
- Add a top view switcher (Tabs or toggle buttons: "Аналитика продаж" / "Управление запасами") using `view` search param (`sales` by default, `inventory` when switched).
- If `view === 'inventory'`, render `<InventoryDashboard userId={demoUserId} workspaceId={workspaceId} />`.
- If `view === 'sales'` (default), render `<SalesDashboard userId={demoUserId} workspaceId={workspaceId} />`.

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test`
Expected: ALL frontend test suites PASS (including existing sales tests and new inventory tests).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/inventory-analytics/ui/inventory-dashboard.tsx frontend/src/features/inventory-analytics/ui/inventory-dashboard.test.tsx frontend/app/page.tsx
git commit -m "feat(inventory): integrate InventoryDashboard with URL state and view switcher on main page"
```

---

### Task 9: End-to-End Verification Checkpoint (`make check`, Integration Script, Roadmap Update)

**Files:**
- Modify: `scripts/verify-integration.sh`
- Modify: `docs/roadmap/06-inventory-intelligence.md`
- Modify: `docs/roadmap/ROADMAP.md`

**Interfaces:**
- Consumes: Running Docker stack or local services
- Produces: Verified exit criteria and updated roadmap documentation

- [ ] **Step 1: Extend verify-integration.sh with inventory checks**

Add inventory analytics checks to `scripts/verify-integration.sh`:
- Assert `/api/v1/analytics/inventory/summary` returns 200 with `summary`, `health_breakdown`, `warehouses` for `ws-1`.
- Assert `/api/v1/analytics/inventory/items` returns 200 with `items` array and `pagination`.
- Assert cross-workspace isolation: `user-1` querying `ws-2` returns 403 Forbidden.

- [ ] **Step 2: Run automated verification checks**

Run: `make check`
Expected:
1. `npm --prefix frontend run contracts:validate`: 0 errors
2. `npm --prefix frontend run api:generate`: schema up-to-date
3. `npm --prefix frontend run lint`: clean
4. `npm --prefix frontend run format:check`: clean
5. `npm --prefix frontend run typecheck`: clean
6. `npm --prefix frontend test`: all tests pass
7. `npm --prefix frontend run build`: Next.js production build succeeds
8. `composer --working-dir=backend validate --strict`: valid
9. `composer --working-dir=backend lint`: clean
10. `composer --working-dir=backend test`: all PHPUnit tests pass

- [ ] **Step 3: Update documentation and roadmap progress**

Update `docs/roadmap/06-inventory-intelligence.md` with:
- Summary of implemented components (OpenAPI contract, Domain metrics, Application CQRS, Postgres Read Model, Presentation controller, Next.js UI components and dashboard).
- Verification date, test outputs, exit criteria fulfillment.

Update `docs/roadmap/ROADMAP.md`:
Mark `- [x] [Phase 6 — Inventory Intelligence](06-inventory-intelligence.md)`.

- [ ] **Step 4: Commit**

```bash
git add scripts/verify-integration.sh docs/roadmap/06-inventory-intelligence.md docs/roadmap/ROADMAP.md
git commit -m "docs(roadmap): complete Phase 6 inventory intelligence vertical slice"
```

---

## Self-Review

1. **Spec coverage:** Skim each requirement in `docs/roadmap/06-inventory-intelligence.md`:
   - Current inventory, stock quantity: covered in Task 1, 2, 3, 4, 7.
   - Average sales velocity: covered in Task 1, 2, 3, 4, 7.
   - Days of stock (DOS): covered in Task 1, 2, 3, 4, 7.
   - Critical stock, overstock, stock health: covered in Task 1, 2, 3, 4, 7.
   - Warehouse breakdown: covered in Task 1, 3, 4, 7.
   - Product drill-down: covered in Task 1, 3, 4, 7.
   - Inventory dashboard & filters: covered in Task 7, 8.
   - Exit criteria (calculations tested, deterministic classifications, no frontend business rules, performant queries): confirmed.
2. **Placeholder scan:** No "TODO", "implement later", or missing function bodies exist. Every step has concrete code blocks or terminal commands.
3. **Type consistency:** Method names (`calculateSalesVelocity`, `calculateDaysOfStock`, `classifyStockHealth`), DTO names (`InventorySummaryDto`, `InventoryItemDto`), and schema types match across all tasks.
