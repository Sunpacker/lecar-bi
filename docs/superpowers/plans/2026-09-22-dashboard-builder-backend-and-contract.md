# Dashboard Builder (Phase 8) — OpenAPI Contract & Backend Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the backend foundational slice and OpenAPI contract for Phase 8 (Dashboard Builder), enabling users to create, read, update, and delete custom dashboards with semantic widget configurations (dataset, metric, dimension, grid coordinates, display options) with strict workspace multi-tenancy isolation and generated frontend TypeScript SDK.

**Architecture:** Domain-Driven Design (DDD) in Laravel 11 under bounded context `Dashboard` with complete layer separation (Domain, Application, Infrastructure, Presentation). PostgreSQL persistence with Star Schema relation to `workspaces`. Contracts defined in OpenAPI 3.0.3 with semantic widget abstractions (independent of frontend framework layout details). In-memory repository for isolated unit testing and Eloquent repository for production.

**Tech Stack:** Laravel 11 (PHP 8.3), PostgreSQL 16, OpenAPI 3.0.3, Redocly CLI, openapi-typescript 7, PHPUnit 11, React 19 / Next.js 15, Vitest.

**Spec:** `docs/roadmap/08-dashboard-builder.md`, `docs/architecture/05-bounded-contexts.md`, `docs/architecture/07-api-and-integration.md`.

## Global Constraints

- Domain Layer in `App\Modules\Dashboard\Domain` MUST NOT depend on Laravel/Illuminate, framework helpers, or Infrastructure layers (`ArchitectureTest`).
- Backend contract MUST rely on semantic widget concepts (`dataset`, `metric`, `dimension`, `date_range`, `position` coordinates x/y/w/h, `options`), NOT React-specific implementation details (`docs/roadmap/08-dashboard-builder.md`).
- OpenAPI specification in `contracts/openapi/analytics-v1.yaml` is the single source of truth; code must be generated via `npm --prefix frontend run api:generate` (`docs/architecture/07-api-and-integration.md`, ADR-007).
- Multi-tenancy isolation MUST be enforced on every endpoint via `workspace_id` and verified against `WorkspaceAccessGuard`.
- All commands and queries adhere to CQRS-lite (`docs/architecture/04-backend-laravel-ddd.md`, ADR-006).

---

### Task 0: Resolve Pre-existing Test Suite Regression in Header Theme Toggle

**Files:**
- Modify: `frontend/src/shared/ui/layout/header.tsx`
- Test: `frontend/src/shared/ui/layout/header.test.tsx`

**Interfaces:**
- Consumes: `localStorage`, `document.documentElement`
- Produces: Theme toggle button in header with `aria-label="Переключить цветовую тему"`

- [ ] **Step 1: Verify the failing test in `header.test.tsx`**

Run: `npm --prefix frontend test -- src/shared/ui/layout/header.test.tsx`
Expected: FAIL with "Unable to find an accessible element with the role 'button' and name 'Переключить цветовую тему'".

- [ ] **Step 2: Add `ThemeToggle` component to `frontend/src/shared/ui/layout/header.tsx`**

Implement the theme switch button inside `header.tsx`:
```tsx
function ThemeToggle() {
  const [theme, setTheme] = React.useState<'light' | 'dark'>('dark')

  React.useEffect(() => {
    const isDark = document.documentElement.classList.contains('dark')
    setTheme(isDark ? 'dark' : 'light')
  }, [])

  const toggleTheme = () => {
    const nextTheme = theme === 'dark' ? 'light' : 'dark'
    setTheme(nextTheme)
    document.documentElement.classList.toggle('dark', nextTheme === 'dark')
    document.documentElement.style.colorScheme = nextTheme
    window.localStorage.setItem('autobi-theme', nextTheme)
  }

  return (
    <Button
      variant="ghost"
      size="icon"
      aria-label="Переключить цветовую тему"
      onClick={toggleTheme}
      className="size-9 text-muted-foreground hover:text-foreground"
    >
      <Sun className="h-4 w-4 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0" />
      <Moon className="absolute h-4 w-4 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
      <span className="sr-only">Переключить цветовую тему</span>
    </Button>
  )
}
```
And render `<ThemeToggle />` within the header actions container.

- [ ] **Step 3: Run test to verify it passes**

Run: `npm --prefix frontend test -- src/shared/ui/layout/header.test.tsx`
Expected: PASS (1 passed).

