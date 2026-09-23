# Shared Filters and Saved Views (Phase 9) — OpenAPI Contract & Backend Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the foundational OpenAPI contract, pure DDD domain model, CQRS application layer, PostgreSQL persistence, and REST API for Phase 9 (Shared Filters and Saved Views), enabling users to create, read, update, delete, and default filter presets (saved views) for dashboards with unified filter semantics and strict workspace multi-tenancy enforcement.

**Architecture:** Domain-Driven Design (DDD) in Laravel 11 under bounded context `Dashboard` with strict layer boundaries (Domain, Application, Infrastructure, Presentation). Domain layer is 100% decoupled from Laravel/Illuminate. Persistence uses PostgreSQL table `dashboard_saved_views` with JSONB filter serialization, cascading deletion, and foreign keys. Presentation provides RESTful sub-resource endpoints `/dashboards/{dashboardId}/views` validated against `WorkspaceAccessGuard`. Contracts defined in OpenAPI 3.0.3 and TypeScript types generated for Next.js.

**Tech Stack:** Laravel 11 (PHP 8.3), PostgreSQL 16, OpenAPI 3.0.3, Redocly CLI, openapi-typescript 7, PHPUnit 11, Next.js 16 (React 19, TypeScript), Vitest.

**Spec:** `docs/roadmap/09-shared-filters-saved-views.md`, `docs/architecture/05-bounded-contexts.md`, `docs/architecture/07-api-and-integration.md`.

## Global Constraints

- Domain Layer in `App\Modules\Dashboard\Domain` MUST NOT depend on Laravel/Illuminate, framework helpers, or Infrastructure layers (`ArchitectureTest`).
- Bounded context `Dashboard` owns saved views, presets, and dashboard filter configurations (`docs/architecture/05-bounded-contexts.md`).
- Multi-tenancy isolation MUST be strictly enforced on every query and command via `workspace_id` through `GetCurrentWorkspaceHandler` / `WorkspaceAccessGuard` (cross-workspace requests return `403 Forbidden`).
- Backend contract MUST rely on unified semantic filter concepts (`date_range`, `date_from`, `date_to`, `category_id`, `region_id`, `warehouse_id`, `stock_health`), independent of UI widget implementation details.
- Incompatible filter combinations MUST be handled explicitly (validation rejects invalid date spans; domain filter resolver strips inapplicable dimensions when mapping to specific datasets).
- OpenAPI specification in `contracts/openapi/analytics-v1.yaml` is the single source of truth; code must be generated via `npm --prefix frontend run api:generate` (`docs/architecture/07-api-and-integration.md`, ADR-007).
- All commands and queries adhere to CQRS-lite (`docs/architecture/04-backend-laravel-ddd.md`, ADR-006).

---

### Task 1: OpenAPI 3.0.3 Contract for Saved Views & Unified Filter Semantics

**Files:**
- Modify: `contracts/openapi/analytics-v1.yaml`
- Modify: `backend/tests/Feature/ApiContractTest.php`
- Generated: `frontend/src/shared/api/generated/schema.ts`

**Interfaces:**
- Consumes: Existing OpenAPI schemas (`ErrorResponse`, `UserIdAuth`)
- Produces:
  - `GET /dashboards/{dashboardId}/views` -> `DashboardSavedViewListResponse`
  - `POST /dashboards/{dashboardId}/views` -> `DashboardSavedViewResponse` (with `CreateDashboardSavedViewRequest`)
  - `GET /dashboards/{dashboardId}/views/{viewId}` -> `DashboardSavedViewResponse`
  - `PUT /dashboards/{dashboardId}/views/{viewId}` -> `DashboardSavedViewResponse` (with `UpdateDashboardSavedViewRequest`)
  - `DELETE /dashboards/{dashboardId}/views/{viewId}` -> `204 No Content`
  - Schemas: `DashboardFilterValues`, `DashboardSavedView`, `DashboardSavedViewListResponse`, `DashboardSavedViewResponse`, `CreateDashboardSavedViewRequest`, `UpdateDashboardSavedViewRequest`
  - Updated `WidgetQueryConfig`: optional `filters` field referencing `DashboardFilterValues`

- [ ] **Step 1: Write failing test in `backend/tests/Feature/ApiContractTest.php`**

Add assertion method to `ApiContractTest.php`:
```php
    public function test_contract_contains_saved_views_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/dashboards/{dashboardId}/views', $contract['paths']);
        self::assertArrayHasKey('/dashboards/{dashboardId}/views/{viewId}', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('DashboardFilterValues', $schemas);
        self::assertArrayHasKey('DashboardSavedView', $schemas);
        self::assertArrayHasKey('DashboardSavedViewListResponse', $schemas);
        self::assertArrayHasKey('DashboardSavedViewResponse', $schemas);
        self::assertArrayHasKey('CreateDashboardSavedViewRequest', $schemas);
        self::assertArrayHasKey('UpdateDashboardSavedViewRequest', $schemas);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_saved_views_endpoints`
Expected: FAIL with "Failed asserting that an array has key '/dashboards/{dashboardId}/views'."

- [ ] **Step 3: Update `contracts/openapi/analytics-v1.yaml` and regenerate TypeScript types**

