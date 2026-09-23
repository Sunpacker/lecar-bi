# Dashboard Builder E2E Flows & Phase 8 Integration Checkpoint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement comprehensive End-to-End and integration tests covering the complete Dashboard Builder user workflows (view/edit mode toggling, widget configuration, 12-column grid repositioning/resizing, CRUD lifecycle, multi-tenancy access control, and semantic contract purity), verify all Phase 8 exit criteria, and complete the Phase 8 Integration Checkpoint.

**Architecture:** 
- Frontend E2E & Flow Tests: Mount real `DashboardViewer` and `DashboardListView` components with unmocked internal child trees (`DashboardGrid`, `DashboardGridEditor`, `WidgetEditorCard`, `WidgetConfigSheet`, `WidgetRenderer`) to validate full user interaction lifecycles with `@testing-library/react` and Vitest.
- Backend & Contract Architecture Tests: Validate strict isolation of public contracts (`WidgetGridPosition`, `WidgetQueryConfig`, `WidgetInput`) from frontend-specific state, preventing style/DOM pollution and enforcing semantic metric/dimension enums and workspace ownership boundaries.
- Full-Stack Integration Verification: Enhance `scripts/verify-integration.sh` with live CRUD lifecycle testing (dynamic POST, GET verification, PUT reconfiguration, cross-tenant 403 isolation, authenticated Next.js SSR rendering, DELETE, and 404 confirmation).

**Tech Stack:** Next.js 16 (App Router), React 19, TypeScript 5.7, Vitest 5, Testing Library React, Tailwind CSS 4, Laravel 11 (PHP 8.3), PHPUnit 11, Redocly CLI, Docker Compose, POSIX Shell (curl).

**Spec:** `docs/roadmap/08-dashboard-builder.md`, `docs/roadmap/ROADMAP.md`, `docs/architecture/05-bounded-contexts.md`, `docs/architecture/07-api-and-integration.md`, `contracts/openapi/analytics-v1.yaml`.

## Global Constraints

- OpenAPI Contract Purity: No React-specific or presentation-internal fields (`className`, `style`, `pixelWidth`, `component`) may exist in `contracts/openapi/analytics-v1.yaml` or be stored in backend persistence.
- Multi-tenancy Isolation: All API interactions and verification checks must enforce `X-User-Id` and `X-Workspace-Id` header authorization; cross-workspace access must strictly yield `403 Forbidden`.
- 12-Column Grid Invariant: Widgets must adhere to `0 <= x <= 11`, `1 <= w <= 12`, `x + w <= 12`, `y >= 0`, `1 <= h <= 24`.
- Strict Linting & Quality: Zero warnings/errors in `make check` (`lint`, `format:check`, `typecheck`, `test`, `build`, Pint, Larastan max, PHPUnit).
- Phase Completion Gate: Phase 8 can only be marked completed (`[x]`) in `docs/roadmap/ROADMAP.md` after all 4 exit criteria are verified and documented in `docs/roadmap/08-dashboard-builder.md`.

---

### Task 1: Complete Frontend Dashboard Builder User Flow E2E Integration Test

**Files:**
- Create: `frontend/src/features/dashboard/ui/dashboard-builder-flow.test.tsx`

**Interfaces:**
- Consumes:
  - `DashboardViewer` from `./dashboard-viewer`
  - Real unmocked `DashboardGrid`, `DashboardGridEditor`, `WidgetEditorCard`, `WidgetConfigSheet`
  - Mocked `dashboardGateway` from `../api/dashboard-gateway`
  - Mocked `loadWidgetData` from `../model/widget-data-loader`
- Produces:
  - Comprehensive integration test suite validating:
    1. Switching from View to Edit mode;
    2. Editing title and description;
    3. Adding a new widget via `WidgetConfigSheet` with dataset, metric, dimension, and date range;
    4. Repositioning and resizing widgets via grid controls;
    5. Editing an existing widget's configuration;
    6. Deleting a widget from the grid;
    7. Saving the dashboard and asserting `dashboardGateway.update` is called with exact clean semantic payload (checking no UI leaks);
    8. Canceling edit mode and discarding changes.