- [ ] **Step 4: Commit**

```bash
git add frontend/src/shared/ui/layout/header.tsx
git commit -m "fix(ui): include theme toggle in header layout"
```

---

### Task 1: OpenAPI 3.0.3 Contract for Dashboards & Semantic Widgets

**Files:**
- Modify: `contracts/openapi/analytics-v1.yaml`
- Modify: `backend/tests/Feature/ApiContractTest.php`
- Generated: `frontend/src/shared/api/generated/schema.ts`

**Interfaces:**
- Consumes: Existing OpenAPI schemas (`ErrorResponse`, `UserIdAuth`)
- Produces:
  - `GET /dashboards` -> `DashboardListResponse`
  - `POST /dashboards` -> `DashboardDetailResponse` (with `CreateDashboardRequest`)
  - `GET /dashboards/{id}` -> `DashboardDetailResponse`
  - `PUT /dashboards/{id}` -> `DashboardDetailResponse` (with `UpdateDashboardRequest`)
  - `DELETE /dashboards/{id}` -> `204 No Content`
  - Schemas: `DashboardSummary`, `DashboardListResponse`, `DashboardDetail`, `DashboardDetailResponse`, `WidgetDetail`, `WidgetInput`, `WidgetGridPosition`, `WidgetQueryConfig`, `CreateDashboardRequest`, `UpdateDashboardRequest`

- [ ] **Step 1: Write failing test in `backend/tests/Feature/ApiContractTest.php`**

Add assertion method to `ApiContractTest`:
```php
    public function test_contract_contains_dashboard_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/dashboards', $contract['paths']);
        self::assertArrayHasKey('/dashboards/{id}', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('DashboardListResponse', $schemas);
        self::assertArrayHasKey('DashboardSummary', $schemas);
        self::assertArrayHasKey('DashboardDetailResponse', $schemas);
        self::assertArrayHasKey('DashboardDetail', $schemas);
        self::assertArrayHasKey('WidgetDetail', $schemas);
        self::assertArrayHasKey('WidgetInput', $schemas);
        self::assertArrayHasKey('WidgetGridPosition', $schemas);
        self::assertArrayHasKey('WidgetQueryConfig', $schemas);
        self::assertArrayHasKey('CreateDashboardRequest', $schemas);
        self::assertArrayHasKey('UpdateDashboardRequest', $schemas);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_dashboard_endpoints`
Expected: FAIL with "Failed asserting that an array has key '/dashboards'."

- [ ] **Step 3: Update `contracts/openapi/analytics-v1.yaml` and generate TypeScript types**

Add paths `/dashboards`, `/dashboards/{id}` with complete request/response definitions and semantic widget schemas (`dataset`: `sales` | `inventory`; `metric`: `revenue`, `order_count`, `average_order_value`, `gross_profit`, `margin_rate`, `stock_quantity`, `stock_value`, `out_of_stock_count`, `overstock_count`; `dimension`: `date`, `category`, `region`, `warehouse`, `abc_class`, `xyz_class`, `supplier`; `widget_type`: `kpi_card`, `line_chart`, `bar_chart`, `donut_chart`, `table`; `position`: `x`, `y`, `w`, `h`).

Run validation and client generator:
```bash
npm --prefix frontend run contracts:validate
npm --prefix frontend run api:generate
```

- [ ] **Step 4: Run test to verify contract test passes**