1. Add paths under `paths:` in `contracts/openapi/analytics-v1.yaml`:
```yaml
  /dashboards/{dashboardId}/views:
    get:
      tags:
        - Dashboards
      summary: List saved views and filter presets for a dashboard
      operationId: getDashboardSavedViews
      security:
        - UserIdAuth: []
      parameters:
        - name: dashboardId
          in: path
          required: true
          description: Dashboard identifier
          schema:
            type: string
        - name: X-Workspace-Id
          in: header
          required: false
          description: Active workspace identifier override
          schema:
            type: string
      responses:
        '200':
          description: List of saved views for the dashboard
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/DashboardSavedViewListResponse'
        '401':
          description: Unauthorized
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '403':
          description: Forbidden - user does not belong to workspace
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '404':
          description: Dashboard not found
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
    post:
      tags:
        - Dashboards
      summary: Create a saved view or filter preset for a dashboard
      operationId: createDashboardSavedView
      security:
        - UserIdAuth: []
      parameters:
        - name: dashboardId
          in: path
          required: true
          description: Dashboard identifier
          schema:
            type: string
        - name: X-Workspace-Id
          in: header
          required: false
          description: Active workspace identifier override
          schema:
            type: string
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateDashboardSavedViewRequest'
      responses:
        '201':
          description: Saved view created successfully
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/DashboardSavedViewResponse'
        '401':
          description: Unauthorized
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
        '404':
          description: Dashboard not found
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

  /dashboards/{dashboardId}/views/{viewId}:
    get:
      tags:
        - Dashboards
      summary: Get a specific saved view
      operationId: getDashboardSavedViewById
      security:
        - UserIdAuth: []
      parameters:
        - name: dashboardId
          in: path
          required: true
          schema:
            type: string
        - name: viewId
          in: path
          required: true
          schema:
            type: string
        - name: X-Workspace-Id
          in: header
          required: false
          schema:
            type: string
      responses:
        '200':
          description: Saved view details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/DashboardSavedViewResponse'
        '401':
          description: Unauthorized
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
        '404':
          description: Saved view or dashboard not found
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
    put:
      tags:
        - Dashboards
      summary: Update a saved view
      operationId: updateDashboardSavedView
      security:
        - UserIdAuth: []
      parameters:
        - name: dashboardId
          in: path
          required: true
          schema:
            type: string
        - name: viewId
          in: path
          required: true
          schema:
            type: string
        - name: X-Workspace-Id
          in: header
          required: false
          schema:
            type: string
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateDashboardSavedViewRequest'
      responses:
        '200':
          description: Saved view updated successfully
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/DashboardSavedViewResponse'
        '401':
          description: Unauthorized
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
        '404':
          description: Saved view or dashboard not found
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
    delete:
      tags:
        - Dashboards
      summary: Delete a saved view
      operationId: deleteDashboardSavedView
      security:
        - UserIdAuth: []
      parameters:
        - name: dashboardId
          in: path
          required: true
          schema:
            type: string
        - name: viewId
          in: path
          required: true
          schema:
            type: string
        - name: X-Workspace-Id
          in: header
          required: false
          schema:
            type: string
      responses:
        '204':
          description: Saved view deleted successfully
        '401':
          description: Unauthorized
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
        '404':
          description: Saved view or dashboard not found
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
```

2. Add schemas under `components/schemas:`:
```yaml
    DashboardFilterValues:
      type: object
      additionalProperties: false
      properties:
        date_range:
          type: string
          nullable: true
          enum: ['30d', '90d', '180d', '365d', 'all', 'custom']
        date_from:
          type: string
          format: date
          nullable: true
        date_to:
          type: string
          format: date
          nullable: true
        category_id:
          type: string
          nullable: true
        region_id:
          type: string
          nullable: true
        warehouse_id:
          type: string
          nullable: true
        stock_health:
          type: string
          nullable: true
          enum: [in_stock, low_stock, out_of_stock, overstock]
    DashboardSavedView:
      type: object
      additionalProperties: false
      required:
        - id
        - dashboard_id
        - name
        - filters
        - is_default
        - created_at
        - updated_at
      properties:
        id:
          type: string
        dashboard_id:
          type: string
        name:
          type: string
        filters:
          $ref: '#/components/schemas/DashboardFilterValues'
        is_default:
          type: boolean
        created_at:
          type: string
          format: date-time
        updated_at:
          type: string
          format: date-time
    DashboardSavedViewListResponse:
      type: object
      additionalProperties: false
      required:
        - items
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/DashboardSavedView'
    DashboardSavedViewResponse:
      type: object
      additionalProperties: false
      required:
        - view
      properties:
        view:
          $ref: '#/components/schemas/DashboardSavedView'
    CreateDashboardSavedViewRequest:
      type: object
      additionalProperties: false
      required:
        - name
        - filters
      properties:
        name:
          type: string
          minLength: 1
          maxLength: 100
        filters:
          $ref: '#/components/schemas/DashboardFilterValues'
        is_default:
          type: boolean
          default: false
    UpdateDashboardSavedViewRequest:
      type: object
      additionalProperties: false
      required:
        - name
        - filters
      properties:
        name:
          type: string
          minLength: 1
          maxLength: 100
        filters:
          $ref: '#/components/schemas/DashboardFilterValues'
        is_default:
          type: boolean
          default: false
```
And add `filters` to `WidgetQueryConfig`:
```yaml
        filters:
          $ref: '#/components/schemas/DashboardFilterValues'
```

3. Validate contract and generate TypeScript definitions:
```bash
npm --prefix frontend run contracts:validate
npm --prefix frontend run api:generate
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_saved_views_endpoints`
Expected: PASS (1 passed).

- [ ] **Step 5: Commit**

```bash
git add contracts/openapi/analytics-v1.yaml backend/tests/Feature/ApiContractTest.php frontend/src/shared/api/generated/schema.ts
git commit -m "feat(dashboard): define OpenAPI contract for saved views and shared filter semantics"
```

---