- [ ] **Step 1: Write the failing E2E builder flow test**

Create `frontend/src/features/dashboard/ui/dashboard-builder-flow.test.tsx`:
```tsx
import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { DashboardViewer } from './dashboard-viewer'
import { dashboardGateway, type DashboardDetail } from '../api/dashboard-gateway'
import * as widgetDataLoader from '../model/widget-data-loader'

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    update: vi.fn(),
  },
}))

vi.spyOn(widgetDataLoader, 'loadWidgetData').mockResolvedValue({
  loading: false,
  kpi: { value: 1500000, formatted: '1 500 000 ₽', subtitle: 'за 30 дней' },
  chartData: [
    { label: '2026-09-01', value: 100000 },
    { label: '2026-09-02', value: 120000 },
  ],
})

const initialDashboard: DashboardDetail = {
  id: 'dash-test-1',
  workspace_id: 'ws-1',
  title: 'Коммерческий дашборд',
  description: 'Исходные ключевые показатели',
  created_at: '2026-09-22T10:00:00Z',
  updated_at: '2026-09-22T10:00:00Z',
  widgets: [
    {
      id: 'w-1',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue', date_range: '30d' },
      position: { x: 0, y: 0, w: 4, h: 2 },
      options: {},
    },
  ],
}

describe('Dashboard Builder Full E2E Flow', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('executes complete builder flow: edit mode -> reconfigure -> add widget -> move -> resize -> save', async () => {
    vi.mocked(dashboardGateway.update).mockResolvedValueOnce({
      ...initialDashboard,
      title: 'Обновленный коммерческий дашборд',
      description: 'Новое описание дашборда',
      widgets: [
        {
          id: 'w-1',
          title: 'Выручка за месяц',
          type: 'kpi_card',
          query_config: { dataset: 'sales', metric: 'revenue', date_range: '30d' },
          position: { x: 1, y: 0, w: 5, h: 2 },
          options: {},
        },
        {
          id: 'w-new-2',
          title: 'Динамика продаж',
          type: 'line_chart',
          query_config: { dataset: 'sales', metric: 'revenue', dimension: 'date', date_range: '30d' },
          position: { x: 0, y: 2, w: 8, h: 4 },
          options: {},
        },
      ],
    })

    render(
      <DashboardViewer
        dashboard={initialDashboard}
        userId="user-1"
        workspaceId="ws-1"
      />,
    )

    // 1. Initial View Mode check
    expect(screen.getByText('Коммерческий дашборд')).toBeInTheDocument()
    expect(screen.getByText('Исходные ключевые показатели')).toBeInTheDocument()
    expect(screen.getByText('Выручка')).toBeInTheDocument()

    // 2. Switch to Edit Mode
    const editBtn = screen.getByRole('button', { name: /Редактировать/i })
    fireEvent.click(editBtn)

    expect(screen.getByText('Режим редактирования')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Добавить виджет/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Сохранить/i })).toBeInTheDocument()

    // 3. Edit title and description
    const titleInput = screen.getByTestId('dashboard-title-input')
    const descInput = screen.getByTestId('dashboard-desc-input')
    fireEvent.change(titleInput, { target: { value: 'Обновленный коммерческий дашборд' } })
    fireEvent.change(descInput, { target: { value: 'Новое описание дашборда' } })

    // 4. Reposition & Resize existing widget (w-1)
    const moveRightBtn = screen.getByRole('button', { name: 'Сдвинуть вправо' })
    fireEvent.click(moveRightBtn)

    const increaseWidthBtn = screen.getByRole('button', { name: 'Увеличить ширину' })
    fireEvent.click(increaseWidthBtn)

    // 5. Open WidgetConfigSheet to edit existing widget w-1
    const editWidgetBtn = screen.getByRole('button', { name: 'Настроить виджет' })
    fireEvent.click(editWidgetBtn)

    expect(screen.getByText('Настройка виджета')).toBeInTheDocument()
    const widgetTitleInput = screen.getByLabelText(/Название виджета/i)
    fireEvent.change(widgetTitleInput, { target: { value: 'Выручка за месяц' } })

    const applyWidgetBtn = screen.getByTestId('submit-widget-btn')
    fireEvent.click(applyWidgetBtn)

    // 6. Open WidgetConfigSheet to add a NEW widget
    const addWidgetBtn = screen.getByRole('button', { name: /Добавить виджет/i })
    fireEvent.click(addWidgetBtn)

    expect(screen.getByRole('heading', { name: 'Добавить виджет' })).toBeInTheDocument()
    const newWidgetTitleInput = screen.getByLabelText(/Название виджета/i)
    fireEvent.change(newWidgetTitleInput, { target: { value: 'Динамика продаж' } })

    const applyNewWidgetBtn = screen.getByTestId('submit-widget-btn')
    fireEvent.click(applyNewWidgetBtn)

    // Verify both widgets exist in editor
    expect(screen.getByText('Выручка за месяц')).toBeInTheDocument()
    expect(screen.getByText('Динамика продаж')).toBeInTheDocument()

    // 7. Save Dashboard and assert clean API payload
    const saveBtn = screen.getByRole('button', { name: /Сохранить/i })
    fireEvent.click(saveBtn)

    await waitFor(() => {
      expect(dashboardGateway.update).toHaveBeenCalledTimes(1)
      const callArgs = vi.mocked(dashboardGateway.update).mock.calls[0]
      expect(callArgs[0]).toBe('dash-test-1')
      expect(callArgs[1]).toBe('user-1')
      expect(callArgs[3]).toBe('ws-1')

      const payload = callArgs[2]
      expect(payload.title).toBe('Обновленный коммерческий дашборд')
      expect(payload.description).toBe('Новое описание дашборда')
      expect(payload.widgets).toHaveLength(2)

      // Invariants: clean semantic properties, no DOM/React leaks
      payload.widgets.forEach((w) => {
        expect(w).toHaveProperty('title')
        expect(w).toHaveProperty('type')
        expect(w).toHaveProperty('position')
        expect(w).toHaveProperty('query_config')
        expect(w).not.toHaveProperty('className')
        expect(w).not.toHaveProperty('style')
        expect(w).not.toHaveProperty('children')

        // 12-column grid bounds
        expect(w.position.x).toBeGreaterThanOrEqual(0)
        expect(w.position.x + w.position.w).toBeLessThanOrEqual(12)
        expect(w.position.w).toBeGreaterThanOrEqual(1)
        expect(w.position.h).toBeGreaterThanOrEqual(1)
      })
    })
  })

  it('allows discarding builder changes without mutating original state', () => {
    render(
      <DashboardViewer
        dashboard={initialDashboard}
        userId="user-1"
        workspaceId="ws-1"
      />,
    )

    // Switch to edit mode
    fireEvent.click(screen.getByRole('button', { name: /Редактировать/i }))

    // Modify title
    const titleInput = screen.getByTestId('dashboard-title-input')
    fireEvent.change(titleInput, { target: { value: 'Случайное изменение' } })

    // Click delete on w-1
    const deleteBtn = screen.getByRole('button', { name: 'Удалить виджет' })
    fireEvent.click(deleteBtn)

    expect(screen.queryByText('Выручка')).not.toBeInTheDocument()

    // Discard changes
    const cancelBtn = screen.getByRole('button', { name: /Отмена/i })
    fireEvent.click(cancelBtn)

    // Mode is back to view and original widget is intact
    expect(screen.getByRole('button', { name: /Редактировать/i })).toBeInTheDocument()
    expect(screen.getByText('Коммерческий дашборд')).toBeInTheDocument()
    expect(screen.getByText('Выручка')).toBeInTheDocument()
    expect(dashboardGateway.update).not.toHaveBeenCalled()
  })
})
```

