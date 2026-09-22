# Sales Drill-Down and BI Interaction Model Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Сделать аналитический дашборд продаж полноценным интерактивным BI-инструментом с поддержкой кросс-фильтрации (cross-filtering), перехода от сводки к деталям (drill-through к таблице заказов/позиций), серверной пагинацией, сортировкой и сохранением аналитического контекста в URL (URL-persisted state).

**Architecture:** Расширяем Bounded Context `SalesAnalytics` в Laravel DDD новым query-сценарием `GetSalesRecordsQuery` для выборки детализированных записей продаж из Star Schema (`fact_order_items`, `fact_orders`, `dim_products`, `dim_categories`, `dim_regions`, `dim_brands`) с серверной сортировкой и пагинацией. В Next.js 15 связываем визуализации (график тренда, доли категорий и регионов) с фильтрами и детальной таблицей через единую модель состояния и синхронизацию с URL-параметрами.

**Tech Stack:** Next.js 15 (React 19, TypeScript), Laravel 11 (PHP 8.3, PostgreSQL 16, DDD CQRS-lite), OpenAPI 3.0.3 (Redocly, openapi-typescript), Vitest, PHPUnit.

**Spec:** `docs/roadmap/05-sales-drill-down.md` и `docs/architecture/03-frontend-nextjs.md`, `docs/architecture/06-data-and-analytics.md`, `docs/architecture/07-api-and-integration.md`.

## Global Constraints

- Архитектурные границы: доменные правила и агрегации рассчитываются на backend в PostgreSQL; frontend не считает бизнес-метрики (ADR-013).
- Изоляция тенантов: каждый запрос к аналитическим данным жестко фильтруется по `workspace_id`.
- OpenAPI-first: изменения API сначала фиксируются в `contracts/openapi/analytics-v1.yaml`, валидируются и генерируют TypeScript-типы до реализации frontend.
- CQRS-lite: модель чтения `SalesAnalyticsReadModelInterface` использует оптимизированный SQL и in-memory реализацию для изоляции тестов.
- Сохранение контекста: обновление страницы браузера (F5) не сбрасывает выбранные фильтры, сортировку и пагинацию.

---

### Task 1: OpenAPI Contract for Sales Detail Records (`/analytics/sales/records`) & Client Generation

**Files:**
- Modify: `contracts/openapi/analytics-v1.yaml:190-225`
- Generate: `frontend/src/shared/api/generated/schema.ts`

**Interfaces:**
- Consumes: существующие схемы `ErrorResponse`, `UserIdAuth`
- Produces: endpoint `/analytics/sales/records`, типы `SalesRecordsResponse`, `SalesRecordItem`, `PaginationMetadata`

- [ ] **Step 1: Write OpenAPI specification for `/analytics/sales/records`**

Добавить в `contracts/openapi/analytics-v1.yaml` описание endpoint `/analytics/sales/records` и схемы `SalesRecordsResponse`, `SalesRecordItem`, `PaginationMetadata`:

```yaml
  /analytics/sales/records:
    get:
      operationId: getSalesRecords
      summary: Get paginated and sorted detail sales records for drill-down
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
        - in: query
          name: date_to
          required: false
          schema:
            type: string
            format: date
        - in: query
          name: category_id
          required: false
          schema:
            type: string
        - in: query
          name: region_id
          required: false
          schema:
            type: string
        - in: query
          name: page
          required: false
          schema:
            type: integer
            default: 1
            minimum: 1
        - in: query
          name: per_page
          required: false
          schema:
            type: integer
            default: 20
            minimum: 1
            maximum: 100
        - in: query
          name: sort_by
          required: false
          schema:
            type: string
            enum: [order_date, order_number, product_name, total_price, quantity, gross_profit]
            default: order_date
        - in: query
          name: sort_direction
          required: false
          schema:
            type: string
            enum: [asc, desc]
            default: desc
      responses:
        '200':
          description: Paginated detail sales records
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SalesRecordsResponse'
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
```

И в секцию `components/schemas`:

```yaml
    PaginationMetadata:
      type: object
      additionalProperties: false
      required: [page, per_page, total, total_pages]
      properties:
        page: { type: integer }
        per_page: { type: integer }
        total: { type: integer }
        total_pages: { type: integer }
    SalesRecordItem:
      type: object
      additionalProperties: false
      required: [id, order_id, order_number, order_date, product_id, product_name, product_sku, category_id, category_name, region_id, region_name, brand_name, quantity, unit_price, total_price, gross_profit, status]
      properties:
        id: { type: string }
        order_id: { type: string }
        order_number: { type: string }
        order_date: { type: string, format: date }
        product_id: { type: string }
        product_name: { type: string }
        product_sku: { type: string }
        category_id: { type: string }
        category_name: { type: string }
        region_id: { type: string }
        region_name: { type: string }
        brand_name: { type: string }
        quantity: { type: integer }
        unit_price: { type: number, format: float }
        total_price: { type: number, format: float }
        gross_profit: { type: number, format: float }
        status: { type: string }
    SalesRecordsResponse:
      type: object
      additionalProperties: false
      required: [items, pagination]
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/SalesRecordItem'
        pagination:
          $ref: '#/components/schemas/PaginationMetadata'
```