### Task 2: Domain Layer for Saved Views & Filter Values (Pure DDD)

**Files:**
- Create: `backend/app/Modules/Dashboard/Domain/SavedViewId.php`
- Create: `backend/app/Modules/Dashboard/Domain/DashboardFilters.php`
- Create: `backend/app/Modules/Dashboard/Domain/SavedView.php`
- Create: `backend/app/Modules/Dashboard/Domain/Repositories/SavedViewRepositoryInterface.php`
- Create: `backend/app/Modules/Dashboard/Domain/Exceptions/SavedViewNotFoundException.php`
- Create: `backend/app/Modules/Dashboard/Domain/Exceptions/InvalidFilterException.php`
- Test: `backend/tests/Unit/Modules/Dashboard/Domain/SavedViewDomainTest.php`

**Interfaces:**
- Consumes: `DashboardId`
- Produces:
  - `SavedViewId` value object with UUID generation and RFC 4122 compliance
  - `DashboardFilters` value object with date span validation and dataset compatibility resolver
  - `SavedView` entity with name, filters, default flag, and timestamp lifecycle
  - `SavedViewRepositoryInterface` repository contract
  - `SavedViewNotFoundException`, `InvalidFilterException` domain exceptions

- [ ] **Step 1: Write failing test in `backend/tests/Unit/Modules/Dashboard/Domain/SavedViewDomainTest.php`**

```php
<?php

namespace Tests\Unit\Modules\Dashboard\Domain;

use App\Modules\Dashboard\Domain\DashboardFilters;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\InvalidFilterException;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SavedViewDomainTest extends TestCase
{
    public function test_saved_view_id_validation_and_generation(): void
    {
        $id = SavedViewId::generate();
        self::assertNotEmpty($id->value());

        $sameId = new SavedViewId($id->value());
        self::assertTrue($id->equals($sameId));

        $this->expectException(InvalidArgumentException::class);
        new SavedViewId('invalid-uuid');
    }

    public function test_dashboard_filters_date_range_validation(): void
    {
        $validFilters = new DashboardFilters(
            dateRange: 'custom',
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            categoryId: 'cat-1',
            regionId: 'reg-1',
        );
        self::assertSame('2026-01-01', $validFilters->dateFrom);
        self::assertSame('2026-01-31', $validFilters->dateTo);

        $this->expectException(InvalidFilterException::class);
        new DashboardFilters(
            dateFrom: '2026-02-01',
            dateTo: '2026-01-01',
        );
    }

    public function test_dashboard_filters_dataset_compatibility_resolver(): void
    {
        $filters = new DashboardFilters(
            dateRange: '30d',
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            categoryId: 'cat-1',
            regionId: 'reg-1',
            warehouseId: 'wh-1',
            stockHealth: 'low_stock',
        );

        $salesFilters = $filters->forSalesDataset();
        self::assertSame('cat-1', $salesFilters->categoryId);
        self::assertSame('reg-1', $salesFilters->regionId);
        self::assertNull($salesFilters->warehouseId);
        self::assertNull($salesFilters->stockHealth);

        $inventoryFilters = $filters->forInventoryDataset();
        self::assertSame('wh-1', $inventoryFilters->warehouseId);
        self::assertSame('low_stock', $inventoryFilters->stockHealth);
        self::assertNull($inventoryFilters->regionId);
    }

    public function test_saved_view_lifecycle(): void
    {
        $dashboardId = DashboardId::generate();
        $viewId = SavedViewId::generate();
        $filters = new DashboardFilters(dateRange: '90d');

        $view = new SavedView(
            id: $viewId,
            dashboardId: $dashboardId,
            name: 'Default Q1 View',
            filters: $filters,
            isDefault: false,
        );

        self::assertSame('Default Q1 View', $view->name());
        self::assertFalse($view->isDefault());

        $view->rename('Updated Q1 View');
        self::assertSame('Updated Q1 View', $view->name());

        $view->markAsDefault(true);
        self::assertTrue($view->isDefault());

        $newFilters = new DashboardFilters(dateRange: '180d');
        $view->updateFilters($newFilters);
        self::assertSame('180d', $view->filters()->dateRange);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=SavedViewDomainTest`
Expected: FAIL with "Class App\Modules\Dashboard\Domain\SavedViewId not found".

- [ ] **Step 3: Implement domain classes**

1. `SavedViewId.php`:
```php
<?php

namespace App\Modules\Dashboard\Domain;

use InvalidArgumentException;

final readonly class SavedViewId
{
    private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(private string $value)
    {
        if (! preg_match(self::UUID_REGEX, $value)) {
            throw new InvalidArgumentException("Invalid UUID format for SavedViewId: '{$value}'");
        }
    }

    public static function generate(): self
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        $hex = bin2hex($bytes);
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );

        return new self($uuid);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

2. `InvalidFilterException.php`:
```php
<?php

namespace App\Modules\Dashboard\Domain\Exceptions;

use DomainException;

final class InvalidFilterException extends DomainException {}
```

3. `SavedViewNotFoundException.php`:
```php
<?php

namespace App\Modules\Dashboard\Domain\Exceptions;

use DomainException;

final class SavedViewNotFoundException extends DomainException {}
```

4. `DashboardFilters.php`:
```php
<?php

namespace App\Modules\Dashboard\Domain;

use App\Modules\Dashboard\Domain\Exceptions\InvalidFilterException;