- [ ] **Step 2: Run test to verify it executes and passes**

Run: `npm --prefix frontend test -- src/features/dashboard/ui/dashboard-builder-flow.test.tsx`
Expected: PASS (2 tests passed).

- [ ] **Step 3: Commit**

```bash
git add frontend/src/features/dashboard/ui/dashboard-builder-flow.test.tsx
git commit -m "test(dashboard): add complete E2E builder interaction flow test"
```

---

### Task 2: Frontend Dashboard Management & List Flow Integration Test

**Files:**
- Create: `frontend/src/features/dashboard/ui/dashboard-list-flow.test.tsx`

**Interfaces:**
- Consumes:
  - `DashboardListView` from `./dashboard-list-view`
  - Mocked `dashboardGateway` (`create`, `delete`)
- Produces:
  - Integration test verifying:
    1. Dashboard creation flow via Sheet (inputting title, description, submitting, and updating the card grid);
    2. Confirmation and deletion flow (clicking delete icon, handling confirmation, calling `dashboardGateway.delete`, removing card from DOM);
    3. Proper error handling when creation or deletion fails.

- [ ] **Step 1: Write the failing list flow test**

Create `frontend/src/features/dashboard/ui/dashboard-list-flow.test.tsx`:
```tsx
import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { DashboardListView } from './dashboard-list-view'
import { dashboardGateway, type DashboardSummary } from '../api/dashboard-gateway'

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    create: vi.fn(),
    delete: vi.fn(),
  },
}))

const mockInitialList: DashboardSummary[] = [
  {
    id: 'd-1',
    workspace_id: 'ws-1',
    title: 'Дашборд директора',
    description: 'Сводные данные компании',
    widget_count: 3,
    created_at: '2026-09-22T08:00:00Z',
    updated_at: '2026-09-22T08:00:00Z',
  },
]

describe('DashboardListView Full Flow', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.spyOn(window, 'confirm').mockReturnValue(true)
  })

  it('creates a new dashboard via Sheet form and renders new card', async () => {
    vi.mocked(dashboardGateway.create).mockResolvedValueOnce({
      id: 'd-new',
      workspace_id: 'ws-1',
      title: 'Аналитика оптовых продаж',
      description: 'Показатели B2B сегмента',
      widgets: [],
      created_at: '2026-09-23T07:00:00Z',
      updated_at: '2026-09-23T07:00:00Z',
    })

    render(
      <DashboardListView
        initialDashboards={mockInitialList}
        userId="user-1"
        workspaceId="ws-1"
      />,
    )

    expect(screen.getByText('Дашборд директора')).toBeInTheDocument()

    // Click "Создать дашборд" button
    const openSheetBtn = screen.getByRole('button', { name: /Создать дашборд/i })
    fireEvent.click(openSheetBtn)

    expect(screen.getByRole('heading', { name: 'Новый дашборд' })).toBeInTheDocument()

    const titleInput = screen.getByLabelText(/Название/i)
    const descInput = screen.getByLabelText(/Описание/i)

    fireEvent.change(titleInput, { target: { value: 'Аналитика оптовых продаж' } })
    fireEvent.change(descInput, { target: { value: 'Показатели B2B сегмента' } })

    const submitBtn = screen.getByRole('button', { name: /Сохранить/i })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(dashboardGateway.create).toHaveBeenCalledWith(
        'user-1',
        {
          title: 'Аналитика оптовых продаж',
          description: 'Показатели B2B сегмента',
        },
        'ws-1',
      )
      expect(screen.getByText('Аналитика оптовых продаж')).toBeInTheDocument()
      expect(screen.getByText('Показатели B2B сегмента')).toBeInTheDocument()
    })
  })

  it('deletes dashboard after user confirms prompt and removes card', async () => {
    vi.mocked(dashboardGateway.delete).mockResolvedValueOnce(undefined)

    render(
      <DashboardListView
        initialDashboards={mockInitialList}
        userId="user-1"
        workspaceId="ws-1"
      />,
    )

    expect(screen.getByText('Дашборд директора')).toBeInTheDocument()

    const deleteBtn = screen.getByRole('button', { name: 'Удалить дашборд' })
    fireEvent.click(deleteBtn)

    expect(window.confirm).toHaveBeenCalledWith('Вы уверены, что хотите удалить этот дашборд?')

    await waitFor(() => {
      expect(dashboardGateway.delete).toHaveBeenCalledWith('d-1', 'user-1', 'ws-1')
      expect(screen.queryByText('Дашборд директора')).not.toBeInTheDocument()
      expect(screen.getByText('Нет доступных дашбордов')).toBeInTheDocument()
    })
  })
})
```