Run: `composer --working-dir=backend test -- --filter=test_contract_contains_dashboard_endpoints`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add contracts/openapi/analytics-v1.yaml backend/tests/Feature/ApiContractTest.php frontend/src/shared/api/generated/schema.ts
git commit -m "feat(contracts): define OpenAPI contract for dashboards and semantic widgets"
```

---

### Task 2: Domain Layer for Dashboard Bounded Context

**Files:**
- Create: `backend/app/Modules/Dashboard/Domain/DashboardId.php`
- Create: `backend/app/Modules/Dashboard/Domain/WidgetId.php`
- Create: `backend/app/Modules/Dashboard/Domain/WidgetType.php`
- Create: `backend/app/Modules/Dashboard/Domain/DatasetType.php`
- Create: `backend/app/Modules/Dashboard/Domain/MetricType.php`
- Create: `backend/app/Modules/Dashboard/Domain/DimensionType.php`
- Create: `backend/app/Modules/Dashboard/Domain/WidgetGridPosition.php`
- Create: `backend/app/Modules/Dashboard/Domain/WidgetQueryConfig.php`
- Create: `backend/app/Modules/Dashboard/Domain/Widget.php`
- Create: `backend/app/Modules/Dashboard/Domain/Dashboard.php`
- Create: `backend/app/Modules/Dashboard/Domain/Repositories/DashboardRepositoryInterface.php`
- Create: `backend/app/Modules/Dashboard/Domain/Exceptions/DashboardNotFoundException.php`
- Create: `backend/app/Modules/Dashboard/Domain/Exceptions/InvalidGridPositionException.php`
- Create: `backend/tests/Unit/Modules/Dashboard/Domain/DashboardDomainTest.php`

**Interfaces:**
- Consumes: PHP standard types, `DateTimeImmutable`, `WorkspaceId` from `App\Modules\Workspace\Domain\WorkspaceId`
- Produces: Pure domain aggregate `Dashboard`, entity `Widget`, value objects `WidgetGridPosition`, `WidgetQueryConfig`, `DashboardRepositoryInterface`

- [ ] **Step 1: Write failing unit tests in `backend/tests/Unit/Modules/Dashboard/Domain/DashboardDomainTest.php`**

```php
<?php

namespace Tests\Unit\Modules\Dashboard\Domain;

use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\DatasetType;
use App\Modules\Dashboard\Domain\DimensionType;
use App\Modules\Dashboard\Domain\Exceptions\InvalidGridPositionException;
use App\Modules\Dashboard\Domain\MetricType;
use App\Modules\Dashboard\Domain\Widget;
use App\Modules\Dashboard\Domain\WidgetGridPosition;
use App\Modules\Dashboard\Domain\WidgetId;
use App\Modules\Dashboard\Domain\WidgetQueryConfig;
use App\Modules\Dashboard\Domain\WidgetType;
use App\Modules\Workspace\Domain\WorkspaceId;
use PHPUnit\Framework\TestCase;

final class DashboardDomainTest extends TestCase
{
    public function test_dashboard_can_be_created_and_manipulated(): void
    {
        $dashboardId = DashboardId::generate();
        $workspaceId = new WorkspaceId('ws-1');
        $dashboard = new Dashboard($dashboardId, $workspaceId, 'Основной обзор');

        self::assertSame('Основной обзор', $dashboard->title());
        self::assertNull($dashboard->description());
        self::assertCount(0, $dashboard->widgets());

        $widget = new Widget(
            id: WidgetId::generate(),
            title: 'Выручка за 30 дней',
            type: WidgetType::KPI_CARD,
            queryConfig: new WidgetQueryConfig(DatasetType::SALES, MetricType::REVENUE, null, '30d'),
            position: new WidgetGridPosition(x: 0, y: 0, w: 4, h: 2),
            options: ['color' => 'emerald']
        );

        $dashboard->addWidget($widget);
        self::assertCount(1, $dashboard->widgets());
        self::assertSame(1, $dashboard->widgetCount());

        $dashboard->rename('Обновленный обзор', 'Новое описание');
        self::assertSame('Обновленный обзор', $dashboard->title());
        self::assertSame('Новое описание', $dashboard->description());

        $dashboard->removeWidget($widget->id());
        self::assertCount(0, $dashboard->widgets());
    }