final readonly class DashboardFilters
{
    public function __construct(
        public ?string $dateRange = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $categoryId = null,
        public ?string $regionId = null,
        public ?string $warehouseId = null,
        public ?string $stockHealth = null,
    ) {
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw new InvalidFilterException("date_from ({$dateFrom}) cannot be later than date_to ({$dateTo}).");
        }
    }

    public function forSalesDataset(): self
    {
        return new self(
            dateRange: $this->dateRange,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            categoryId: $this->categoryId,
            regionId: $this->regionId,
            warehouseId: null,
            stockHealth: null,
        );
    }

    public function forInventoryDataset(): self
    {
        return new self(
            dateRange: $this->dateRange,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            categoryId: $this->categoryId,
            regionId: null,
            warehouseId: $this->warehouseId,
            stockHealth: $this->stockHealth,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'date_range' => $this->dateRange,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'category_id' => $this->categoryId,
            'region_id' => $this->regionId,
            'warehouse_id' => $this->warehouseId,
            'stock_health' => $this->stockHealth,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dateRange: isset($data['date_range']) && is_string($data['date_range']) ? $data['date_range'] : null,
            dateFrom: isset($data['date_from']) && is_string($data['date_from']) ? $data['date_from'] : null,
            dateTo: isset($data['date_to']) && is_string($data['date_to']) ? $data['date_to'] : null,
            categoryId: isset($data['category_id']) && is_string($data['category_id']) ? $data['category_id'] : null,
            regionId: isset($data['region_id']) && is_string($data['region_id']) ? $data['region_id'] : null,
            warehouseId: isset($data['warehouse_id']) && is_string($data['warehouse_id']) ? $data['warehouse_id'] : null,
            stockHealth: isset($data['stock_health']) && is_string($data['stock_health']) ? $data['stock_health'] : null,
        );
    }
}
```

5. `SavedView.php`:
```php
<?php

namespace App\Modules\Dashboard\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class SavedView
{
    public function __construct(
        private readonly SavedViewId $id,
        private readonly DashboardId $dashboardId,
        private string $name,
        private DashboardFilters $filters,
        private bool $isDefault = false,
        private readonly ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->setName($name);
    }

    public function id(): SavedViewId
    {
        return $this->id;
    }

    public function dashboardId(): DashboardId
    {
        return $this->dashboardId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Saved view name cannot be empty.');
        }
        $this->name = $trimmed;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function rename(string $name): void
    {
        $this->setName($name);
    }

    public function filters(): DashboardFilters
    {
        return $this->filters;
    }