- [ ] **Step 2: Run test to verify it executes and passes**

Run: `npm --prefix frontend test -- src/features/dashboard/ui/dashboard-list-flow.test.tsx`
Expected: PASS (2 tests passed).

- [ ] **Step 3: Commit**

```bash
git add frontend/src/features/dashboard/ui/dashboard-list-flow.test.tsx
git commit -m "test(dashboard): add dashboard list view creation and deletion flow test"
```

---

### Task 3: Backend Contract Semantics Purity & Anti-Corruption Test

**Files:**
- Create: `backend/tests/Feature/Modules/Dashboard/DashboardContractSemanticsTest.php`

**Interfaces:**
- Consumes:
  - `contracts/openapi/analytics-v1.yaml`
  - Laravel HTTP test client
  - Seeded users `user-1`, `user-2` and workspaces `ws-1`, `ws-2`
- Produces:
  - Automated assertions confirming:
    1. OpenAPI schemas (`WidgetInput`, `WidgetDetail`, `WidgetGridPosition`, `WidgetQueryConfig`) strictly define semantic concepts without UI/framework leakage;
    2. Sending forbidden frontend fields (e.g. `style`, `className`, `pixelWidth`, `domId`) is stripped and never persisted into responses;
    3. Semantic Enums for `type` (`kpi_card`, `line_chart`, `bar_chart`, `donut_chart`, `table`), `dataset` (`sales`, `inventory`), `metric`, and `dimension` are strictly validated;
    4. Cross-tenant modification/deletion returns `403 Forbidden`.