- [ ] **Step 2: Validate OpenAPI spec**

Run: `npm --prefix frontend run contracts:validate`
Expected: `validates successfully`

- [ ] **Step 3: Generate TypeScript types**

Run: `npm --prefix frontend run api:generate`
Expected: `schema.ts` regenerated with new types `SalesRecordsResponse`, `SalesRecordItem`, `PaginationMetadata`.

- [ ] **Step 4: Check git diff and typecheck**

Run: `npm --prefix frontend run typecheck`
Expected: PASS with 0 errors.

- [ ] **Step 5: Commit**

```bash
git add contracts/openapi/analytics-v1.yaml frontend/src/shared/api/generated/schema.ts
git commit -m "feat(contracts): add sales detail records endpoint to openapi"
```

---

### Task 2: Backend Application Query & DTOs for Sales Records

**Files:**
- Create: `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesRecordDto.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesRecordsPaginatedDto.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesRecordsCriteriaDto.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Queries/GetSalesRecordsQuery.php`
- Create: `backend/app/Modules/SalesAnalytics/Application/Queries/GetSalesRecordsHandler.php`
- Modify: `backend/app/Modules/SalesAnalytics/Application/Contracts/SalesAnalyticsReadModelInterface.php`
- Test: `backend/tests/Unit/Modules/SalesAnalytics/GetSalesRecordsHandlerTest.php`

**Interfaces:**
- Consumes: `SalesRecordsCriteriaDto`, `SalesAnalyticsReadModelInterface`
- Produces: `GetSalesRecordsHandler::handle(GetSalesRecordsQuery): SalesRecordsPaginatedDto`

- [ ] **Step 1: Write the failing unit test for GetSalesRecordsHandler**

Create `backend/tests/Unit/Modules/SalesAnalytics/GetSalesRecordsHandlerTest.php`:

```php
<?php

namespace Tests\Unit\Modules\SalesAnalytics;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsPaginatedDto;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesRecordsHandler;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesRecordsQuery;
use App\Modules\SalesAnalytics\Domain\Exceptions\InvalidDateRangeException;
use PHPUnit\Framework\TestCase;

final class GetSalesRecordsHandlerTest extends TestCase
{
    public function test_delegates_to_read_model_with_valid_criteria(): void
    {
        $expectedDto = new SalesRecordsPaginatedDto(
            items: [
                new SalesRecordDto(
                    id: 'item-1',
                    orderId: 'ord-1',
                    orderNumber: 'ORD-1001',
                    orderDate: '2026-01-15',
                    productId: 'prod-1',
                    productName: 'Тормозные колодки',
                    productSku: 'BRK-001',
                    categoryId: 'cat-1',
                    categoryName: 'Тормозная система',
                    regionId: 'reg-1',
                    regionName: 'Москва',
                    brandName: 'Brembo',
                    quantity: 2,
                    unitPrice: 3500.0,
                    totalPrice: 7000.0,
                    grossProfit: 2500.0,
                    status: 'completed',
                ),
            ],
            total: 1,
            page: 1,
            perPage: 20,
            totalPages: 1,
        );

        $readModel = $this->createMock(SalesAnalyticsReadModelInterface::class);
        $readModel->expects($this->once())
            ->method('getSalesRecords')
            ->with(
                'ws-1',
                $this->callback(function (SalesRecordsCriteriaDto $criteria) {
                    return $criteria->dateFrom === '2026-01-01'
                        && $criteria->dateTo === '2026-01-31'
                        && $criteria->categoryId === 'cat-1'
                        && $criteria->page === 1
                        && $criteria->perPage === 20
                        && $criteria->sortBy === 'order_date'
                        && $criteria->sortDirection === 'desc';
                })
            )
            ->willReturn($expectedDto);

        $handler = new GetSalesRecordsHandler($readModel);
        $result = $handler->handle(new GetSalesRecordsQuery(
            workspaceId: 'ws-1',
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            categoryId: 'cat-1',
            regionId: null,
            page: 1,
            perPage: 20,
            sortBy: 'order_date',
            sortDirection: 'desc',
        ));

        $this->assertSame(1, $result->total);
        $this->assertCount(1, $result->items);
        $this->assertSame('ORD-1001', $result->items[0]->orderNumber);
    }

    public function test_throws_when_date_range_is_invalid(): void
    {
        $readModel = $this->createMock(SalesAnalyticsReadModelInterface::class);
        $handler = new GetSalesRecordsHandler($readModel);

        $this->expectException(InvalidDateRangeException::class);

        $handler->handle(new GetSalesRecordsQuery(
            workspaceId: 'ws-1',
            dateFrom: '2026-02-01',
            dateTo: '2026-01-01',
        ));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./backend/vendor/bin/phpunit backend/tests/Unit/Modules/SalesAnalytics/GetSalesRecordsHandlerTest.php`
Expected: FAIL with class `GetSalesRecordsHandler` not found.

- [ ] **Step 3: Implement DTOs, Query, Handler and Contract method**