    public function updateFilters(DashboardFilters $filters): void
    {
        $this->filters = $filters;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function markAsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
```

6. `SavedViewRepositoryInterface.php`:
```php
<?php

namespace App\Modules\Dashboard\Domain\Repositories;

use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;

interface SavedViewRepositoryInterface
{
    public function findById(SavedViewId $id): ?SavedView;

    /**
     * @return list<SavedView>
     */
    public function findByDashboardId(DashboardId $dashboardId): array;

    public function save(SavedView $view): void;

    public function delete(SavedViewId $id): void;

    public function clearDefault(DashboardId $dashboardId, ?SavedViewId $exceptId = null): void;
}
```

- [ ] **Step 4: Run domain unit test and architecture test**

Run:
```bash
composer --working-dir=backend test -- --filter=SavedViewDomainTest
composer --working-dir=backend test -- --filter=ArchitectureTest
```
Expected: PASS (All tests pass; 0 forbidden dependencies in Domain layer).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/Dashboard/Domain backend/tests/Unit/Modules/Dashboard/Domain
git commit -m "feat(dashboard): add pure domain model for saved views and filter presets"
```

---

### Task 3: Application Layer (DTOs, CQRS Commands, Queries, and Handlers)

**Files:**
- Create: `backend/app/Modules/Dashboard/Application/Dtos/DashboardFiltersDto.php`
- Create: `backend/app/Modules/Dashboard/Application/Dtos/SavedViewDto.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/CreateSavedViewCommand.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/CreateSavedViewHandler.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/UpdateSavedViewCommand.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/UpdateSavedViewHandler.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/DeleteSavedViewCommand.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/DeleteSavedViewHandler.php`
- Create: `backend/app/Modules/Dashboard/Application/Queries/GetSavedViewsByDashboardQuery.php`
- Create: `backend/app/Modules/Dashboard/Application/Queries/GetSavedViewsByDashboardHandler.php`
- Create: `backend/app/Modules/Dashboard/Application/Queries/GetSavedViewByIdQuery.php`
- Create: `backend/app/Modules/Dashboard/Application/Queries/GetSavedViewByIdHandler.php`
- Test: `backend/tests/Unit/Modules/Dashboard/Application/SavedViewApplicationTest.php`

**Interfaces:**
- Consumes: `DashboardRepositoryInterface`, `SavedViewRepositoryInterface`, Domain entities
- Produces: Application CQRS handlers orchestrating saved view CRUD with tenant workspace verification and default view resolution

- [ ] **Step 1: Write failing application tests in `SavedViewApplicationTest.php`**

```php
<?php

namespace Tests\Unit\Modules\Dashboard\Application;

use App\Modules\Dashboard\Application\Commands\CreateSavedViewCommand;
use App\Modules\Dashboard\Application\Commands\CreateSavedViewHandler;
use App\Modules\Dashboard\Application\Commands\DeleteSavedViewCommand;
use App\Modules\Dashboard\Application\Commands\DeleteSavedViewHandler;
use App\Modules\Dashboard\Application\Commands\UpdateSavedViewCommand;
use App\Modules\Dashboard\Application\Commands\UpdateSavedViewHandler;
use App\Modules\Dashboard\Application\Dtos\DashboardFiltersDto;
use App\Modules\Dashboard\Application\Queries\GetSavedViewsByDashboardHandler;
use App\Modules\Dashboard\Application\Queries\GetSavedViewsByDashboardQuery;
use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Exceptions\SavedViewNotFoundException;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemoryDashboardRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemorySavedViewRepository;
use PHPUnit\Framework\TestCase;

final class SavedViewApplicationTest extends TestCase
{
    private InMemoryDashboardRepository $dashboardRepo;
    private InMemorySavedViewRepository $savedViewRepo;
    private Dashboard $dashboard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboardRepo = new InMemoryDashboardRepository;
        $this->savedViewRepo = new InMemorySavedViewRepository;

        $this->dashboard = new Dashboard(
            id: DashboardId::generate(),
            workspaceId: 'ws-1',
            title: 'Sales Dashboard',
        );
        $this->dashboardRepo->save($this->dashboard);
    }

    public function test_create_and_get_saved_views(): void
    {
        $createHandler = new CreateSavedViewHandler($this->dashboardRepo, $this->savedViewRepo);
        $getHandler = new GetSavedViewsByDashboardHandler($this->dashboardRepo, $this->savedViewRepo);

        $viewDto = $createHandler->handle(new CreateSavedViewCommand(
            workspaceId: 'ws-1',
            dashboardId: $this->dashboard->id()->value(),
            name: 'North Region View',
            filters: new DashboardFiltersDto(dateRange: '30d', regionId: 'reg-north'),
            isDefault: true,
        ));

        self::assertSame('North Region View', $viewDto->name);
        self::assertTrue($viewDto->isDefault);
        self::assertSame('30d', $viewDto->filters->dateRange);
        self::assertSame('reg-north', $viewDto->filters->regionId);

        $views = $getHandler->handle(new GetSavedViewsByDashboardQuery('ws-1', $this->dashboard->id()->value()));
        self::assertCount(1, $views);
        self::assertSame($viewDto->id, $views[0]->id);
    }

    public function test_cannot_access_dashboard_from_other_workspace(): void
    {
        $createHandler = new CreateSavedViewHandler($this->dashboardRepo, $this->savedViewRepo);

        $this->expectException(DashboardNotFoundException::class);
        $createHandler->handle(new CreateSavedViewCommand(
            workspaceId: 'ws-other',
            dashboardId: $this->dashboard->id()->value(),
            name: 'Hacked View',
            filters: new DashboardFiltersDto(dateRange: '30d'),
        ));
    }

    public function test_update_and_delete_saved_view(): void
    {
        $createHandler = new CreateSavedViewHandler($this->dashboardRepo, $this->savedViewRepo);
        $updateHandler = new UpdateSavedViewHandler($this->dashboardRepo, $this->savedViewRepo);
        $deleteHandler = new DeleteSavedViewHandler($this->dashboardRepo, $this->savedViewRepo);

        $view = $createHandler->handle(new CreateSavedViewCommand(
            workspaceId: 'ws-1',
            dashboardId: $this->dashboard->id()->value(),
            name: 'Initial View',
            filters: new DashboardFiltersDto(dateRange: '30d'),
        ));

        $updated = $updateHandler->handle(new UpdateSavedViewCommand(
            workspaceId: 'ws-1',
            dashboardId: $this->dashboard->id()->value(),
            viewId: $view->id,
            name: 'Renamed View',
            filters: new DashboardFiltersDto(dateRange: '90d'),
            isDefault: false,
        ));

        self::assertSame('Renamed View', $updated->name);
        self::assertSame('90d', $updated->filters->dateRange);

        $deleteHandler->handle(new DeleteSavedViewCommand('ws-1', $this->dashboard->id()->value(), $view->id));

        $this->expectException(SavedViewNotFoundException::class);
        $updateHandler->handle(new UpdateSavedViewCommand(
            workspaceId: 'ws-1',
            dashboardId: $this->dashboard->id()->value(),
            viewId: $view->id,
            name: 'Should Fail',
            filters: new DashboardFiltersDto(dateRange: '90d'),
        ));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=SavedViewApplicationTest`
Expected: FAIL with missing classes.

- [ ] **Step 3: Implement DTOs, Commands, Queries, and Handlers**

1. `DashboardFiltersDto.php`:
```php
<?php

namespace App\Modules\Dashboard\Application\Dtos;

final readonly class DashboardFiltersDto
{
    public function __construct(
        public ?string $dateRange = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $categoryId = null,
        public ?string $regionId = null,
        public ?string $warehouseId = null,
        public ?string $stockHealth = null,
    ) {}
}
```

2. `SavedViewDto.php`:
```php
<?php

namespace App\Modules\Dashboard\Application\Dtos;

final readonly class SavedViewDto
{
    public function __construct(
        public string $id,
        public string $dashboardId,
        public string $name,
        public DashboardFiltersDto $filters,
        public bool $isDefault,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}
}
```

3. `CreateSavedViewCommand.php` & `CreateSavedViewHandler.php`:
```php
<?php

namespace App\Modules\Dashboard\Application\Commands;

use App\Modules\Dashboard\Application\Dtos\DashboardFiltersDto;

final readonly class CreateSavedViewCommand
{
    public function __construct(
        public string $workspaceId,
        public string $dashboardId,
        public string $name,
        public DashboardFiltersDto $filters,
        public bool $isDefault = false,
    ) {}
}
```
And `CreateSavedViewHandler.php`:
```php
<?php

namespace App\Modules\Dashboard\Application\Commands;

use App\Modules\Dashboard\Application\Dtos\DashboardFiltersDto;
use App\Modules\Dashboard\Application\Dtos\SavedViewDto;
use App\Modules\Dashboard\Domain\DashboardFilters;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Repositories\SavedViewRepositoryInterface;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;
use DateTimeImmutable;

final readonly class CreateSavedViewHandler
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
        private SavedViewRepositoryInterface $savedViewRepository,
    ) {}

    public function handle(CreateSavedViewCommand $command): SavedViewDto
    {
        $dashboardId = new DashboardId($command->dashboardId);
        $dashboard = $this->dashboardRepository->findById($dashboardId);

        if ($dashboard === null || $dashboard->workspaceId() !== $command->workspaceId) {
            throw new DashboardNotFoundException("Dashboard not found: {$command->dashboardId}");
        }

        if ($command->isDefault) {
            $this->savedViewRepository->clearDefault($dashboardId);
        }

        $viewId = SavedViewId::generate();
        $domainFilters = new DashboardFilters(
            dateRange: $command->filters->dateRange,
            dateFrom: $command->filters->dateFrom,
            dateTo: $command->filters->dateTo,
            categoryId: $command->filters->categoryId,
            regionId: $command->filters->regionId,
            warehouseId: $command->filters->warehouseId,
            stockHealth: $command->filters->stockHealth,
        );

        $now = new DateTimeImmutable;
        $savedView = new SavedView(
            id: $viewId,
            dashboardId: $dashboardId,
            name: $command->name,
            filters: $domainFilters,
            isDefault: $command->isDefault,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->savedViewRepository->save($savedView);

        return new SavedViewDto(
            id: $savedView->id()->value(),
            dashboardId: $savedView->dashboardId()->value(),
            name: $savedView->name(),
            filters: new DashboardFiltersDto(
                dateRange: $savedView->filters()->dateRange,
                dateFrom: $savedView->filters()->dateFrom,
                dateTo: $savedView->filters()->dateTo,
                categoryId: $savedView->filters()->categoryId,
                regionId: $savedView->filters()->regionId,
                warehouseId: $savedView->filters()->warehouseId,
                stockHealth: $savedView->filters()->stockHealth,
            ),
            isDefault: $savedView->isDefault(),
            createdAt: $savedView->createdAt()?->format(DateTimeImmutable::ATOM),
            updatedAt: $savedView->updatedAt()?->format(DateTimeImmutable::ATOM),
        );
    }
}
```

4. Implement `UpdateSavedViewHandler`, `DeleteSavedViewHandler`, `GetSavedViewsByDashboardHandler`, `GetSavedViewByIdHandler` with identical workspace checks and domain mappings.

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=SavedViewApplicationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/Dashboard/Application backend/tests/Unit/Modules/Dashboard/Application
git commit -m "feat(dashboard): add application CQRS commands and queries for saved views"
```

---

### Task 4: Infrastructure Layer (PostgreSQL Migration, Eloquent Model, Repositories)

**Files:**
- Create: `backend/database/migrations/2026_09_23_000022_create_dashboard_saved_views_table.php`
- Create: `backend/app/Modules/Dashboard/Infrastructure/Persistence/Eloquent/Models/DashboardSavedViewModel.php`
- Modify: `backend/app/Modules/Dashboard/Infrastructure/Persistence/Eloquent/Models/DashboardModel.php`
- Create: `backend/app/Modules/Dashboard/Infrastructure/Persistence/Eloquent/Repositories/EloquentSavedViewRepository.php`
- Create: `backend/app/Modules/Dashboard/Infrastructure/Persistence/InMemory/InMemorySavedViewRepository.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Unit/Modules/Dashboard/Infrastructure/SavedViewRepositoryTest.php`

**Interfaces:**
- Consumes: PostgreSQL connection, `SavedViewRepositoryInterface`
- Produces:
  - Database table `dashboard_saved_views`
  - Eloquent mapping with JSONB filter serialization
  - In-memory repository for ultra-fast unit testing
  - Service container binding in `AppServiceProvider`

- [ ] **Step 1: Write failing repository test in `SavedViewRepositoryTest.php`**

```php
<?php

namespace Tests\Unit\Modules\Dashboard\Infrastructure;

use App\Modules\Dashboard\Domain\DashboardFilters;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemorySavedViewRepository;
use PHPUnit\Framework\TestCase;

final class SavedViewRepositoryTest extends TestCase
{
    private InMemorySavedViewRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InMemorySavedViewRepository;
    }

    public function test_crud_and_clear_default(): void
    {
        $dashboardId = DashboardId::generate();
        $view1 = new SavedView(
            id: SavedViewId::generate(),
            dashboardId: $dashboardId,
            name: 'View 1',
            filters: new DashboardFilters(dateRange: '30d'),
            isDefault: true,
        );
        $view2 = new SavedView(
            id: SavedViewId::generate(),
            dashboardId: $dashboardId,
            name: 'View 2',
            filters: new DashboardFilters(dateRange: '90d'),
            isDefault: false,
        );

        $this->repository->save($view1);
        $this->repository->save($view2);

        $found = $this->repository->findById($view1->id());
        self::assertNotNull($found);
        self::assertSame('View 1', $found->name());

        $list = $this->repository->findByDashboardId($dashboardId);
        self::assertCount(2, $list);

        $this->repository->clearDefault($dashboardId, $view2->id());
        $updated1 = $this->repository->findById($view1->id());
        self::assertFalse($updated1->isDefault());

        $this->repository->delete($view1->id());
        self::assertNull($this->repository->findById($view1->id()));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=SavedViewRepositoryTest`
Expected: FAIL with missing classes.

- [ ] **Step 3: Implement migration, Eloquent model, Eloquent and InMemory repositories**

1. Migration `2026_09_23_000022_create_dashboard_saved_views_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_saved_views', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('dashboard_id');
            $table->string('name', 100);
            $table->jsonb('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('dashboard_id')->references('id')->on('dashboards')->cascadeOnDelete();
            $table->index(['dashboard_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_saved_views');
    }
};
```

2. Eloquent Model `DashboardSavedViewModel.php`:
```php
<?php

namespace App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $dashboard_id
 * @property string $name
 * @property array<string, mixed> $filters
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DashboardModel $dashboard
 */
final class DashboardSavedViewModel extends Model
{
    protected $table = 'dashboard_saved_views';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<DashboardModel, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(DashboardModel::class, 'dashboard_id');
    }
}
```

3. Modify `DashboardModel.php` to add relation:
```php
    /**
     * @return HasMany<DashboardSavedViewModel, $this>
     */
    public function savedViews(): HasMany
    {
        return $this->hasMany(DashboardSavedViewModel::class, 'dashboard_id')
            ->orderBy('created_at', 'asc');
    }
```

4. `EloquentSavedViewRepository.php` & `InMemorySavedViewRepository.php`:
Implement mapping to/from `SavedView` domain entity, with `clearDefault()` utilizing DB transactions.

5. Register binding in `AppServiceProvider.php`:
```php
$this->app->bind(SavedViewRepositoryInterface::class, EloquentSavedViewRepository::class);
```

- [ ] **Step 4: Run repository tests and migrations**

Run:
```bash
composer --working-dir=backend test -- --filter=SavedViewRepositoryTest
php backend/artisan migrate --dry-run
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations backend/app/Modules/Dashboard/Infrastructure backend/app/Providers/AppServiceProvider.php backend/tests/Unit/Modules/Dashboard/Infrastructure
git commit -m "feat(dashboard): implement persistence layer for dashboard saved views"
```

---

### Task 5: Presentation Layer (Requests, DashboardSavedViewController, Routes)

**Files:**
- Create: `backend/app/Modules/Dashboard/Presentation/Requests/CreateSavedViewRequest.php`
- Create: `backend/app/Modules/Dashboard/Presentation/Requests/UpdateSavedViewRequest.php`
- Create: `backend/app/Modules/Dashboard/Presentation/Controllers/DashboardSavedViewController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Modules/Dashboard/DashboardSavedViewApiTest.php`

**Interfaces:**
- Consumes: `GetCurrentWorkspaceHandler`, CQRS commands and queries, HTTP JSON request payload
- Produces: `/dashboards/{dashboardId}/views` endpoints with tenant isolation and full validation

- [ ] **Step 1: Write failing Feature API test in `DashboardSavedViewApiTest.php`**

```php
<?php

namespace Tests\Feature\Modules\Dashboard;

use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardSavedViewApiTest extends TestCase
{
    use RefreshDatabase;

    private DashboardRepositoryInterface $dashboardRepo;
    private Dashboard $dashboardWs1;
    private Dashboard $dashboardWs2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->dashboardRepo = $this->app->make(DashboardRepositoryInterface::class);

        $this->dashboardWs1 = new Dashboard(
            id: DashboardId::generate(),
            workspaceId: 'ws-1',
            title: 'WS 1 Dashboard',
        );
        $this->dashboardRepo->save($this->dashboardWs1);

        $this->dashboardWs2 = new Dashboard(
            id: DashboardId::generate(),
            workspaceId: 'ws-2',
            title: 'WS 2 Dashboard',
        );
        $this->dashboardRepo->save($this->dashboardWs2);
    }

    public function test_saved_view_crud_flow_and_default_toggle(): void
    {
        // 1. Create View
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views", [
            'name' => 'Monthly Report View',
            'filters' => [
                'date_range' => '30d',
                'category_id' => 'cat-1',
            ],
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('view.name', 'Monthly Report View')
            ->assertJsonPath('view.is_default', true)
            ->assertJsonPath('view.filters.date_range', '30d');

        $viewId = $response->json('view.id');

        // 2. List Views
        $listRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views");

        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $viewId);

        // 3. Update View
        $updateRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->putJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views/{$viewId}", [
            'name' => 'Renamed View',
            'filters' => [
                'date_range' => '90d',
            ],
            'is_default' => false,
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('view.name', 'Renamed View')
            ->assertJsonPath('view.filters.date_range', '90d')
            ->assertJsonPath('view.is_default', false);

        // 4. Delete View
        $delRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->deleteJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views/{$viewId}");

        $delRes->assertStatus(204);

        // 5. Verify Not Found
        $getRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views/{$viewId}");

        $getRes->assertStatus(404);
    }