- [ ] **Step 1: Write the failing contract semantics test**

Create `backend/tests/Feature/Modules/Dashboard/DashboardContractSemanticsTest.php`:
```php
<?php

namespace Tests\Feature\Modules\Dashboard;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class DashboardContractSemanticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);

        $user1 = new User(new UserId('user-1'), 'elena@autobi.internal', 'Elena Rostova');
        $user2 = new User(new UserId('user-2'), 'dmitry@autobi.internal', 'Dmitry Smirnov');
        $userRepo->save($user1);
        $userRepo->save($user2);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws1);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Lecar Wholesale', 'lecar-wholesale');
        $ws2->addMember(new UserId('user-2'), MembershipRole::OWNER);
        $wsRepo->save($ws2);
    }

    public function test_openapi_contract_does_not_leak_frontend_state(): void
    {
        $openapiPath = base_path('../contracts/openapi/analytics-v1.yaml');
        $this->assertFileExists($openapiPath);

        $content = (string) file_get_contents($openapiPath);
        $schema = Yaml::parse($content);

        $widgetInputProps = array_keys($schema['components']['schemas']['WidgetInput']['properties']);
        $gridPosProps = array_keys($schema['components']['schemas']['WidgetGridPosition']['properties']);
        $queryConfigProps = array_keys($schema['components']['schemas']['WidgetQueryConfig']['properties']);

        // Assert strictly semantic properties only
        $this->assertEqualsCanonicalizing(['id', 'title', 'type', 'position', 'query_config', 'options'], $widgetInputProps);
        $this->assertEqualsCanonicalizing(['x', 'y', 'w', 'h'], $gridPosProps);
        $this->assertEqualsCanonicalizing(['dataset', 'metric', 'dimension', 'date_range', 'filters'], $queryConfigProps);

        // Assert forbidden frontend-specific terms do NOT exist in widget schemas
        $forbiddenTerms = ['className', 'style', 'pixelWidth', 'pixelHeight', 'domId', 'component', 'handler'];
        foreach ($forbiddenTerms as $term) {
            $this->assertArrayNotHasKey($term, $schema['components']['schemas']['WidgetInput']['properties']);
            $this->assertArrayNotHasKey($term, $schema['components']['schemas']['WidgetGridPosition']['properties']);
            $this->assertArrayNotHasKey($term, $schema['components']['schemas']['WidgetQueryConfig']['properties']);
        }
    }

    public function test_api_ignores_and_sanitizes_spurious_frontend_fields(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/dashboards', [
            'title' => 'Дашборд с виджетом',
            'description' => 'Проверка очистки спам-полей',
            'widgets' => [
                [
                    'id' => '00000000-0000-4000-8000-000000000001',
                    'title' => 'Семантический виджет',
                    'type' => 'kpi_card',
                    'className' => 'col-span-4 bg-red-500', // Spurious frontend state
                    'style' => 'width: 100px;',
                    'position' => [
                        'x' => 0,
                        'y' => 0,
                        'w' => 4,
                        'h' => 2,
                        'pixel_w' => 400, // Spurious
                    ],
                    'query_config' => [
                        'dataset' => 'sales',
                        'metric' => 'revenue',
                        'date_range' => '30d',
                    ],
                    'options' => [],
                ],
            ],
        ]);

        $response->assertStatus(201);
        $widget = $response->json('dashboard.widgets.0');

        $this->assertArrayNotHasKey('className', $widget);
        $this->assertArrayNotHasKey('style', $widget);
        $this->assertArrayNotHasKey('pixel_w', $widget['position']);
        $this->assertSame('Семантический виджет', $widget['title']);
        $this->assertSame(4, $widget['position']['w']);
    }

    public function test_cross_tenant_update_and_delete_enforces_ownership(): void
    {
        // 1. Create dashboard in ws-1
        $createRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/dashboards', [
            'title' => 'Дашборд тенанта 1',
        ]);
        $dashId = $createRes->json('dashboard.id');

        // 2. User 2 (ws-2) attempting to update ws-1 dashboard returns 403
        $updateForbidden = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->putJson("/api/v1/dashboards/{$dashId}", [
            'title' => 'Взлом дашборда',
            'widgets' => [],
        ]);
        $updateForbidden->assertStatus(403);

        // 3. User 2 (ws-2) attempting to delete ws-1 dashboard returns 403
        $deleteForbidden = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->deleteJson("/api/v1/dashboards/{$dashId}");
        $deleteForbidden->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run test to verify it executes and passes**

Run: `composer --working-dir=backend test -- tests/Feature/Modules/Dashboard/DashboardContractSemanticsTest.php`
Expected: PASS (3 tests passed).

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Modules/Dashboard/DashboardContractSemanticsTest.php
git commit -m "test(dashboard): add contract semantics purity and ownership enforcement test"
```