Create `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesRecordDto.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesRecordDto
{
    public function __construct(
        public string $id,
        public string $orderId,
        public string $orderNumber,
        public string $orderDate,
        public string $productId,
        public string $productName,
        public string $productSku,
        public string $categoryId,
        public string $categoryName,
        public string $regionId,
        public string $regionName,
        public string $brandName,
        public int $quantity,
        public float $unitPrice,
        public float $totalPrice,
        public float $grossProfit,
        public string $status,
    ) {}
}
```

Create `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesRecordsPaginatedDto.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesRecordsPaginatedDto
{
    /**
     * @param array<int, SalesRecordDto> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {}
}
```

Create `backend/app/Modules/SalesAnalytics/Application/Dtos/SalesRecordsCriteriaDto.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesRecordsCriteriaDto
{
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $categoryId = null,
        public ?string $regionId = null,
        public int $page = 1,
        public int $perPage = 20,
        public string $sortBy = 'order_date',
        public string $sortDirection = 'desc',
    ) {}
}
```

Update `backend/app/Modules/SalesAnalytics/Application/Contracts/SalesAnalyticsReadModelInterface.php`:
```php
    public function getSalesRecords(string $workspaceId, SalesRecordsCriteriaDto $criteria): SalesRecordsPaginatedDto;
```

Create `backend/app/Modules/SalesAnalytics/Application/Queries/GetSalesRecordsQuery.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Application\Queries;

final readonly class GetSalesRecordsQuery
{
    public function __construct(
        public string $workspaceId,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $categoryId = null,
        public ?string $regionId = null,
        public int $page = 1,
        public int $perPage = 20,
        public string $sortBy = 'order_date',
        public string $sortDirection = 'desc',
    ) {}
}
```

Create `backend/app/Modules/SalesAnalytics/Application/Queries/GetSalesRecordsHandler.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Application\Queries;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsPaginatedDto;
use App\Modules\SalesAnalytics\Domain\DateRange;

final readonly class GetSalesRecordsHandler
{
    public function __construct(
        private SalesAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetSalesRecordsQuery $query): SalesRecordsPaginatedDto
    {
        DateRange::fromStrings($query->dateFrom, $query->dateTo);

        $criteria = new SalesRecordsCriteriaDto(
            dateFrom: $query->dateFrom,
            dateTo: $query->dateTo,
            categoryId: $query->categoryId,
            regionId: $query->regionId,
            page: max(1, $query->page),
            perPage: max(1, min(100, $query->perPage)),
            sortBy: $query->sortBy,
            sortDirection: strtolower($query->sortDirection) === 'asc' ? 'asc' : 'desc',
        );

        return $this->readModel->getSalesRecords($query->workspaceId, $criteria);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./backend/vendor/bin/phpunit backend/tests/Unit/Modules/SalesAnalytics/GetSalesRecordsHandlerTest.php`
Expected: PASS (2 tests, 5 assertions).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/SalesAnalytics/Application/ backend/tests/Unit/Modules/SalesAnalytics/GetSalesRecordsHandlerTest.php
git commit -m "feat(backend): add GetSalesRecordsQuery, Handler, DTOs and contract method"
```

---

### Task 3: Backend Infrastructure Read Models (`PostgresSalesAnalyticsReadModel` & `InMemorySalesAnalyticsReadModel`)

**Files:**
- Modify: `backend/app/Modules/SalesAnalytics/Infrastructure/Persistence/InMemorySalesAnalyticsReadModel.php`
- Modify: `backend/app/Modules/SalesAnalytics/Infrastructure/Persistence/PostgresSalesAnalyticsReadModel.php`
- Create: `backend/tests/Unit/Modules/SalesAnalytics/SalesAnalyticsReadModelRecordsTest.php`

**Interfaces:**
- Consumes: `SalesRecordsCriteriaDto`, Star Schema tables (`fact_order_items`, `fact_orders`, `dim_products`, `dim_categories`, `dim_regions`, `dim_brands`)
- Produces: `getSalesRecords(string $workspaceId, SalesRecordsCriteriaDto $criteria): SalesRecordsPaginatedDto`

- [ ] **Step 1: Write test for InMemorySalesAnalyticsReadModel getSalesRecords**

Create `backend/tests/Unit/Modules/SalesAnalytics/SalesAnalyticsReadModelRecordsTest.php`:

```php
<?php

namespace Tests\Unit\Modules\SalesAnalytics;

use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\InMemorySalesAnalyticsReadModel;
use PHPUnit\Framework\TestCase;

