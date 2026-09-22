# Sales Analytics Vertical Slice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the end-to-end Sales Analytics Vertical Slice from PostgreSQL to Next.js UI, delivering revenue, order count, AOV, gross profit, sales trends, category and regional breakdowns with synchronized filters.

**Architecture:** CQRS-lite with a specialized PostgreSQL read model in Laravel DDD (`SalesAnalytics` bounded context) exposing OpenAPI-contracted endpoints, consumed by a typed Next.js frontend feature with a reactive dashboard, zero-dependency responsive SVG visualizations, and loading/empty/error states.

**Tech Stack:** Laravel 11 (PHP 8.3), PostgreSQL 16 (Star Schema), OpenAPI 3.0.3, Next.js 15 (App Router, React 19), openapi-typescript, openapi-fetch, Vitest, PHPUnit 11.

**Spec:** `docs/roadmap/04-sales-analytics.md`

## Global Constraints

- Domain Layer in `App\Modules\SalesAnalytics\Domain` MUST NOT depend on Laravel/Illuminate, framework helpers, or Infrastructure layers (`ArchitectureTest`).
- Database aggregations MUST execute in PostgreSQL; DO NOT load raw order rows into PHP memory (`docs/architecture/06-data-and-analytics.md`, ADR-014).
- Frontend MUST NOT calculate domain business metrics; all KPIs, averages, and shares are computed by backend (`docs/architecture/03-frontend-nextjs.md`, ADR-013).
- OpenAPI specification in `contracts/openapi/analytics-v1.yaml` is the single source of truth; code must be generated via `npm --prefix frontend run api:generate` (`docs/architecture/07-api-and-integration.md`, ADR-007).
- Multi-tenancy isolation MUST be enforced on every query via `workspace_id` and verified against `WorkspaceAccessGuard`.

---

### Task 1: OpenAPI Contract for Sales Analytics & TypeScript Client Generation

**Files:**
- Modify: `contracts/openapi/analytics-v1.yaml`
- Modify: `backend/tests/Feature/ApiContractTest.php`
- Generated: `frontend/src/shared/api/generated/schema.ts`

**Interfaces:**
- Consumes: Existing OpenAPI schema with `UserIdAuth`, `ErrorResponse`, `/workspaces`
- Produces: New endpoints `/analytics/sales/overview` and `/analytics/sales/filters` with schemas `SalesOverviewResponse`, `SalesSummary`, `SalesTrendPoint`, `SalesCategoryBreakdown`, `SalesRegionBreakdown`, `SalesFilterOptionsResponse`

- [ ] **Step 1: Write the failing contract test**

Update `backend/tests/Feature/ApiContractTest.php` to assert that the OpenAPI contract contains the sales analytics endpoints and schemas:

```php
    public function test_contract_contains_sales_analytics_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/analytics/sales/overview', $contract['paths']);
        self::assertArrayHasKey('/analytics/sales/filters', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('SalesOverviewResponse', $schemas);
        self::assertArrayHasKey('SalesSummary', $schemas);
        self::assertArrayHasKey('SalesTrendPoint', $schemas);
        self::assertArrayHasKey('SalesCategoryBreakdown', $schemas);
        self::assertArrayHasKey('SalesRegionBreakdown', $schemas);
        self::assertArrayHasKey('SalesFilterOptionsResponse', $schemas);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_sales_analytics_endpoints`
Expected: FAIL with "Failed asserting that an array has key '/analytics/sales/overview'."

- [ ] **Step 3: Update OpenAPI specification and generate TypeScript types**

Add `/analytics/sales/overview` and `/analytics/sales/filters` paths and schema components to `contracts/openapi/analytics-v1.yaml`:

```yaml
  /analytics/sales/overview:
    get:
      operationId: getSalesOverview
      summary: Get aggregated sales summary, trends, and breakdowns
      parameters:
        - in: header
          name: X-Workspace-Id
          required: false
          schema:
            type: string
          description: Optional requested workspace identifier
        - in: query
          name: date_from
          required: false
          schema:
            type: string
            format: date
          description: Start of date filter range (YYYY-MM-DD)
        - in: query
          name: date_to
          required: false
          schema:
            type: string
            format: date
          description: End of date filter range (YYYY-MM-DD)
        - in: query
          name: category_id
          required: false
          schema:
            type: string
          description: Filter by specific category ID
        - in: query
          name: region_id
          required: false
          schema:
            type: string
          description: Filter by specific region ID
      responses:
        '200':
          description: Aggregated sales overview
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SalesOverviewResponse'
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
  /analytics/sales/filters:
    get:
      operationId: getSalesFilterOptions
      summary: Get available categories, regions, and date boundaries for sales filters
      parameters:
        - in: header
          name: X-Workspace-Id
          required: false
          schema:
            type: string
          description: Optional requested workspace identifier
      responses:
        '200':
          description: Filter options
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SalesFilterOptionsResponse'
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

And in `components.schemas`:
```yaml
    SalesOverviewResponse:
      type: object
      additionalProperties: false
      required: [summary, trend, categories, regions]
      properties:
        summary:
          $ref: '#/components/schemas/SalesSummary'
        trend:
          type: array
          items:
            $ref: '#/components/schemas/SalesTrendPoint'
        categories:
          type: array
          items:
            $ref: '#/components/schemas/SalesCategoryBreakdown'
        regions:
          type: array
          items:
            $ref: '#/components/schemas/SalesRegionBreakdown'
    SalesSummary:
      type: object
      additionalProperties: false
      required: [total_revenue, order_count, average_order_value, gross_profit, margin_rate]
      properties:
        total_revenue: { type: number, format: float }
        order_count: { type: integer }
        average_order_value: { type: number, format: float }
        gross_profit: { type: number, format: float }
        margin_rate: { type: number, format: float }
    SalesTrendPoint:
      type: object
      additionalProperties: false
      required: [date, revenue, order_count]
      properties:
        date: { type: string, format: date }
        revenue: { type: number, format: float }
        order_count: { type: integer }
    SalesCategoryBreakdown:
      type: object
      additionalProperties: false
      required: [category_id, category_name, revenue, order_count, revenue_share]
      properties:
        category_id: { type: string }
        category_name: { type: string }
        revenue: { type: number, format: float }
        order_count: { type: integer }
        revenue_share: { type: number, format: float }
    SalesRegionBreakdown:
      type: object
      additionalProperties: false
      required: [region_id, region_name, region_code, revenue, order_count, revenue_share]
      properties:
        region_id: { type: string }
        region_name: { type: string }
        region_code: { type: string }
        revenue: { type: number, format: float }
        order_count: { type: integer }
        revenue_share: { type: number, format: float }
    SalesFilterOptionsResponse:
      type: object
      additionalProperties: false
      required: [categories, regions, min_date, max_date]
      properties:
        categories:
          type: array
          items:
            type: object
            additionalProperties: false
            required: [id, name]
            properties:
              id: { type: string }
              name: { type: string }
        regions:
          type: array
          items:
            type: object
            additionalProperties: false
            required: [id, name, code]
            properties:
              id: { type: string }
              name: { type: string }
              code: { type: string }
        min_date: { type: string, format: date }
        max_date: { type: string, format: date }