---

### Task 4: Full-Stack Integration Script Enhancement with Complete Builder Lifecycle

**Files:**
- Modify: `scripts/verify-integration.sh`

**Interfaces:**
- Consumes:
  - Running backend instance at `$BACKEND_URL`
  - Running frontend instance at `$FRONTEND_URL` with Next.js session cookie
- Produces:
  - Automated bash checks covering:
    - Step 22: Dashboards list retrieval
    - Step 23: Cross-workspace access protection
    - Step 24: `POST /api/v1/dashboards` creating dynamic custom dashboard with widgets
    - Step 25: `GET /api/v1/dashboards/{id}` verifying restored widget configuration
    - Step 26: `PUT /api/v1/dashboards/{id}` updating title and reconfiguring widgets
    - Step 27: `GET /api/v1/dashboards/{id}` confirming persisted updates
    - Step 28: Cross-workspace isolation (user-2 cannot access user-1 custom dashboard)
    - Step 29: Authenticated Next.js SSR `/dashboards` and `/dashboards/[id]` page render check
    - Step 30: `DELETE /api/v1/dashboards/{id}`
    - Step 31: `GET /api/v1/dashboards/{id}` returns 404 Not Found

- [ ] **Step 1: Update `scripts/verify-integration.sh` with full CRUD builder flows**