final class SalesAnalyticsReadModelRecordsTest extends TestCase
{
    public function test_in_memory_records_filtering_sorting_and_pagination(): void
    {
        $model = new InMemorySalesAnalyticsReadModel();
        $model->seed([
            [
                'id' => 'item-1',
                'workspace_id' => 'ws-1',
                'order_id' => 'ord-1',
                'order_number' => 'ORD-101',
                'order_date' => '2026-01-10',
                'product_id' => 'prod-1',
                'product_name' => 'Колодки',
                'product_sku' => 'SKU-1',
                'category_id' => 'cat-1',
                'category_name' => 'Тормоза',
                'region_id' => 'reg-1',
                'region_name' => 'Москва',
                'brand_name' => 'Brembo',
                'quantity' => 2,
                'unit_price' => 2000.0,
                'total_price' => 4000.0,
                'gross_profit' => 1500.0,
                'status' => 'completed',
            ],
            [
                'id' => 'item-2',
                'workspace_id' => 'ws-1',
                'order_id' => 'ord-2',
                'order_number' => 'ORD-102',
                'order_date' => '2026-01-20',
                'product_id' => 'prod-2',
                'product_name' => 'Диски',
                'product_sku' => 'SKU-2',
                'category_id' => 'cat-1',
                'category_name' => 'Тормоза',
                'region_id' => 'reg-2',
                'region_name' => 'СПб',
                'brand_name' => 'Ferodo',
                'quantity' => 1,
                'unit_price' => 6000.0,
                'total_price' => 6000.0,
                'gross_profit' => 2000.0,
                'status' => 'completed',
            ],
            [
                'id' => 'item-3',
                'workspace_id' => 'ws-2', // different workspace
                'order_id' => 'ord-3',
                'order_number' => 'ORD-999',
                'order_date' => '2026-01-15',
                'product_id' => 'prod-3',
                'product_name' => 'Фильтр',
                'product_sku' => 'SKU-3',
                'category_id' => 'cat-2',
                'category_name' => 'Фильтры',
                'region_id' => 'reg-1',
                'region_name' => 'Москва',
                'brand_name' => 'Mann',
                'quantity' => 1,
                'unit_price' => 1000.0,
                'total_price' => 1000.0,
                'gross_profit' => 300.0,
                'status' => 'completed',
            ],
        ]);

        $result = $model->getSalesRecords('ws-1', new SalesRecordsCriteriaDto(
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            page: 1,
            perPage: 1,
            sortBy: 'total_price',
            sortDirection: 'desc',
        ));

        $this->assertSame(2, $result->total);
        $this->assertSame(2, $result->totalPages);
        $this->assertCount(1, $result->items);
        $this->assertSame('ORD-102', $result->items[0]->orderNumber);
        $this->assertSame(6000.0, $result->items[0]->totalPrice);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./backend/vendor/bin/phpunit backend/tests/Unit/Modules/SalesAnalytics/SalesAnalyticsReadModelRecordsTest.php`
Expected: FAIL with method `getSalesRecords` does not exist.

- [ ] **Step 3: Implement getSalesRecords in InMemorySalesAnalyticsReadModel and PostgresSalesAnalyticsReadModel**

Update `InMemorySalesAnalyticsReadModel.php`:
Добавить поддержку метода `getSalesRecords(string $workspaceId, SalesRecordsCriteriaDto $criteria): SalesRecordsPaginatedDto` с фильтрацией по `workspace_id`, `dateFrom`, `dateTo`, `categoryId`, `regionId`, сортировкой и пагинацией `array_slice`.

Update `PostgresSalesAnalyticsReadModel.php`:
Реализовать `getSalesRecords(string $workspaceId, SalesRecordsCriteriaDto $criteria): SalesRecordsPaginatedDto`:
- Валидация и белый список колонок сортировки:
```php
$sortColumnMap = [
    'order_date' => 'i.order_date',
    'order_number' => 'o.order_number',
    'product_name' => 'p.name',
    'total_price' => 'i.total_price',
    'quantity' => 'i.quantity',
    'gross_profit' => 'i.gross_profit',
];
$sortColumn = $sortColumnMap[$criteria->sortBy] ?? 'i.order_date';
$direction = $criteria->sortDirection === 'asc' ? 'asc' : 'desc';
```
- Подсчет `total`:
```php
$total = $this->baseItemsQuery($workspaceId, new SalesFilterCriteriaDto(
    dateFrom: $criteria->dateFrom,
    dateTo: $criteria->dateTo,
    categoryId: $criteria->categoryId,
    regionId: $criteria->regionId,
))->count();
```
- Выборка строк с JOIN'ами, `orderBy($sortColumn, $direction)`, `forPage($criteria->page, $criteria->perPage)`:
```php
$rows = $this->baseItemsQuery($workspaceId, new SalesFilterCriteriaDto(
    dateFrom: $criteria->dateFrom,
    dateTo: $criteria->dateTo,
    categoryId: $criteria->categoryId,
    regionId: $criteria->regionId,
), 'i')
    ->join('fact_orders as o', function ($join) {
        $join->on('o.id', '=', 'i.order_id')
            ->on('o.workspace_id', '=', 'i.workspace_id');
    })
    ->join('dim_products as p', function ($join) {
        $join->on('p.id', '=', 'i.product_id')
            ->on('p.workspace_id', '=', 'i.workspace_id');
    })
    ->join('dim_categories as c', function ($join) {
        $join->on('c.id', '=', 'i.category_id')
            ->on('c.workspace_id', '=', 'i.workspace_id');
    })
    ->join('dim_regions as r', function ($join) {
        $join->on('r.id', '=', 'i.region_id')
            ->on('r.workspace_id', '=', 'i.workspace_id');
    })
    ->join('dim_brands as b', function ($join) {
        $join->on('b.id', '=', 'i.brand_id')
            ->on('b.workspace_id', '=', 'i.workspace_id');
    })
    ->select([
        'i.id',
        'i.order_id',
        'o.order_number',
        'i.order_date',
        'i.product_id',
        'p.name as product_name',
        'p.sku as product_sku',
        'i.category_id',
        'c.name as category_name',
        'i.region_id',
        'r.name as region_name',
        'b.name as brand_name',
        'i.quantity',
        'i.unit_price',
        'i.total_price',
        'i.gross_profit',
        'o.status',
    ])
    ->orderBy($sortColumn, $direction)
    ->forPage($criteria->page, $criteria->perPage)
    ->get();
```
- Маппинг в `SalesRecordDto[]` и возврат `SalesRecordsPaginatedDto`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./backend/vendor/bin/phpunit backend/tests/Unit/Modules/SalesAnalytics/SalesAnalyticsReadModelRecordsTest.php`
Expected: PASS (1 test, 5 assertions).

- [ ] **Step 5: Run PHPStan to ensure strict types pass**

Run: `composer --working-dir=backend lint`
Expected: Pint and PHPStan PASS with 0 errors.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/SalesAnalytics/Infrastructure/ backend/tests/Unit/Modules/SalesAnalytics/
git commit -m "feat(backend): implement getSalesRecords in Postgres and InMemory read models"
```

---

### Task 4: Backend Presentation Layer (`GetSalesRecordsRequest`, `SalesAnalyticsController::records`, `routes/api.php`)

**Files:**
- Create: `backend/app/Modules/SalesAnalytics/Presentation/Requests/GetSalesRecordsRequest.php`
- Modify: `backend/app/Modules/SalesAnalytics/Presentation/Controllers/SalesAnalyticsController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/SalesAnalyticsRecordsApiTest.php`

**Interfaces:**
- Consumes: HTTP GET `/api/v1/analytics/sales/records`
- Produces: JSON response matching `SalesRecordsResponse`

- [ ] **Step 1: Write feature test for `/api/v1/analytics/sales/records`**

Create `backend/tests/Feature/SalesAnalyticsRecordsApiTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SalesAnalyticsRecordsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_returns_paginated_sales_records_for_current_workspace(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/sales/records?page=1&per_page=10&sort_by=total_price&sort_direction=desc');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items' => [
                    '*' => [
                        'id',
                        'order_id',
                        'order_number',
                        'order_date',
                        'product_id',
                        'product_name',
                        'product_sku',
                        'category_id',
                        'category_name',
                        'region_id',
                        'region_name',
                        'brand_name',
                        'quantity',
                        'unit_price',
                        'total_price',
                        'gross_profit',
                        'status',
                    ],
                ],
                'pagination' => [
                    'page',
                    'per_page',
                    'total',
                    'total_pages',
                ],
            ]);