```

Run OpenAPI validation and client code generation:
`make check-contracts`

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_sales_analytics_endpoints`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add contracts/openapi/analytics-v1.yaml frontend/src/shared/api/generated/schema.ts backend/tests/Feature/ApiContractTest.php
git commit -m "feat(contracts): define Sales Analytics OpenAPI endpoints and schemas"
```

---

### Task 2: Backend SalesAnalytics Domain Layer (Value Objects & Tests)

**Files:**
- Create: `backend/app/Modules/SalesAnalytics/Domain/DateRange.php`
- Create: `backend/app/Modules/SalesAnalytics/Domain/Exceptions/InvalidDateRangeException.php`
- Create: `backend/app/Modules/SalesAnalytics/Domain/SalesMetrics.php`
- Create: `backend/tests/Unit/Modules/SalesAnalytics/Domain/SalesAnalyticsDomainTest.php`

**Interfaces:**
- Consumes: Pure PHP standard library (DateTimeImmutable)
- Produces:
  - `DateRange`: `create(?string $from, ?string $to): self`, `from(): ?string`, `to(): ?string`
  - `SalesMetrics::calculateAov(float $revenue, int $orderCount): float`
  - `SalesMetrics::calculateMarginRate(float $revenue, float $grossProfit): float`
  - `SalesMetrics::calculateShare(float $part, float $total): float`

- [ ] **Step 1: Write the failing unit tests**

Create `backend/tests/Unit/Modules/SalesAnalytics/Domain/SalesAnalyticsDomainTest.php`:

```php
<?php

namespace Tests\Unit\Modules\SalesAnalytics\Domain;