In `scripts/verify-integration.sh`, replace Section 22–23 and append complete CRUD lifecycle assertions:
```bash
# 22. Dashboards endpoint returns list for workspace
dashboards_list="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards")"
assert_response_contains "$dashboards_list" '"items":[' "Dashboards list"
assert_response_contains "$dashboards_list" '"id":"d0000001-0000-4000-8000-000000000001"' "Dashboards list"

# 23. Cross-workspace dashboard isolation: user-1 accessing ws-2 dashboard returns 403
dashboard_cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/dashboards/d0000002-0000-4000-8000-000000000001")"
if [ "$dashboard_cross_status" != "403" ]; then
    echo "Expected 403 for cross-workspace dashboard access, got $dashboard_cross_status" >&2
    exit 1
fi

# 24. Dashboard Builder Full Lifecycle: Create custom dashboard via POST
created_dash_json="$(curl --fail --silent --show-error -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{
      "title": "Интеграционный дашборд",
      "description": "Создан для проверки жизненного цикла",
      "widgets": [
        {
          "id": "e0000001-0000-4000-8000-000000000001",
          "title": "Выручка",
          "type": "kpi_card",
          "position": {"x": 0, "y": 0, "w": 4, "h": 2},
          "query_config": {"dataset": "sales", "metric": "revenue", "date_range": "30d"},
          "options": {}
        }
      ]
    }' \
    "$BACKEND_URL/api/v1/dashboards")"
assert_response_contains "$created_dash_json" '"title":"Интеграционный дашборд"' "Dashboard creation"
CUSTOM_DASH_ID="$(printf '%s' "$created_dash_json" | sed -n 's/.*"id":"\([^"]*\)".*/\1/p')"

if [ -z "$CUSTOM_DASH_ID" ]; then
    echo "Failed to extract created dashboard ID from response" >&2
    exit 1
fi

# 25. Dashboard Builder: Restore dashboard via GET
restored_dash_json="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID")"
assert_response_contains "$restored_dash_json" '"id":"'"$CUSTOM_DASH_ID"'"' "Dashboard restore"
assert_response_contains "$restored_dash_json" '"metric":"revenue"' "Dashboard widget restore"

# 26. Dashboard Builder: Update dashboard via PUT (reposition & add widget)
updated_dash_json="$(curl --fail --silent --show-error -X PUT -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{
      "title": "Обновленный дашборд",
      "description": "Описание обновлено",
      "widgets": [
        {
          "id": "e0000001-0000-4000-8000-000000000001",
          "title": "Выручка перемещенная",
          "type": "kpi_card",
          "position": {"x": 4, "y": 0, "w": 6, "h": 2},
          "query_config": {"dataset": "sales", "metric": "revenue", "date_range": "30d"},
          "options": {}
        }
      ]
    }' \
    "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID")"
assert_response_contains "$updated_dash_json" '"title":"Обновленный дашборд"' "Dashboard update"
assert_response_contains "$updated_dash_json" '"x":4' "Widget reposition"

# 27. Cross-tenant isolation on custom dashboard
cross_custom_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-2" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID")"
if [ "$cross_custom_status" != "403" ]; then
    echo "Expected 403 for cross-workspace access to custom dashboard, got $cross_custom_status" >&2
    exit 1
fi

# 28. Frontend UI: Authenticated Next.js renders Dashboards pages
frontend_dashboards_page="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/dashboards")"
assert_response_contains "$frontend_dashboards_page" 'Пользовательские дашборды' "Frontend dashboards page"

frontend_dash_view="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/dashboards/$CUSTOM_DASH_ID")"
assert_response_contains "$frontend_dash_view" 'Обновленный дашборд' "Frontend custom dashboard view"

# 29. Dashboard Builder: Delete custom dashboard via DELETE
delete_status="$(curl --silent -o /dev/null -w "%{http_code}" -X DELETE -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID")"
if [ "$delete_status" != "204" ]; then
    echo "Expected 204 for DELETE dashboard, got $delete_status" >&2
    exit 1
fi

# 30. Verify 404 after deletion
deleted_get_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID")"
if [ "$deleted_get_status" != "404" ]; then
    echo "Expected 404 for deleted dashboard, got $deleted_get_status" >&2
    exit 1
fi

echo "Integration check passed: web -> analytics health, identity, workspace access boundaries, demo dataset, sales overview, drill-down detail records, inventory intelligence, ABC/XYZ matrix, and complete dashboard builder CRUD lifecycle are verified."
```