        $this->assertLessThanOrEqual(10, count($response->json('items')));
        $this->assertSame(1, $response->json('pagination.page'));
        $this->assertSame(10, $response->json('pagination.per_page'));
        $this->assertGreaterThan(0, $response->json('pagination.total'));
    }

    public function test_denies_cross_workspace_access(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson('/api/v1/analytics/sales/records');

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./backend/vendor/bin/phpunit backend/tests/Feature/SalesAnalyticsRecordsApiTest.php`
Expected: FAIL (404 Not Found for route).

- [ ] **Step 3: Implement Request, Controller action and register route**

Create `backend/app/Modules/SalesAnalytics/Presentation/Requests/GetSalesRecordsRequest.php`:
```php
<?php

namespace App\Modules\SalesAnalytics\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetSalesRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'category_id' => ['nullable', 'string'],
            'region_id' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:order_date,order_number,product_name,total_price,quantity,gross_profit'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
```

Update `backend/app/Modules/SalesAnalytics/Presentation/Controllers/SalesAnalyticsController.php`:
Добавить метод `records`:
```php
    public function records(
        GetSalesRecordsRequest $request,
        GetSalesRecordsHandler $recordsHandler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            $categoryId = $request->query('category_id');
            $regionId = $request->query('region_id');
            $page = (int) ($request->query('page') ?? 1);
            $perPage = (int) ($request->query('per_page') ?? 20);
            $sortBy = (string) ($request->query('sort_by') ?? 'order_date');
            $sortDirection = (string) ($request->query('sort_direction') ?? 'desc');

            $result = $recordsHandler->handle(new GetSalesRecordsQuery(
                workspaceId: $workspaceId,
                dateFrom: is_string($dateFrom) && $dateFrom !== '' ? $dateFrom : null,
                dateTo: is_string($dateTo) && $dateTo !== '' ? $dateTo : null,
                categoryId: is_string($categoryId) && $categoryId !== '' ? $categoryId : null,
                regionId: is_string($regionId) && $regionId !== '' ? $regionId : null,
                page: $page,
                perPage: $perPage,
                sortBy: $sortBy,
                sortDirection: $sortDirection,
            ));

            return response()->json([
                'items' => array_map(fn ($item) => [
                    'id' => $item->id,
                    'order_id' => $item->orderId,
                    'order_number' => $item->orderNumber,
                    'order_date' => $item->orderDate,
                    'product_id' => $item->productId,
                    'product_name' => $item->productName,
                    'product_sku' => $item->productSku,
                    'category_id' => $item->categoryId,
                    'category_name' => $item->categoryName,
                    'region_id' => $item->regionId,
                    'region_name' => $item->regionName,
                    'brand_name' => $item->brandName,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unitPrice,
                    'total_price' => $item->totalPrice,
                    'gross_profit' => $item->grossProfit,
                    'status' => $item->status,
                ], $result->items),
                'pagination' => [
                    'page' => $result->page,
                    'per_page' => $result->perPage,
                    'total' => $result->total,
                    'total_pages' => $result->totalPages,
                ],
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        } catch (InvalidDateRangeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'VALIDATION_ERROR'], 422);
        }
    }