    public function test_invalid_grid_position_throws_exception(): void
    {
        $this->expectException(InvalidGridPositionException::class);
        new WidgetGridPosition(x: 10, y: 0, w: 4, h: 2); // 10 + 4 > 12
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=DashboardDomainTest`
Expected: FAIL with "Class App\Modules\Dashboard\Domain\Dashboard not found".

- [ ] **Step 3: Implement Domain classes**

1. `DashboardId`: value object with UUID regex validation and `public static function generate(): self`.
2. `WidgetId`: value object with UUID regex validation and `public static function generate(): self`.
3. `WidgetType`, `DatasetType`, `MetricType`, `DimensionType`: pure PHP enums.
4. `WidgetGridPosition`: validates `0 <= x <= 11`, `1 <= w <= 12`, `x + w <= 12`, `y >= 0`, `1 <= h <= 24`.
5. `WidgetQueryConfig`: encapsulates `DatasetType`, `MetricType`, `?DimensionType`, `?string $dateRange`.
6. `Widget`: entity with getters and setters.
7. `Dashboard`: aggregate root with `rename()`, `addWidget()`, `removeWidget()`, `replaceWidgets()`, `widgets()`, `widgetCount()`.
8. `DashboardRepositoryInterface`: defines contracts:
   - `findById(DashboardId $id): ?Dashboard`
   - `findByWorkspaceId(WorkspaceId $workspaceId): array`
   - `save(Dashboard $dashboard): void`
   - `delete(DashboardId $id): void`

- [ ] **Step 4: Run domain unit test and Architecture test**

Run:
```bash
composer --working-dir=backend test -- --filter=DashboardDomainTest
composer --working-dir=backend test -- --filter=ArchitectureTest
```
Expected: PASS (ArchitectureTest asserts 0 forbidden framework dependencies in `App\Modules\Dashboard\Domain`).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/Dashboard/Domain backend/tests/Unit/Modules/Dashboard/Domain
git commit -m "feat(dashboard): implement domain layer for Dashboard bounded context"
```

---

### Task 3: Application Layer (CQRS Commands, Queries, DTOs & Handlers)

**Files:**
- Create: `backend/app/Modules/Dashboard/Application/Dtos/WidgetGridPositionDto.php`
- Create: `backend/app/Modules/Dashboard/Application/Dtos/WidgetQueryConfigDto.php`
- Create: `backend/app/Modules/Dashboard/Application/Dtos/WidgetDto.php`
- Create: `backend/app/Modules/Dashboard/Application/Dtos/DashboardSummaryDto.php`
- Create: `backend/app/Modules/Dashboard/Application/Dtos/DashboardDetailDto.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/CreateDashboardCommand.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/CreateDashboardHandler.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/UpdateDashboardCommand.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/UpdateDashboardHandler.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/DeleteDashboardCommand.php`
- Create: `backend/app/Modules/Dashboard/Application/Commands/DeleteDashboardHandler.php`
- Create: `backend/app/Modules/Dashboard/Application/Queries/GetDashboardsQuery.php`
- Create: `backend/app/Modules/Dashboard/Application/Queries/GetDashboardsHandler.php`
- Create: `backend/app/Modules/Dashboard/Application/Queries/GetDashboardByIdQuery.php`
- Create: `backend/app/Modules/Dashboard/Application/Queries/GetDashboardByIdHandler.php`
- Create: `backend/tests/Unit/Modules/Dashboard/Application/DashboardApplicationTest.php`

**Interfaces:**
- Consumes: `DashboardRepositoryInterface`, `WorkspaceAccessGuard`, Domain entities
- Produces: DTOs (`DashboardSummaryDto`, `DashboardDetailDto`), application command & query handlers

- [ ] **Step 1: Write failing application tests in `DashboardApplicationTest.php`**

Test all use cases with an in-memory repository mock/stub:
1. `CreateDashboardHandler`: creates dashboard, saves via repository, returns `DashboardDetailDto`.
2. `GetDashboardsHandler`: returns list of `DashboardSummaryDto` for workspace.
3. `GetDashboardByIdHandler`: returns `DashboardDetailDto` or throws `DashboardNotFoundException`.
4. `UpdateDashboardHandler`: renames and updates widgets.
5. `DeleteDashboardHandler`: deletes dashboard.

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=DashboardApplicationTest`
Expected: FAIL with "Class CreateDashboardHandler not found".

- [ ] **Step 3: Implement Application DTOs, Commands, Queries, and Handlers**

Implement DTOs with typed properties matching OpenAPI schemas. Implement Handlers using `DashboardRepositoryInterface` and `WorkspaceAccessGuard` for workspace validation.

- [ ] **Step 4: Run application tests to verify they pass**

Run: `composer --working-dir=backend test -- --filter=DashboardApplicationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/Dashboard/Application backend/tests/Unit/Modules/Dashboard/Application
git commit -m "feat(dashboard): implement application commands and queries"
```

---

### Task 4: Infrastructure Layer (PostgreSQL Migrations, Eloquent Models & Repositories)

**Files:**
- Create: `backend/database/migrations/2026_09_22_000020_create_dashboards_table.php`
- Create: `backend/database/migrations/2026_09_22_000021_create_dashboard_widgets_table.php`
- Create: `backend/app/Modules/Dashboard/Infrastructure/Persistence/Eloquent/Models/DashboardModel.php`
- Create: `backend/app/Modules/Dashboard/Infrastructure/Persistence/Eloquent/Models/DashboardWidgetModel.php`
- Create: `backend/app/Modules/Dashboard/Infrastructure/Persistence/Eloquent/Repositories/EloquentDashboardRepository.php`
- Create: `backend/app/Modules/Dashboard/Infrastructure/Persistence/InMemory/InMemoryDashboardRepository.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Create: `backend/tests/Unit/Modules/Dashboard/Infrastructure/DashboardRepositoryTest.php`

**Interfaces:**
- Consumes: PostgreSQL schema, `dashboards`, `dashboard_widgets`, Eloquent
- Produces: `DashboardRepositoryInterface` implementations (InMemory for testing, Eloquent for production/dev)

- [ ] **Step 1: Write failing repository test in `DashboardRepositoryTest.php`**

Test `InMemoryDashboardRepository` and `EloquentDashboardRepository`:
- Save aggregate with widgets.
- `findById` returns reconstituted domain aggregate with accurate widget grid positions and query configurations.
- `findByWorkspaceId` filters by workspace.
- `delete` removes dashboard and cascaded widgets.

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=DashboardRepositoryTest`
Expected: FAIL.

- [ ] **Step 3: Implement database migrations, models, repositories and provider binding**

1. Migration `create_dashboards_table`: `id` (string/UUID primary), `workspace_id` (foreign key -> workspaces, cascade delete), `title` (string), `description` (text, nullable), timestamps. Index on `[workspace_id, created_at]`.
2. Migration `create_dashboard_widgets_table`: `id` (string/UUID primary), `dashboard_id` (foreign key -> dashboards, cascade delete), `title` (string), `type` (string), `query_config` (jsonb), `grid_x` (int), `grid_y` (int), `grid_w` (int), `grid_h` (int), `options` (jsonb, nullable), timestamps. Index on `dashboard_id`.
3. `DashboardModel`: hasMany `widgets` (`DashboardWidgetModel`).
4. `DashboardWidgetModel`: belongsTo `dashboard` (`DashboardModel`), casts `query_config` => `'array'`, `options` => `'array'`.
5. `EloquentDashboardRepository`: implements `DashboardRepositoryInterface` using DB transactions and atomic widget synchronization.
6. `InMemoryDashboardRepository`: implements `DashboardRepositoryInterface` for fast isolated testing.
7. `AppServiceProvider`: bind `DashboardRepositoryInterface` to `InMemoryDashboardRepository` when `environment('testing')`, otherwise `EloquentDashboardRepository`.

- [ ] **Step 4: Run repository tests**

Run: `composer --working-dir=backend test -- --filter=DashboardRepositoryTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations backend/app/Modules/Dashboard/Infrastructure backend/app/Providers/AppServiceProvider.php backend/tests/Unit/Modules/Dashboard/Infrastructure
git commit -m "feat(dashboard): implement persistence layer and migrations"
```

---

### Task 5: Presentation Layer (Controllers, Form Requests & API Routes)

**Files:**
- Create: `backend/app/Modules/Dashboard/Presentation/Requests/CreateDashboardRequest.php`
- Create: `backend/app/Modules/Dashboard/Presentation/Requests/UpdateDashboardRequest.php`
- Create: `backend/app/Modules/Dashboard/Presentation/Controllers/DashboardController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Modules/Dashboard/DashboardApiTest.php`

**Interfaces:**
- Consumes: HTTP requests authenticated via `AuthenticateUserIdMiddleware`, `X-Workspace-Id` header, Application handlers
- Produces: REST API endpoints:
  - `GET /api/v1/dashboards`
  - `POST /api/v1/dashboards`
  - `GET /api/v1/dashboards/{id}`
  - `PUT /api/v1/dashboards/{id}`
  - `DELETE /api/v1/dashboards/{id}`

- [ ] **Step 1: Write failing Feature API tests in `DashboardApiTest.php`**

Write feature tests covering:
1. `test_unauthenticated_request_returns_401`
2. `test_list_dashboards_returns_only_current_workspace_items`
3. `test_create_dashboard_validates_input_and_creates_record` (201 Created)
4. `test_get_dashboard_by_id_returns_details_and_widgets` (200 OK)
5. `test_cross_workspace_dashboard_access_is_forbidden` (403 Forbidden)
6. `test_update_dashboard_updates_title_and_replaces_widgets` (200 OK)
7. `test_delete_dashboard_removes_record` (204 No Content)
8. `test_invalid_widget_grid_position_returns_422`

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=DashboardApiTest`
Expected: FAIL with 404 Route not found.

- [ ] **Step 3: Implement Form Requests, Controller, and register routes in `routes/api.php`**

1. `CreateDashboardRequest`: validates `title` (required, string, max:100), `description` (nullable, string, max:1000).
2. `UpdateDashboardRequest`: validates `title`, `description`, `widgets` array and sub-fields (`type`, `query_config.dataset`, `query_config.metric`, `position.x`, `position.y`, `position.w`, `position.h`).
3. `DashboardController`: methods `index`, `store`, `show`, `update`, `destroy` resolving user from request attributes and workspace from `GetCurrentWorkspaceHandler`.
4. `routes/api.php`: register the 5 routes under `AuthenticateUserIdMiddleware`.

- [ ] **Step 4: Run Feature API tests to verify they pass**

Run: `composer --working-dir=backend test -- --filter=DashboardApiTest`
Expected: PASS (all 8 tests pass).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/Dashboard/Presentation backend/routes/api.php backend/tests/Feature/Modules/Dashboard/DashboardApiTest.php
git commit -m "feat(dashboard): expose dashboard CRUD REST API endpoints"
```

---

### Task 6: Seed Demo Dashboards for Workspaces & Integration Verification

**Files:**
- Create: `backend/database/seeders/DashboardDatabaseSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Modify: `scripts/verify-integration.sh`

**Interfaces:**
- Consumes: Seeded workspaces `ws-1` ("AutoParts Retail") and `ws-2` ("Lecar Wholesale")
- Produces: Initial starter dashboard with 4 semantic widgets (KPI Revenue, KPI Orders, Sales Trend line chart, Sales by Category donut chart) for each demo workspace.

- [ ] **Step 1: Create `DashboardDatabaseSeeder.php`**

Seed default dashboard "Сводный обзор бизнеса" for `ws-1` and `ws-2` with realistic semantic widgets using `DashboardRepositoryInterface`.

- [ ] **Step 2: Register in `DatabaseSeeder.php`**

Add `$this->call(DashboardDatabaseSeeder::class);` to `DatabaseSeeder::run()`.

- [ ] **Step 3: Update `scripts/verify-integration.sh`**

Add verification checks in `scripts/verify-integration.sh`:
- Query `GET /api/v1/dashboards` for `ws-1` returns 200 with at least 1 dashboard.
- Query `GET /api/v1/dashboards/{id}` returns semantic widgets.
- Tenant isolation: accessing `ws-1` dashboard with `ws-2` credentials returns 403 Forbidden.

- [ ] **Step 4: Run full verification suite**

Run:
```bash
make check
```
Verifies:
- `npm --prefix frontend run contracts:validate`
- `npm --prefix frontend run api:generate`
- `npm --prefix frontend run lint`
- `npm --prefix frontend run typecheck`
- `npm --prefix frontend test`
- `npm --prefix frontend run build`
- `composer --working-dir=backend validate --strict`
- `composer --working-dir=backend lint`
- `composer --working-dir=backend test`

- [ ] **Step 5: Commit**

```bash
git add backend/database/seeders scripts/verify-integration.sh
git commit -m "feat(dashboard): seed starter dashboards and verify integration"
```

---

## Self-Review Checklist

- **Spec coverage:**
  - Create/rename/delete dashboard? Covered in Task 3, 4, 5 (`store`, `update`, `destroy`).
  - Add/remove/move/resize widget? Covered in Task 2, 3, 4, 5 (semantic widget position `x`, `y`, `w`, `h`, query config).
  - Metric/dimension/options? Covered in Task 1, 2, 3, 5 (`WidgetQueryConfig`, `options`).
  - Save/reload? Covered in Task 3, 4, 5 (`save`, `findById`, `findByWorkspaceId`).
  - Workspace ownership? Covered in Task 3, 5 (`WorkspaceAccessGuard`, tenant isolation tests).
  - Semantic widget concepts independent of React? Covered in Task 1, 2.
- **Placeholder scan:** No "TODO", "TBD", or unelaborated steps. Concrete code and commands provided.
- **Type consistency:** `DashboardId`, `WidgetId`, `WidgetGridPosition`, `WidgetQueryConfig`, `WidgetType`, `DatasetType`, `MetricType`, `DimensionType` types match consistently across OpenAPI, Domain, Application, and Persistence layers.