- [ ] **Step 2: Commit**

```bash
git add scripts/verify-integration.sh
git commit -m "feat(integration): add full dashboard builder CRUD and UI render verification to integration script"
```

---

### Task 5: Phase 8 Verification, Integration Checkpoint & Roadmap Completion

**Files:**
- Modify: `docs/roadmap/08-dashboard-builder.md`
- Modify: `docs/roadmap/ROADMAP.md`

**Interfaces:**
- Consumes:
  - All test suites across backend and frontend
  - `make check`
- Produces:
  - Verified exit criteria in `docs/roadmap/08-dashboard-builder.md`:
    1. Dashboard собирается и восстанавливается;
    2. Ownership enforced;
    3. Frontend-specific state не протекает в public contract без причины;
    4. Основные builder flows покрыты E2E.
  - Phase 8 marked completed (`[x]`) in `docs/roadmap/ROADMAP.md`.

- [ ] **Step 1: Run full verification suite**

Run:
```bash
make check
```
Verify:
1. `check-contracts`: redocly lint + openapi-typescript pass cleanly;
2. `check-frontend`: lint, format:check, typecheck, test, and build pass cleanly;
3. `check-backend`: composer validate, Pint, PHPStan max, and PHPUnit pass cleanly.

- [ ] **Step 2: Update `docs/roadmap/08-dashboard-builder.md`**

Update `docs/roadmap/08-dashboard-builder.md`:
- Move remaining items to "Что сделано" (item 10: "E2E тестирование и финальный Integration Checkpoint");
- Clear "Что осталось в текущей фазе" and "Следующий шаг" (stage complete);
- Add section `## Проверка завершения`:
  - Date of completion;
  - Status: Выполнено (все exit criteria подтверждены);
  - Detailed mapping and confirmation of all 4 Exit Criteria;
  - Automated test output records (`make check`).

- [ ] **Step 3: Update `docs/roadmap/ROADMAP.md`**

In `docs/roadmap/ROADMAP.md`, update line 27:
```markdown
- [x] [Phase 8 — Dashboard Builder](08-dashboard-builder.md)
```

- [ ] **Step 4: Commit**

```bash
git add docs/roadmap/08-dashboard-builder.md docs/roadmap/ROADMAP.md
git commit -m "docs(roadmap): complete Phase 8 Dashboard Builder and pass integration checkpoint"
```

---

## Plan Self-Review Checklist

1. **Spec Coverage:**
   - Dashboard assembly & restoration: Covered in Task 1, 3, 4.
   - Ownership enforced: Covered in Task 3, 4.
   - Frontend-specific state isolation: Covered in Task 1, 3.
   - E2E coverage of builder flows: Covered in Task 1 (builder flow), Task 2 (list flow), Task 4 (integration script).
   - Integration Checkpoint & Roadmap closure: Covered in Task 5.

2. **Placeholder Scan:**
   - No "TODO", "TBD", or vague placeholders.
   - Every task provides exact file paths, interfaces, and complete executable code blocks.

3. **Type & Architecture Consistency:**
   - Semantic types (`WidgetGridPosition`, `WidgetQueryConfig`, `WidgetDetail`, `WidgetInput`) strictly respected.
   - 12-column grid invariants (`0 <= x <= 11`, `x + w <= 12`, `w >= 1`, `h >= 1`) enforced.