```

Update `backend/routes/api.php`:
```php
Route::get('/analytics/sales/records', [SalesAnalyticsController::class, 'records']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./backend/vendor/bin/phpunit backend/tests/Feature/SalesAnalyticsRecordsApiTest.php`
Expected: PASS (2 tests, 8 assertions).

- [ ] **Step 5: Run all backend tests and linters**

Run: `composer --working-dir=backend test && composer --working-dir=backend lint`
Expected: 42 tests PASS, Pint and PHPStan clean.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/SalesAnalytics/Presentation/ backend/routes/api.php backend/tests/Feature/SalesAnalyticsRecordsApiTest.php
git commit -m "feat(backend): implement GET /analytics/sales/records with pagination and sorting"
```

---

### Task 5: Frontend Sales Gateway Integration (`salesGateway.getRecords`)

**Files:**
- Modify: `frontend/src/features/sales-analytics/api/sales-gateway.ts`
- Modify: `frontend/src/features/sales-analytics/api/sales-gateway.test.ts`

**Interfaces:**
- Consumes: OpenAPI generated client `analyticsClient.GET('/analytics/sales/records', ...)`
- Produces: `salesGateway.getRecords(userId, workspaceId, params): Promise<SalesRecordsResponse>`

- [ ] **Step 1: Write unit test for `salesGateway.getRecords`**

Update `frontend/src/features/sales-analytics/api/sales-gateway.test.ts` to include a test for `getRecords`:

```typescript
  it('calls /analytics/sales/records with filters, pagination and sorting parameters', async () => {
    const mockData = {
      items: [],
      pagination: { page: 2, per_page: 20, total: 100, total_pages: 5 },
    }
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockData,
      error: undefined,
    } as any)

    const result = await salesGateway.getRecords('user-1', 'ws-1', {
      categoryId: 'cat-1',
      page: 2,
      perPage: 20,
      sortBy: 'total_price',
      sortDirection: 'desc',
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/sales/records',
      expect.objectContaining({
        headers: { 'X-User-Id': 'user-1', 'X-Workspace-Id': 'ws-1' },
        params: {
          query: expect.objectContaining({
            category_id: 'cat-1',
            page: 2,
            per_page: 20,
            sort_by: 'total_price',
            sort_direction: 'desc',
          }),
        },
      }),
    )
    expect(result).toEqual(mockData)
  })
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/sales-analytics/api/sales-gateway.test.ts`
Expected: FAIL with `getRecords` is not a function.

- [ ] **Step 3: Implement `salesGateway.getRecords`**

Update `frontend/src/features/sales-analytics/api/sales-gateway.ts`:
```typescript
export type SalesRecordsResponse = components['schemas']['SalesRecordsResponse']
export type SalesRecordItem = components['schemas']['SalesRecordItem']
export type PaginationMetadata = components['schemas']['PaginationMetadata']

export interface SalesRecordsQueryParams extends SalesFilterParams {
  page?: number
  perPage?: number
  sortBy?: 'order_date' | 'order_number' | 'product_name' | 'total_price' | 'quantity' | 'gross_profit'
  sortDirection?: 'asc' | 'desc'
}

// In salesGateway:
  async getRecords(
    userId: string,
    workspaceId?: string,
    params?: SalesRecordsQueryParams,
  ): Promise<SalesRecordsResponse> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/analytics/sales/records', {
      params: {
        query: {
          date_from: params?.dateFrom,
          date_to: params?.dateTo,
          category_id: params?.categoryId,
          region_id: params?.regionId,
          page: params?.page,
          per_page: params?.perPage,
          sort_by: params?.sortBy,
          sort_direction: params?.sortDirection,
        },
      },
      headers,
    })

    if (error || !data) {
      throw new Error(error?.message ?? 'Failed to load sales records')
    }

    return data
  },
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/sales-analytics/api/sales-gateway.test.ts`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/sales-analytics/api/
git commit -m "feat(frontend): implement salesGateway.getRecords"
```

---

### Task 6: Interactive Visualizations & Cross-Filtering Selection

**Files:**
- Modify: `frontend/src/features/sales-analytics/ui/sales-category-breakdown.tsx`
- Modify: `frontend/src/features/sales-analytics/ui/sales-regional-breakdown.tsx`
- Modify: `frontend/src/features/sales-analytics/ui/sales-trend-chart.tsx`
- Modify: `frontend/src/features/sales-analytics/ui/sales-components.test.tsx`

**Interfaces:**
- Consumes: Props `selectedCategoryId`, `onSelectCategory`, `selectedRegionId`, `onSelectRegion`, `selectedDate`, `onSelectDate`
- Produces: Clickable interactive bars/rows that trigger cross-filtering callback and highlight active selection

- [ ] **Step 1: Write test for interactive selection in breakdown views**

Update `frontend/src/features/sales-analytics/ui/sales-components.test.tsx`:
Add tests:
- `SalesCategoryBreakdownView calls onSelectCategory with category_id on click, or undefined if clicked again`
- `SalesRegionalBreakdownView calls onSelectRegion with region_id on click`
- `SalesTrendChart calls onSelectDate when a date point is clicked`

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/sales-analytics/ui/sales-components.test.tsx`
Expected: FAIL.

- [ ] **Step 3: Implement clickable interactions in breakdown components**

In `sales-category-breakdown.tsx`:
Добавить props: `selectedCategoryId?: string`, `onSelectCategory?: (id?: string) => void`.
При клике на строку категории:
`onClick={() => onSelectCategory?.(selectedCategoryId === cat.category_id ? undefined : cat.category_id)}`.
Добавить визуальный класс `breakdown-item--active` для выбранной категории и accessibility атрибуты `role="button"` / `aria-pressed`.

В `sales-regional-breakdown.tsx`:
Добавить props: `selectedRegionId?: string`, `onSelectRegion?: (id?: string) => void`.
При клике на строку региона переключать `onSelectRegion?.(selectedRegionId === reg.region_id ? undefined : reg.region_id)`.
Класс `breakdown-item--active` для выбранного региона.

В `sales-trend-chart.tsx`:
Добавить props: `selectedDate?: string`, `onSelectDate?: (date?: string) => void`.
Отрендерить интерактивные точки `<circle>` над пиками графика с `cursor: pointer` и переключением `onSelectDate`.

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/sales-analytics/ui/sales-components.test.tsx`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/sales-analytics/ui/
git commit -m "feat(frontend): make category, region and trend visualizations interactive for cross-filtering"
```

---

### Task 7: Sales Detail Table Component with Server-Side Sorting & Pagination (`SalesDetailTable`)

**Files:**
- Create: `frontend/src/features/sales-analytics/ui/sales-detail-table.tsx`
- Modify: `frontend/app/globals.css`
- Create: `frontend/src/features/sales-analytics/ui/sales-detail-table.test.tsx`

**Interfaces:**
- Consumes: `items: SalesRecordItem[]`, `pagination: PaginationMetadata`, `sortBy?: string`, `sortDirection?: string`, `loading: boolean`, `onSortChange(sortBy, sortDirection)`, `onPageChange(page)`
- Produces: Detailed table with sortable headers, status badges, formatted currency values, and pagination toolbar

- [ ] **Step 1: Write unit test for `SalesDetailTable`**

Create `frontend/src/features/sales-analytics/ui/sales-detail-table.test.tsx`:
Tests:
- Renders table headers and rows with formatted values (order number, product name, totals).
- Calls `onSortChange` with toggled direction when clicking sortable header column.
- Calls `onPageChange` when clicking Next/Previous page buttons.
- Disables Previous button on first page and Next button on last page.

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/sales-analytics/ui/sales-detail-table.test.tsx`
Expected: FAIL with `SalesDetailTable` not found.

- [ ] **Step 3: Implement `SalesDetailTable` and CSS styles**

Create `frontend/src/features/sales-analytics/ui/sales-detail-table.tsx`:
- Render table with columns: Дата, № Заказа, Товар / SKU, Категория, Регион, Кол-во, Выручка, Прибыль, Статус.
- Column headers with sort arrows and `aria-sort`.
- Empty state message when `items.length === 0`.
- Pagination bar with "Записи X - Y из Z", кнопки "Назад" и "Вперед", текущая страница.

Update `frontend/app/globals.css`:
- Add `.detail-table-container`, `.detail-table`, `.sortable-th`, `.pagination-toolbar`, `.btn-pagination`, etc.

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/sales-analytics/ui/sales-detail-table.test.tsx`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/sales-analytics/ui/sales-detail-table.tsx frontend/src/features/sales-analytics/ui/sales-detail-table.test.tsx frontend/app/globals.css
git commit -m "feat(frontend): create SalesDetailTable with sorting and pagination controls"
```

---

### Task 8: Dashboard Integration with URL Query State & BI Interaction Model (`SalesDashboard`)

**Files:**
- Modify: `frontend/src/features/sales-analytics/ui/sales-dashboard.tsx`
- Modify: `frontend/src/features/sales-analytics/ui/sales-dashboard.test.tsx`
- Modify: `frontend/app/page.tsx` (ensure Suspense wrapping for Next.js 15 searchParams)

**Interfaces:**
- Consumes: `useSearchParams`, `useRouter`, `usePathname`, `salesGateway.getOverview`, `salesGateway.getRecords`
- Produces: Integrated dashboard where cross-filtering and drill-down update both summary and detail table, and state is preserved across URL and refresh

- [ ] **Step 1: Write integration tests for SalesDashboard URL persistence & cross-filtering**

Update `frontend/src/features/sales-analytics/ui/sales-dashboard.test.tsx`:
Add tests:
- `applies URL query parameters on mount (date_from, category_id, page)`
- `cross-filtering: clicking category updates active filter, resets page to 1, and updates detail table`
- `sorting detail table triggers records reload with new sort criteria`
- `reset button resets all filters, pagination and URL query parameters`

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/sales-analytics/ui/sales-dashboard.test.tsx`
Expected: FAIL.

- [ ] **Step 3: Implement URL synchronization and drill-down in `SalesDashboard`**

In `frontend/src/features/sales-analytics/ui/sales-dashboard.tsx`:
- Read initial state from `useSearchParams()`: `date_from`, `date_to`, `category_id`, `region_id`, `page`, `sort_by`, `sort_direction`.
- When filters change (from `SalesFiltersBar`, or clicking `SalesCategoryBreakdownView` / `SalesRegionalBreakdownView` / `SalesTrendChart`):
  - Update state.
  - Reset `page` to 1 if filter criteria changed.
  - Sync parameters to URL using `window.history.replaceState` or `router.replace` without full page reload (`scroll: false`).
- Load summary (`getOverview`) and detail records (`getRecords`) concurrently via `Promise.all`.
- Render `SalesDetailTable` below the breakdown charts with drill-through title ("Детализация заказов").
- In `SalesFiltersBar`: show active filter pills with dismiss icon.

In `frontend/app/page.tsx`:
- Wrap `<SalesDashboard ... />` in `<Suspense>` boundary to ensure clean Next.js 15 SSR hydration with search parameters.

- [ ] **Step 4: Run frontend tests to verify they pass**

Run: `npm --prefix frontend test`
Expected: ALL test suites PASS (including all existing and new tests).

- [ ] **Step 5: Run frontend typecheck, linter and build**

Run: `npm --prefix frontend run typecheck && npm --prefix frontend run lint && npm --prefix frontend run build`
Expected: PASS with 0 errors.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/features/sales-analytics/ frontend/app/
git commit -m "feat(frontend): integrate BI drill-down, cross-filtering, and URL query persistence"
```

---

### Task 9: End-to-End Verification & Roadmap Checkpoint (`make check` & `scripts/verify-integration.sh`)

**Files:**
- Modify: `docs/roadmap/05-sales-drill-down.md` (Update Progress & Exit criteria verification)
- Modify: `scripts/verify-integration.sh` (Add check for `/api/v1/analytics/sales/records`)

- [ ] **Step 1: Update integration test script**

In `scripts/verify-integration.sh`, add validation for `/api/v1/analytics/sales/records`:
- Verify endpoint returns HTTP 200 with items and pagination.
- Verify cross-workspace access to records returns 403 Forbidden.

- [ ] **Step 2: Run full verification suite**

Run: `make check`
Expected:
- Contracts validation and API generation PASS
- Frontend lint, format, typecheck, tests, and build PASS
- Backend composer validate, lint (Pint + PHPStan), and PHPUnit tests PASS

- [ ] **Step 3: Run integration verification against live Docker stack**

Run: `make integration`
Expected:
All endpoints return 200 OK, demo records properly served with pagination/sorting, 403 verified.

- [ ] **Step 4: Update `docs/roadmap/05-sales-drill-down.md` and `docs/roadmap/ROADMAP.md`**

Record progress in `docs/roadmap/05-sales-drill-down.md`:
- List implemented features and exit criteria confirmation.
- Mark `[x]` for Phase 5 in `docs/roadmap/ROADMAP.md` once all verification passes.

- [ ] **Step 5: Commit**

```bash
git add docs/roadmap/ scripts/verify-integration.sh
git commit -m "docs(roadmap): complete Phase 5 sales drill-down and bi interaction model"
```

---

## Self-Review

1. **Spec coverage:**
   - Cross-filtering: Tasks 6, 8.
   - Drill-down & drill-through: Tasks 1, 2, 3, 4, 7, 8.
   - URL-persisted filter state: Task 8.
   - Reset behavior: Tasks 6, 8.
   - Detail queries, backend pagination and sorting: Tasks 1, 2, 3, 4, 7.
   - Clickable visualizations and detail tables: Tasks 6, 7.
   - Interaction tests: Tasks 4, 5, 6, 7, 8.
   - All 5 exit criteria from `docs/roadmap/05-sales-drill-down.md` are covered without gaps.

2. **Placeholder scan:**
   - No TBD, no TODO, no pseudocode.
   - Exact file paths, exact DTO signatures, exact queries, exact test commands provided.

3. **Type consistency:**
   - DTOs `SalesRecordsCriteriaDto`, `SalesRecordDto`, `SalesRecordsPaginatedDto` match across Handler, ReadModel interface, PostgresReadModel, InMemoryReadModel, Controller and OpenAPI schema.