    public function test_workspace_isolation_enforced(): void
    {
        // user-2 cannot view or create views on ws-1 dashboard
        $response = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views");

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=DashboardSavedViewApiTest`
Expected: FAIL (404 route not found).

- [ ] **Step 3: Implement FormRequests, DashboardSavedViewController, and register routes**

1. `CreateSavedViewRequest.php` & `UpdateSavedViewRequest.php`:
Validate:
- `name`: string, required, min:1, max:100
- `filters`: array, required
- `filters.date_range`: nullable, string, in:30d,90d,180d,365d,all,custom
- `filters.date_from`: nullable, date
- `filters.date_to`: nullable, date, after_or_equal:filters.date_from
- `filters.category_id`: nullable, string
- `filters.region_id`: nullable, string
- `filters.warehouse_id`: nullable, string
- `filters.stock_health`: nullable, string, in:in_stock,low_stock,out_of_stock,overstock
- `is_default`: nullable, boolean

2. `DashboardSavedViewController.php`:
Implement:
- `index`: calls `GetSavedViewsByDashboardHandler`
- `store`: calls `CreateSavedViewHandler`
- `show`: calls `GetSavedViewByIdHandler`
- `update`: calls `UpdateSavedViewHandler`
- `destroy`: calls `DeleteSavedViewHandler`
Handle `UnauthorizedWorkspaceAccessException` (403), `WorkspaceNotFoundException` | `DashboardNotFoundException` | `SavedViewNotFoundException` (404), `InvalidFilterException` (422).

3. `backend/routes/api.php`:
Inside `Route::middleware(AuthenticateUserIdMiddleware::class)`:
```php
Route::get('/dashboards/{dashboardId}/views', [DashboardSavedViewController::class, 'index']);
Route::post('/dashboards/{dashboardId}/views', [DashboardSavedViewController::class, 'store']);
Route::get('/dashboards/{dashboardId}/views/{viewId}', [DashboardSavedViewController::class, 'show']);
Route::put('/dashboards/{dashboardId}/views/{viewId}', [DashboardSavedViewController::class, 'update']);
Route::delete('/dashboards/{dashboardId}/views/{viewId}', [DashboardSavedViewController::class, 'destroy']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=DashboardSavedViewApiTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/Dashboard/Presentation backend/routes/api.php backend/tests/Feature/Modules/Dashboard/DashboardSavedViewApiTest.php
git commit -m "feat(dashboard): add REST API endpoints and form requests for dashboard saved views"
```

---

### Task 6: Tenant Isolation, Filter Semantics & Full Verification

**Files:**
- Create: `backend/tests/Feature/Modules/Dashboard/DashboardFilterSemanticsTest.php`
- Modify: `scripts/verify-integration.sh`

**Interfaces:**
- Consumes: All endpoints and repositories
- Produces: Automated verification of tenant isolation, cross-dashboard boundary protection, dataset compatibility, and end-to-end integration check

- [ ] **Step 1: Write comprehensive semantics test in `DashboardFilterSemanticsTest.php`**

```php
<?php

namespace Tests\Feature\Modules\Dashboard;

use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardFilterSemanticsTest extends TestCase
{
    use RefreshDatabase;

    private DashboardRepositoryInterface $dashboardRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->dashboardRepo = $this->app->make(DashboardRepositoryInterface::class);
    }

    public function test_cannot_update_or_delete_view_belonging_to_another_dashboard(): void
    {
        $d1 = new Dashboard(id: DashboardId::generate(), workspaceId: 'ws-1', title: 'D1');
        $d2 = new Dashboard(id: DashboardId::generate(), workspaceId: 'ws-1', title: 'D2');
        $this->dashboardRepo->save($d1);
        $this->dashboardRepo->save($d2);

        // Create view on D1
        $res = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/dashboards/{$d1->id()->value()}/views", [
            'name' => 'View 1',
            'filters' => ['date_range' => '30d'],
        ]);
        $viewId = $res->json('view.id');

        // Try to access or delete view 1 via D2 URL -> must return 404
        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$d2->id()->value()}/views/{$viewId}")
            ->assertStatus(404);

        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->deleteJson("/api/v1/dashboards/{$d2->id()->value()}/views/{$viewId}")
            ->assertStatus(404);
    }

    public function test_invalid_date_span_validation(): void
    {
        $d1 = new Dashboard(id: DashboardId::generate(), workspaceId: 'ws-1', title: 'D1');
        $this->dashboardRepo->save($d1);

        $res = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/dashboards/{$d1->id()->value()}/views", [
            'name' => 'Invalid Span View',
            'filters' => [
                'date_from' => '2026-05-10',
                'date_to' => '2026-05-01',
            ],
        ]);

        $res->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=DashboardFilterSemanticsTest`
Expected: PASS.

- [ ] **Step 3: Update `scripts/verify-integration.sh`**

Add curl checks for creating, listing, updating, and deleting dashboard saved views:
```bash
echo "Testing Dashboard Saved Views API..."
VIEW_RES=$(curl -s -X POST "${BACKEND_URL}/api/v1/dashboards/${DASHBOARD_ID}/views" \
  -H "X-User-Id: user-1" \
  -H "X-Workspace-Id: ws-1" \
  -H "Content-Type: application/json" \
  -d '{"name":"Saved Integration View","filters":{"date_range":"30d"},"is_default":true}')