use App\Modules\SalesAnalytics\Domain\DateRange;
use App\Modules\SalesAnalytics\Domain\Exceptions\InvalidDateRangeException;
use App\Modules\SalesAnalytics\Domain\SalesMetrics;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SalesAnalyticsDomainTest extends TestCase
{
    #[Test]
    public function it_creates_valid_date_range(): void
    {
        $range = DateRange::create('2025-01-01', '2025-01-31');
        self::assertSame('2025-01-01', $range->from());
        self::assertSame('2025-01-31', $range->to());
    }

    #[Test]
    public function it_allows_null_boundaries(): void
    {
        $range = DateRange::create(null, null);
        self::assertNull($range->from());
        self::assertNull($range->to());
    }

    #[Test]
    public function it_throws_when_from_date_is_after_to_date(): void
    {
        $this->expectException(InvalidDateRangeException::class);
        DateRange::create('2025-02-01', '2025-01-01');
    }

    #[Test]
    public function it_calculates_aov_correctly(): void
    {
        self::assertSame(150.0, SalesMetrics::calculateAov(300.0, 2));
        self::assertSame(0.0, SalesMetrics::calculateAov(0.0, 0));
        self::assertSame(0.0, SalesMetrics::calculateAov(500.0, 0));
    }

    #[Test]
    public function it_calculates_margin_rate_and_shares(): void
    {
        self::assertSame(0.25, SalesMetrics::calculateMarginRate(1000.0, 250.0));
        self::assertSame(0.0, SalesMetrics::calculateMarginRate(0.0, 0.0));
        self::assertSame(0.4, SalesMetrics::calculateShare(400.0, 1000.0));
        self::assertSame(0.0, SalesMetrics::calculateShare(100.0, 0.0));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=SalesAnalyticsDomainTest`
Expected: FAIL with "Class DateRange not found"

- [ ] **Step 3: Write minimal Domain implementation**

Create `backend/app/Modules/SalesAnalytics/Domain/Exceptions/InvalidDateRangeException.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Domain\Exceptions;

use DomainException;

final class InvalidDateRangeException extends DomainException
{
    public static function inverted(string $from, string $to): self
    {
        return new self("Invalid date range: '{$from}' cannot be after '{$to}'.");
    }
}
```

Create `backend/app/Modules/SalesAnalytics/Domain/DateRange.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Domain;

use App\Modules\SalesAnalytics\Domain\Exceptions\InvalidDateRangeException;
use DateTimeImmutable;

final readonly class DateRange
{
    private function __construct(
        private ?string $from,
        private ?string $to,
    ) {}

    public static function create(?string $from, ?string $to): self
    {
        if ($from !== null && $to !== null) {
            $fromDate = DateTimeImmutable::createFromFormat('Y-m-d', $from);
            $toDate = DateTimeImmutable::createFromFormat('Y-m-d', $to);

            if ($fromDate !== false && $toDate !== false && $fromDate > $toDate) {
                throw InvalidDateRangeException::inverted($from, $to);
            }
        }

        return new self($from, $to);
    }

    public function from(): ?string
    {
        return $this->from;
    }

    public function to(): ?string
    {
        return $this->to;
    }
}
```

Create `backend/app/Modules/SalesAnalytics/Domain/SalesMetrics.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Domain;

final class SalesMetrics
{
    public static function calculateAov(float $revenue, int $orderCount): float
    {
        if ($orderCount <= 0) {
            return 0.0;
        }

        return round($revenue / $orderCount, 2);
    }

    public static function calculateMarginRate(float $revenue, float $grossProfit): float
    {
        if ($revenue <= 0.0) {
            return 0.0;
        }

        return round($grossProfit / $revenue, 4);
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

- [ ] **Step 4: Run tests to verify they pass and check architecture rules**

Run: `composer --working-dir=backend test -- --filter=SalesAnalyticsDomainTest`
Expected: PASS
Run: `composer --working-dir=backend test -- --filter=ArchitectureTest`
Expected: PASS (zero forbidden dependencies in SalesAnalytics Domain)

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/SalesAnalytics/Domain backend/tests/Unit/Modules/SalesAnalytics/Domain
git commit -m "feat(sales-analytics): add domain DateRange and SalesMetrics value objects"
```

---

### Task 3: Backend SalesAnalytics Application Layer (DTOs, Queries, Handlers, ReadModel Interface & Tests)

**Files:**
- Create: `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesFilterCriteriaDto.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesSummaryDto.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesTrendPointDto.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesCategoryBreakdownDto.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesRegionBreakdownDto.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesOverviewDto.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesFilterOptionsDto.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Contracts/SalesAnalyticsReadModelInterface.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Queries/GetSalesOverviewQuery.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Queries/GetSalesOverviewHandler.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Queries/GetSalesFilterOptionsQuery.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Queries/GetSalesFilterOptionsHandler.php`
- Create: `backend/tests/Unit/Modules/SalesAnalytics/Application/SalesAnalyticsApplicationTest.php`

**Interfaces:**
- Consumes: `App\Modules\SalesAnalytics\Domain\DateRange`, `SalesAnalyticsReadModelInterface`
- Produces: `GetSalesOverviewHandler::handle(GetSalesOverviewQuery): SalesOverviewDto`, `GetSalesFilterOptionsHandler::handle(GetSalesFilterOptionsQuery): SalesFilterOptionsDto`

- [ ] **Step 1: Write failing application tests with mock/fake read model**

Create `backend/tests/Unit/Modules/SalesAnalytics/Application/SalesAnalyticsApplicationTest.php`:

```php
<?php

namespace Tests\Unit\Modules\SalesAnalytics\Application;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesCategoryBreakdownDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRegionBreakdownDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesSummaryDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesTrendPointDto;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesFilterOptionsHandler;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesFilterOptionsQuery;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesOverviewHandler;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesOverviewQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SalesAnalyticsApplicationTest extends TestCase
{
    #[Test]
    public function it_handles_sales_overview_query(): void
    {
        $mockReadModel = $this->createMock(SalesAnalyticsReadModelInterface::class);

        $expectedOverview = new SalesOverviewDto(
            summary: new SalesSummaryDto(150000.0, 50, 3000.0, 45000.0, 0.3),
            trend: [new SalesTrendPointDto('2025-01-01', 50000.0, 15)],
            categories: [new SalesCategoryBreakdownDto('cat-1', 'Tires', 100000.0, 30, 0.6667)],
            regions: [new SalesRegionBreakdownDto('reg-1', 'Moscow', 'MSK', 150000.0, 50, 1.0)],
        );

        $mockReadModel->expects(self::once())
            ->method('getSalesOverview')
            ->with('ws-1', self::isInstanceOf(SalesFilterCriteriaDto::class))
            ->willReturn($expectedOverview);

        $handler = new GetSalesOverviewHandler($mockReadModel);
        $result = $handler->handle(new GetSalesOverviewQuery('ws-1', '2025-01-01', '2025-01-31', 'cat-1', null));

        self::assertSame(150000.0, $result->summary->totalRevenue);
        self::assertSame(50, $result->summary->orderCount);
        self::assertCount(1, $result->trend);
        self::assertCount(1, $result->categories);
        self::assertCount(1, $result->regions);
    }

    #[Test]
    public function it_handles_sales_filter_options_query(): void
    {
        $mockReadModel = $this->createMock(SalesAnalyticsReadModelInterface::class);
        $expectedFilters = new SalesFilterOptionsDto(
            categories: [['id' => 'cat-1', 'name' => 'Tires']],
            regions: [['id' => 'reg-1', 'name' => 'Moscow', 'code' => 'MSK']],
            minDate: '2025-01-01',
            maxDate: '2025-12-31',
        );

        $mockReadModel->expects(self::once())
            ->method('getFilterOptions')
            ->with('ws-1')
            ->willReturn($expectedFilters);

        $handler = new GetSalesFilterOptionsHandler($mockReadModel);
        $result = $handler->handle(new GetSalesFilterOptionsQuery('ws-1'));

        self::assertSame('2025-01-01', $result->minDate);
        self::assertCount(1, $result->categories);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=SalesAnalyticsApplicationTest`
Expected: FAIL with missing classes

- [ ] **Step 3: Implement Application DTOs, Queries, Handlers, and Contract**

Create DTOs in `backend/app/Modules/SalesAnalytics/Application/Dtos/`:
- `SalesFilterCriteriaDto`: `(public ?string $dateFrom, public ?string $dateTo, public ?string $categoryId, public ?string $regionId)`
- `SalesSummaryDto`: `(public float $totalRevenue, public int $orderCount, public float $averageOrderValue, public float $grossProfit, public float $marginRate)`
- `SalesTrendPointDto`: `(public string $date, public float $revenue, public int $orderCount)`
- `SalesCategoryBreakdownDto`: `(public string $categoryId, public string $categoryName, public float $revenue, public int $orderCount, public float $revenueShare)`
- `SalesRegionBreakdownDto`: `(public string $regionId, public string $regionName, public string $regionCode, public float $revenue, public int $orderCount, public float $revenueShare)`
- `SalesOverviewDto`: `(public SalesSummaryDto $summary, public array $trend, public array $categories, public array $regions)`
- `SalesFilterOptionsDto`: `(public array $categories, public array $regions, public string $minDate, public string $maxDate)`

Create Contract `backend/app/Modules/SalesAnalytics/Application/Contracts/SalesAnalyticsReadModelInterface.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Application\Contracts;

use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;

interface SalesAnalyticsReadModelInterface
{
    public function getSalesOverview(string $workspaceId, SalesFilterCriteriaDto $criteria): SalesOverviewDto;

    public function getFilterOptions(string $workspaceId): SalesFilterOptionsDto;
}
```

Create Queries and Handlers in `backend/app/Modules/SalesAnalytics/Application/Queries/`:
- `GetSalesOverviewQuery`: `(public string $workspaceId, public ?string $dateFrom = null, public ?string $dateTo = null, public ?string $categoryId = null, public ?string $regionId = null)`
- `GetSalesOverviewHandler`: creates `DateRange::create($query->dateFrom, $query->dateTo)`, instantiates `SalesFilterCriteriaDto`, calls `readModel->getSalesOverview(...)`.
- `GetSalesFilterOptionsQuery`: `(public string $workspaceId)`
- `GetSalesFilterOptionsHandler`: calls `readModel->getFilterOptions($query->workspaceId)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=SalesAnalyticsApplicationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/SalesAnalytics/Application backend/tests/Unit/Modules/SalesAnalytics/Application
git commit -m "feat(sales-analytics): add Application layer DTOs, Queries, Handlers, and ReadModel interface"
```

---

### Task 4: Backend SalesAnalytics Infrastructure Layer (PostgreSQL Aggregation Read Model & Module Binding)

**Files:**
- Create: `backend/app/Modules/SalesAnalytics/Infrastructure/Persistence/PostgresSalesAnalyticsReadModel.php`
- Create: `backend/app/Modules/SalesAnalytics/Infrastructure/Persistence/InMemorySalesAnalyticsReadModel.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Create: `backend/tests/Unit/Modules/SalesAnalytics/Infrastructure/SalesAnalyticsReadModelTest.php`

**Interfaces:**
- Consumes: Database connection via `Illuminate\Support\Facades\DB`, `fact_order_items`, `fact_orders`, `dim_categories`, `dim_regions`, `dim_dates`
- Produces: Implementation of `SalesAnalyticsReadModelInterface` registered in Laravel service container

- [ ] **Step 1: Write failing read model tests**

Create `backend/tests/Unit/Modules/SalesAnalytics/Infrastructure/SalesAnalyticsReadModelTest.php`:
Assert that `InMemorySalesAnalyticsReadModel` satisfies `SalesAnalyticsReadModelInterface`, returns consistent data, and `PostgresSalesAnalyticsReadModel` generates valid SQL aggregations without memory leaks.

```php
<?php

namespace Tests\Unit\Modules\SalesAnalytics\Infrastructure;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\InMemorySalesAnalyticsReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SalesAnalyticsReadModelTest extends TestCase
{
    #[Test]
    public function in_memory_read_model_satisfies_interface(): void
    {
        $readModel = new InMemorySalesAnalyticsReadModel;
        self::assertInstanceOf(SalesAnalyticsReadModelInterface::class, $readModel);

        $overview = $readModel->getSalesOverview('ws-1', new SalesFilterCriteriaDto(null, null, null, null));
        self::assertGreaterThanOrEqual(0, $overview->summary->orderCount);

        $filters = $readModel->getFilterOptions('ws-1');
        self::assertNotEmpty($filters->minDate);
        self::assertNotEmpty($filters->maxDate);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=SalesAnalyticsReadModelTest`
Expected: FAIL with class not found

- [ ] **Step 3: Implement PostgresSalesAnalyticsReadModel and InMemorySalesAnalyticsReadModel**

Create `backend/app/Modules/SalesAnalytics/Infrastructure/Persistence/InMemorySalesAnalyticsReadModel.php`:
Provides deterministic mock data for test environments.

Create `backend/app/Modules/SalesAnalytics/Infrastructure/Persistence/PostgresSalesAnalyticsReadModel.php`:
Uses `DB::table('fact_order_items')` to run purely database-side aggregations:
1. `getSalesOverview(string $workspaceId, SalesFilterCriteriaDto $criteria): SalesOverviewDto`
   - Apply base filter: `where('workspace_id', $workspaceId)`
   - If `$criteria->dateFrom`, `where('order_date', '>=', $criteria->dateFrom)`
   - If `$criteria->dateTo`, `where('order_date', '<=', $criteria->dateTo)`
   - If `$criteria->categoryId`, `where('category_id', $criteria->categoryId)`
   - If `$criteria->regionId`, `where('region_id', $criteria->regionId)`
   - Summary query:
     `SELECT COALESCE(SUM(total_price), 0) as revenue, COUNT(DISTINCT order_id) as order_count, COALESCE(SUM(gross_profit), 0) as gross_profit FROM fact_order_items WHERE ...`
     Computes AOV via `SalesMetrics::calculateAov` and margin rate via `SalesMetrics::calculateMarginRate`.
   - Trend query:
     `SELECT order_date as date, COALESCE(SUM(total_price), 0) as revenue, COUNT(DISTINCT order_id) as order_count FROM fact_order_items WHERE ... GROUP BY order_date ORDER BY order_date ASC`
   - Categories breakdown query:
     `SELECT c.id as category_id, c.name as category_name, COALESCE(SUM(i.total_price), 0) as revenue, COUNT(DISTINCT i.order_id) as order_count FROM fact_order_items i JOIN dim_categories c ON c.id = i.category_id WHERE ... GROUP BY c.id, c.name ORDER BY revenue DESC`
     Computes `revenue_share` using `SalesMetrics::calculateShare($row->revenue, $totalRevenue)`.
   - Regions breakdown query:
     `SELECT r.id as region_id, r.name as region_name, r.code as region_code, COALESCE(SUM(i.total_price), 0) as revenue, COUNT(DISTINCT i.order_id) as order_count FROM fact_order_items i JOIN dim_regions r ON r.id = i.region_id WHERE ... GROUP BY r.id, r.name, r.code ORDER BY revenue DESC`
     Computes `revenue_share` using `SalesMetrics::calculateShare($row->revenue, $totalRevenue)`.
2. `getFilterOptions(string $workspaceId): SalesFilterOptionsDto`
   - Categories: `DB::table('dim_categories')->where('workspace_id', $workspaceId)->select('id', 'name')->orderBy('name')->get()`
   - Regions: `DB::table('dim_regions')->where('workspace_id', $workspaceId)->select('id', 'name', 'code')->orderBy('name')->get()`
   - Dates: `DB::table('fact_orders')->where('workspace_id', $workspaceId)->selectRaw('MIN(order_date) as min_date, MAX(order_date) as max_date')->first()`

Register in `backend/app/Providers/AppServiceProvider.php`:
```php
        $this->app->singleton(SalesAnalyticsReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemorySalesAnalyticsReadModel;
            }

            return new PostgresSalesAnalyticsReadModel;
        });
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=SalesAnalyticsReadModelTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/SalesAnalytics/Infrastructure backend/app/Providers/AppServiceProvider.php backend/tests/Unit/Modules/SalesAnalytics/Infrastructure
git commit -m "feat(sales-analytics): implement PostgreSQL read model and container bindings"
```

---

### Task 5: Backend SalesAnalytics Presentation Layer (Controller, Request Validation, API Routes & Tests)

**Files:**
- Create: `backend/app/Modules/SalesAnalytics/Presentation/Controllers/SalesAnalyticsController.php`
- Create: `backend/app/Modules/SalesAnalytics/Presentation/Requests/GetSalesOverviewRequest.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Modules/SalesAnalytics/SalesAnalyticsApiTest.php`

**Interfaces:**
- Consumes: `GetSalesOverviewHandler`, `GetSalesFilterOptionsHandler`, `WorkspaceAccessGuard`
- Produces: HTTP endpoints `GET /api/v1/analytics/sales/overview` and `GET /api/v1/analytics/sales/filters`

- [ ] **Step 1: Write failing Feature/API tests**

Create `backend/tests/Feature/Modules/SalesAnalytics/SalesAnalyticsApiTest.php`:
Tests authentication (401), authorization across workspaces (403), parameter validation (422), and valid 200 responses matching OpenAPI schema:

```php
<?php

namespace Tests\Feature\Modules\SalesAnalytics;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class SalesAnalyticsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);

        $user1 = new User(new UserId('user-1'), 'elena@autobi.internal', 'Elena Rostova');
        $userRepo->save($user1);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws1);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Lecar Wholesale', 'lecar-wholesale');
        $wsRepo->save($ws2);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/analytics/sales/overview')->assertStatus(401);
    }

    public function test_forbidden_workspace_access_returns_403(): void
    {
        $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-2')
            ->getJson('/api/v1/analytics/sales/overview')
            ->assertStatus(403);
    }

    public function test_invalid_date_parameters_return_422(): void
    {
        $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-1')
            ->getJson('/api/v1/analytics/sales/overview?date_from=invalid-date')
            ->assertStatus(422);
    }

    public function test_authenticated_user_can_fetch_sales_overview(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-1')
            ->getJson('/api/v1/analytics/sales/overview');

        $response->assertOk()
            ->assertJsonStructure([
                'summary' => ['total_revenue', 'order_count', 'average_order_value', 'gross_profit', 'margin_rate'],
                'trend' => [['date', 'revenue', 'order_count']],
                'categories' => [['category_id', 'category_name', 'revenue', 'order_count', 'revenue_share']],
                'regions' => [['region_id', 'region_name', 'region_code', 'revenue', 'order_count', 'revenue_share']],
            ]);
    }

    public function test_authenticated_user_can_fetch_sales_filter_options(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-1')
            ->getJson('/api/v1/analytics/sales/filters');

        $response->assertOk()
            ->assertJsonStructure([
                'categories' => [['id', 'name']],
                'regions' => [['id', 'name', 'code']],
                'min_date',
                'max_date',
            ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=SalesAnalyticsApiTest`
Expected: FAIL with 404 (routes not registered yet)

- [ ] **Step 3: Implement Controller, Form Request, and Routes**

Create `backend/app/Modules/SalesAnalytics/Presentation/Requests/GetSalesOverviewRequest.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetSalesOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'category_id' => ['nullable', 'string', 'max:64'],
            'region_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
```

Create `backend/app/Modules/SalesAnalytics/Presentation/Controllers/SalesAnalyticsController.php`:
- Resolves workspace context: if `X-Workspace-Id` header is passed, validates access with `WorkspaceAccessGuard::assertAccess($userId, $requestedWs)`. If not passed, retrieves the user's default/first workspace.
- For `overview`: passes validated parameters to `GetSalesOverviewHandler`, returns JSON response formatted strictly according to OpenAPI `SalesOverviewResponse`.
- For `filters`: passes workspace ID to `GetSalesFilterOptionsHandler`, returns JSON response formatted according to `SalesFilterOptionsResponse`.

Register in `backend/routes/api.php`:
```php
use App\Modules\SalesAnalytics\Presentation\Controllers\SalesAnalyticsController;

// Inside AuthenticateUserIdMiddleware group:
Route::get('/analytics/sales/overview', [SalesAnalyticsController::class, 'overview']);
Route::get('/analytics/sales/filters', [SalesAnalyticsController::class, 'filters']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=SalesAnalyticsApiTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/SalesAnalytics/Presentation backend/routes/api.php backend/tests/Feature/Modules/SalesAnalytics
git commit -m "feat(sales-analytics): add presentation controller, request validation, and routes"
```

---

### Task 6: Frontend Sales Analytics API Gateway & Contract Integration

**Files:**
- Create: `frontend/src/features/sales-analytics/api/sales-gateway.ts`
- Create: `frontend/src/features/sales-analytics/api/sales-gateway.test.ts`

**Interfaces:**
- Consumes: `analyticsClient` from `frontend/src/shared/api/analytics-client.ts`, OpenAPI types from `frontend/src/shared/api/generated/schema.ts`
- Produces: `salesGateway.getOverview(userId, workspaceId?, filters?)`, `salesGateway.getFilterOptions(userId, workspaceId?)`

- [ ] **Step 1: Write failing gateway unit test**

Create `frontend/src/features/sales-analytics/api/sales-gateway.test.ts`:
```typescript
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { salesGateway } from './sales-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    GET: vi.fn(),
  },
}))

describe('salesGateway', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches sales overview with filters and headers', async () => {
    const mockOverview = {
      summary: {
        total_revenue: 1250000,
        order_count: 320,
        average_order_value: 3906.25,
        gross_profit: 375000,
        margin_rate: 0.3,
      },
      trend: [{ date: '2025-01-01', revenue: 45000, order_count: 12 }],
      categories: [
        {
          category_id: 'cat-tires',
          category_name: 'Шины',
          revenue: 600000,
          order_count: 150,
          revenue_share: 0.48,
        },
      ],
      regions: [
        {
          region_id: 'reg-msk',
          region_name: 'Москва',
          region_code: 'MSK',
          revenue: 750000,
          order_count: 190,
          revenue_share: 0.6,
        },
      ],
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockOverview,
      error: undefined,
      response: new Response(),
    } as any)

    const result = await salesGateway.getOverview('user-1', 'ws-1', {
      dateFrom: '2025-01-01',
      dateTo: '2025-01-31',
      categoryId: 'cat-tires',
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith('/analytics/sales/overview', {
      params: {
        query: {
          date_from: '2025-01-01',
          date_to: '2025-01-31',
          category_id: 'cat-tires',
          region_id: undefined,
        },
      },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result.summary.total_revenue).toBe(1250000)
    expect(result.categories[0].category_name).toBe('Шины')
  })

  it('throws error when request fails', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: undefined,
      error: { message: 'Unauthorized', code: 'FORBIDDEN' },
      response: new Response(),
    } as any)

    await expect(salesGateway.getOverview('user-1', 'ws-1')).rejects.toThrow(
      'Unauthorized',
    )
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test sales-gateway.test.ts`
Expected: FAIL with module not found

- [ ] **Step 3: Implement sales gateway**

Create `frontend/src/features/sales-analytics/api/sales-gateway.ts`:
```typescript
import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type SalesOverview = components['schemas']['SalesOverviewResponse']
export type SalesSummary = components['schemas']['SalesSummary']
export type SalesTrendPoint = components['schemas']['SalesTrendPoint']
export type SalesCategoryBreakdown =
  components['schemas']['SalesCategoryBreakdown']
export type SalesRegionBreakdown = components['schemas']['SalesRegionBreakdown']
export type SalesFilterOptions =
  components['schemas']['SalesFilterOptionsResponse']

export interface SalesFilterParams {
  dateFrom?: string
  dateTo?: string
  categoryId?: string
  regionId?: string
}

export const salesGateway = {
  async getOverview(
    userId: string,
    workspaceId?: string,
    filters?: SalesFilterParams,
  ): Promise<SalesOverview> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET(
      '/analytics/sales/overview',
      {
        params: {
          query: {
            date_from: filters?.dateFrom,
            date_to: filters?.dateTo,
            category_id: filters?.categoryId,
            region_id: filters?.regionId,
          },
        },
        headers,
      },
    )

    if (error || !data) {
      throw new Error(error?.message ?? 'Failed to load sales overview')
    }

    return data
  },

  async getFilterOptions(
    userId: string,
    workspaceId?: string,
  ): Promise<SalesFilterOptions> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET(
      '/analytics/sales/filters',
      {
        headers,
      },
    )

    if (error || !data) {
      throw new Error(error?.message ?? 'Failed to load sales filter options')
    }

    return data
  },
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test sales-gateway.test.ts`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/sales-analytics/api
git commit -m "feat(sales-analytics): add sales gateway client using typed OpenAPI schema"
```

---

### Task 7: Frontend Sales Analytics UI Components (KPI Cards, Trend Chart, Breakdowns, Filters Bar)

**Files:**
- Create: `frontend/src/features/sales-analytics/ui/sales-kpi-cards.tsx`
- Create: `frontend/src/features/sales-analytics/ui/sales-trend-chart.tsx`
- Create: `frontend/src/features/sales-analytics/ui/sales-category-breakdown.tsx`
- Create: `frontend/src/features/sales-analytics/ui/sales-regional-breakdown.tsx`
- Create: `frontend/src/features/sales-analytics/ui/sales-filters-bar.tsx`
- Create: `frontend/src/features/sales-analytics/ui/sales-components.test.tsx`

**Interfaces:**
- Consumes: DTO types from `sales-gateway.ts`
- Produces: Presentation components with zero external chart library dependencies, responsive CSS & clean accessible SVG markup

- [ ] **Step 1: Write failing UI component tests**

Create `frontend/src/features/sales-analytics/ui/sales-components.test.tsx`:
Tests rendering of KPI cards, trend chart SVG, category breakdown percentages, and filter interactions.

```typescript
import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import React from 'react'
import { SalesKpiCards } from './sales-kpi-cards'
import { SalesCategoryBreakdownView } from './sales-category-breakdown'
import { SalesRegionalBreakdownView } from './sales-regional-breakdown'
import { SalesFiltersBar } from './sales-filters-bar'

describe('Sales Analytics UI Components', () => {
  it('renders KPI cards with correct metric values', () => {
    render(
      <SalesKpiCards
        summary={{
          total_revenue: 1500000,
          order_count: 250,
          average_order_value: 6000,
          gross_profit: 450000,
          margin_rate: 0.3,
        }}
      />,
    )

    expect(screen.getByText('Выручка')).toBeDefined()
    expect(screen.getByText('Заказы')).toBeDefined()
    expect(screen.getByText('Средний чек')).toBeDefined()
    expect(screen.getByText('Маржинальность')).toBeDefined()
    expect(screen.getByText('250')).toBeDefined()
    expect(screen.getByText('30.0%')).toBeDefined()
  })

  it('renders category breakdown items and percentage bars', () => {
    render(
      <SalesCategoryBreakdownView
        categories={[
          {
            category_id: 'cat-1',
            category_name: 'Аккумуляторы',
            revenue: 300000,
            order_count: 50,
            revenue_share: 0.2,
          },
        ]}
      />,
    )

    expect(screen.getByText('Аккумуляторы')).toBeDefined()
    expect(screen.getByText('20.0%')).toBeDefined()
  })

  it('triggers filter changes on user selection', () => {
    const onFilterChange = vi.fn()
    render(
      <SalesFiltersBar
        filterOptions={{
          categories: [{ id: 'cat-1', name: 'Масла' }],
          regions: [{ id: 'reg-1', name: 'Москва', code: 'MSK' }],
          min_date: '2025-01-01',
          max_date: '2025-12-31',
        }}
        activeFilters={{}}
        onFilterChange={onFilterChange}
      />,
    )

    const categorySelect = screen.getByLabelText('Категория')
    fireEvent.change(categorySelect, { target: { value: 'cat-1' } })

    expect(onFilterChange).toHaveBeenCalledWith(
      expect.objectContaining({ categoryId: 'cat-1' }),
    )
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test sales-components.test.tsx`
Expected: FAIL with components not found

- [ ] **Step 3: Implement UI presentation components**

Create:
1. `frontend/src/features/sales-analytics/ui/sales-kpi-cards.tsx`:
   - Formats numbers in rubles (RUB) and Russian locale.
   - 4 card grid: Выручка, Заказы, Средний чек, Маржинальность (gross_profit + margin_rate%).
2. `frontend/src/features/sales-analytics/ui/sales-trend-chart.tsx`:
   - Pure SVG responsive time-series chart with gradient fill under curve / bar visualization.
   - Shows dates on X axis, values on Y axis.
3. `frontend/src/features/sales-analytics/ui/sales-category-breakdown.tsx`:
   - List/table of categories sorted by revenue share with progress bar indicator and order count.
4. `frontend/src/features/sales-analytics/ui/sales-regional-breakdown.tsx`:
   - List/table of regions sorted by revenue share with region code badge and progress bar.
5. `frontend/src/features/sales-analytics/ui/sales-filters-bar.tsx`:
   - Filter controls: Date range (from, to), Category select, Region select, and "Сбросить фильтры" button.

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test sales-components.test.tsx`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/sales-analytics/ui
git commit -m "feat(sales-analytics): create KPI cards, trend chart, breakdowns, and filter controls"
```

---

### Task 8: Frontend Sales Analytics Dashboard & App Page Integration (States, Integration Tests)

**Files:**
- Create: `frontend/src/features/sales-analytics/ui/sales-dashboard.tsx`
- Modify: `frontend/app/page.tsx`
- Modify: `frontend/app/globals.css`
- Create: `frontend/src/features/sales-analytics/ui/sales-dashboard.test.tsx`

**Interfaces:**
- Consumes: `WorkspaceContextBar`, `salesGateway`, UI components from Task 7
- Produces: Integrated Sales Analytics Dashboard with loading, empty, and error states, embedded into main application page

- [ ] **Step 1: Write failing dashboard integration test**

Create `frontend/src/features/sales-analytics/ui/sales-dashboard.test.tsx`:
Tests loading state, data display, empty state (no data matching filters), and error recovery.

```typescript
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import React from 'react'
import { SalesDashboard } from './sales-dashboard'
import { salesGateway } from '../api/sales-gateway'

vi.mock('../api/sales-gateway', () => ({
  salesGateway: {
    getOverview: vi.fn(),
    getFilterOptions: vi.fn(),
  },
}))

describe('SalesDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders loading skeleton then displays dashboard data', async () => {
    vi.mocked(salesGateway.getFilterOptions).mockResolvedValueOnce({
      categories: [{ id: 'cat-1', name: 'Шины' }],
      regions: [{ id: 'reg-1', name: 'Москва', code: 'MSK' }],
      min_date: '2025-01-01',
      max_date: '2025-12-31',
    })

    vi.mocked(salesGateway.getOverview).mockResolvedValueOnce({
      summary: {
        total_revenue: 500000,
        order_count: 100,
        average_order_value: 5000,
        gross_profit: 150000,
        margin_rate: 0.3,
      },
      trend: [{ date: '2025-01-01', revenue: 50000, order_count: 10 }],
      categories: [
        {
          category_id: 'cat-1',
          category_name: 'Шины',
          revenue: 500000,
          order_count: 100,
          revenue_share: 1.0,
        },
      ],
      regions: [
        {
          region_id: 'reg-1',
          region_name: 'Москва',
          region_code: 'MSK',
          revenue: 500000,
          order_count: 100,
          revenue_share: 1.0,
        },
      ],
    })

    render(<SalesDashboard userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByTestId('sales-dashboard-loading')).toBeDefined()

    await waitFor(() => {
      expect(screen.getByText('Аналитика продаж')).toBeDefined()
      expect(screen.getByText('500 000 ₽')).toBeDefined()
    })
  })

  it('renders empty state when order count is zero', async () => {
    vi.mocked(salesGateway.getFilterOptions).mockResolvedValueOnce({
      categories: [],
      regions: [],
      min_date: '2025-01-01',
      max_date: '2025-12-31',
    })

    vi.mocked(salesGateway.getOverview).mockResolvedValueOnce({
      summary: {
        total_revenue: 0,
        order_count: 0,
        average_order_value: 0,
        gross_profit: 0,
        margin_rate: 0,
      },
      trend: [],
      categories: [],
      regions: [],
    })

    render(<SalesDashboard userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(
        screen.getByText('Нет данных о продажах за выбранный период'),
      ).toBeDefined()
    })
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test sales-dashboard.test.tsx`
Expected: FAIL with module not found

- [ ] **Step 3: Implement SalesDashboard and integrate into HomePage**

Create `frontend/src/features/sales-analytics/ui/sales-dashboard.tsx`:
- State management for active filters (`dateFrom`, `dateTo`, `categoryId`, `regionId`).
- `useEffect` fetching overview whenever filters or `workspaceId` change.
- Loading skeleton with `data-testid="sales-dashboard-loading"`.
- Error banner with retry button.
- Empty state with guidance when `summary.order_count === 0`.
- Comprehensive dashboard view arranging KPI cards, trend chart, and 2-column layout for category & regional breakdowns.

Update `frontend/app/page.tsx`:
Mount `SalesDashboard` passing `userId={demoUserId}` and `workspaceId={workspaceContext?.workspace.id ?? 'ws-1'}`.

Update `frontend/app/globals.css`:
Add styles for dashboard layout grid, KPI cards, breakdown progress bars, chart container, filter bar inputs and selects.

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test sales-dashboard.test.tsx`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/sales-analytics/ui/sales-dashboard.tsx frontend/app/page.tsx frontend/app/globals.css frontend/src/features/sales-analytics/ui/sales-dashboard.test.tsx
git commit -m "feat(sales-analytics): integrate reactive sales dashboard into main application page"
```

---

### Task 9: End-to-End Verification Checkpoint (`make check`, Docker integration, update roadmap)

**Files:**
- Modify: `scripts/verify-integration.sh`
- Modify: `docs/roadmap/04-sales-analytics.md`
- Modify: `docs/roadmap/ROADMAP.md`

**Interfaces:**
- Consumes: Entire vertical slice across backend, database, OpenAPI, and frontend
- Produces: Green CI checks (`make check`), verified live Docker integration test (`scripts/verify-integration.sh`), and roadmap Phase 4 completion record

- [ ] **Step 1: Extend integration verification script**

Update `scripts/verify-integration.sh` to add checks for the sales analytics API:
1. Fetch `/api/v1/analytics/sales/filters` with `X-User-Id: user-1` and `X-Workspace-Id: ws-1`, assert 200 and categories/regions presence.
2. Fetch `/api/v1/analytics/sales/overview` with `X-User-Id: user-1` and `X-Workspace-Id: ws-1`, assert 200, `total_revenue > 0`, and `order_count > 0`.
3. Check that cross-workspace access to sales analytics returns 403.
4. Verify frontend page renders 'Аналитика продаж' and KPI cards.

- [ ] **Step 2: Run all linters, static analyzers, and test suites**

Run: `make check`
Expected:
- Redocly lint: 0 errors
- OpenAPI TypeScript generation: clean
- Frontend lint: 0 warnings/errors
- Frontend format check: clean
- Frontend typecheck: clean
- Frontend Vitest tests: all PASS
- Frontend Next.js build: successfully compiled
- Backend Composer validate: valid
- Backend Pint: passed
- Backend PHPStan: 0 errors
- Backend PHPUnit: all PASS (Domain, Application, Infrastructure, Presentation, Architecture, ApiContract)

- [ ] **Step 3: Run live Docker integration verification**

Run: `make integration`
Expected:
- Service health ok
- Access boundary ok
- Sales analytics API endpoints return valid aggregated data from PostgreSQL demo dataset
- Multi-tenancy cross-workspace isolation verified
- Frontend renders dashboard and KPIs

- [ ] **Step 4: Update roadmap documents**

Update `docs/roadmap/04-sales-analytics.md`:
- Fill in "Прогресс" and "Проверка завершения" with verification commands, dates, and results.
Update `docs/roadmap/ROADMAP.md`:
- Mark `- [x] [Phase 4 — Sales Analytics Vertical Slice](04-sales-analytics.md)`.

- [ ] **Step 5: Commit**

```bash
git add scripts/verify-integration.sh docs/roadmap/04-sales-analytics.md docs/roadmap/ROADMAP.md
git commit -m "chore: complete Phase 4 Sales Analytics Vertical Slice and pass integration checkpoint"
```