VIEW_ID=$(echo "$VIEW_RES" | grep -o '"id":"[^"]*' | head -n1 | cut -d'"' -f4)
test -n "$VIEW_ID" || { echo "Failed to create saved view"; exit 1; }

curl -s -f "${BACKEND_URL}/api/v1/dashboards/${DASHBOARD_ID}/views" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" > /dev/null
curl -s -X DELETE "${BACKEND_URL}/api/v1/dashboards/${DASHBOARD_ID}/views/${VIEW_ID}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" > /dev/null
```

- [ ] **Step 4: Execute full test verification (`make check`)**

Run:
```bash
npm --prefix frontend run contracts:validate
npm --prefix frontend run format:check
npm --prefix frontend run lint
npm --prefix frontend run typecheck
npm --prefix frontend test
composer --working-dir=backend lint
composer --working-dir=backend test
```
Expected: All tests and linters pass with 0 errors.

- [ ] **Step 5: Commit**

```bash
git add backend/tests/Feature/Modules/Dashboard/DashboardFilterSemanticsTest.php scripts/verify-integration.sh
git commit -m "test(dashboard): add filter semantics verification and integration checks for saved views"
```

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-09-23-shared-filters-and-saved-views-backend-contract.md`. Two execution options:

1. **Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration
2. **Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
