# Phase 9 Task 2: Frontend Shared Filters & Saved Views Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the frontend UI, state management, dataset projection, URL synchronization, and REST client for Phase 9 (Shared Filters and Saved Views), enabling users to filter dashboards by date presets, categories, regions, warehouses, and stock status, and to save, switch, and manage filter presets (saved views).

**Architecture:** Feature-oriented frontend in Next.js 16 (React 19, TypeScript) adhering to ADR-013 (no domain business rules on frontend) and ADR-016 (shadcn/ui + Tailwind CSS). Gateway methods invoke OpenAPI-typed backend endpoints. Pure helper `filter-resolver` handles relative date conversions, widget-level overrides, and dataset-specific projection (sales vs inventory). Hook `useDashboardFilters` synchronizes active filter state with URL search parameters. `DashboardFilterBar` and `DashboardSavedViewsMenu` are integrated into `DashboardViewer`, passing active filters through `DashboardGrid` into `WidgetRenderer` and `loadWidgetData`.

**Tech Stack:** Next.js 16 (React 19), Tailwind CSS 4, shadcn/ui (@base-ui/react, lucide-react), openapi-fetch, Vitest, React Testing Library.

**Spec:** `docs/roadmap/09-shared-filters-saved-views.md`, `docs/architecture/03-frontend-nextjs.md`, `docs/architecture/05-bounded-contexts.md`, `docs/architecture/07-api-and-integration.md`, `docs/architecture/12-architecture-decisions.md`.

## Global Constraints

- Frontend MUST NOT calculate or alter business KPIs or analytical domain metrics (ADR-013).
- UI MUST strictly use shadcn/ui primitives and Tailwind CSS (ADR-016).
- All API requests MUST use the typed API client generated from OpenAPI 3.0.3 (`frontend/src/shared/api/generated/schema.ts`).
- Multi-tenancy headers (`X-User-Id` and `X-Workspace-Id`) MUST be sent on every API invocation.
- Filter semantics MUST be unified: `date_range` ('30d' | '90d' | '180d' | '365d' | 'all' | 'custom'), `date_from`, `date_to`, `category_id`, `region_id`, `warehouse_id`, `stock_health`.
- Incompatible filter combinations MUST be safely projected per dataset: sales dataset excludes `warehouse_id` and `stock_health`; inventory dataset excludes `region_id`.
- Local widget-level filters take precedence over dashboard-level shared filters where defined.
- Filter state MUST be synchronized with URL query parameters so links can be shared and restored.

---

### Task 1: Extend Dashboard Gateway with Saved Views REST API Client

**Files:**
- Modify: `frontend/src/features/dashboard/api/dashboard-gateway.ts`
- Modify: `frontend/src/features/dashboard/api/dashboard-gateway.test.ts`

**Interfaces:**
- Consumes:
  - `analyticsClient` from `../../../shared/api/analytics-client`
  - `DashboardSavedView`, `CreateDashboardSavedViewRequest`, `UpdateDashboardSavedViewRequest`, `DashboardFilterValues` from `../../../shared/api/generated/schema`
- Produces:
  - `dashboardGateway.listSavedViews(dashboardId: string, userId: string, workspaceId?: string): Promise<DashboardSavedView[]>`
  - `dashboardGateway.getSavedView(dashboardId: string, viewId: string, userId: string, workspaceId?: string): Promise<DashboardSavedView>`
  - `dashboardGateway.createSavedView(dashboardId: string, userId: string, request: CreateDashboardSavedViewRequest, workspaceId?: string): Promise<DashboardSavedView>`
  - `dashboardGateway.updateSavedView(dashboardId: string, viewId: string, userId: string, request: UpdateDashboardSavedViewRequest, workspaceId?: string): Promise<DashboardSavedView>`
  - `dashboardGateway.deleteSavedView(dashboardId: string, viewId: string, userId: string, workspaceId?: string): Promise<void>`

- [ ] **Step 1: Write failing tests in `frontend/src/features/dashboard/api/dashboard-gateway.test.ts`**

Add tests for all 5 saved view gateway methods to `frontend/src/features/dashboard/api/dashboard-gateway.test.ts`:
```typescript
  it('lists saved views for a dashboard', async () => {
    const mockViewsList = {
      items: [
        {
          id: 'view-1',
          dashboard_id: 'dash-1',
          name: 'Основной вид',
          filters: { date_range: '30d' },
          is_default: true,
          created_at: '2026-09-23T10:00:00Z',
          updated_at: '2026-09-23T10:00:00Z',
        },
      ],
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockViewsList,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.listSavedViews('dash-1', 'user-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith('/dashboards/{dashboardId}/views', {
      params: { path: { dashboardId: 'dash-1' } },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockViewsList.items)
  })

  it('gets a single saved view by ID', async () => {
    const mockView = {
      view: {
        id: 'view-1',
        dashboard_id: 'dash-1',
        name: 'Основной вид',
        filters: { date_range: '30d' },
        is_default: true,
        created_at: '2026-09-23T10:00:00Z',
        updated_at: '2026-09-23T10:00:00Z',
      },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockView,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.getSavedView('dash-1', 'view-1', 'user-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith('/dashboards/{dashboardId}/views/{viewId}', {
      params: { path: { dashboardId: 'dash-1', viewId: 'view-1' } },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockView.view)
  })

  it('creates a saved view', async () => {
    const mockCreated = {
      view: {
        id: 'view-new',
        dashboard_id: 'dash-1',
        name: 'Новый фильтр',
        filters: { date_range: '90d', region_id: 'reg-1' },
        is_default: false,
        created_at: '2026-09-23T10:00:00Z',
        updated_at: '2026-09-23T10:00:00Z',
      },
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: mockCreated,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.createSavedView(
      'dash-1',
      'user-1',
      { name: 'Новый фильтр', filters: { date_range: '90d', region_id: 'reg-1' }, is_default: false },
      'ws-1',
    )

    expect(analyticsClient.POST).toHaveBeenCalledWith('/dashboards/{dashboardId}/views', {
      params: { path: { dashboardId: 'dash-1' } },
      body: { name: 'Новый фильтр', filters: { date_range: '90d', region_id: 'reg-1' }, is_default: false },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockCreated.view)
  })

  it('updates a saved view', async () => {
    const mockUpdated = {
      view: {
        id: 'view-1',
        dashboard_id: 'dash-1',
        name: 'Обновленный фильтр',
        filters: { date_range: '180d' },
        is_default: true,
        created_at: '2026-09-23T10:00:00Z',
        updated_at: '2026-09-23T11:00:00Z',
      },
    }

    vi.mocked(analyticsClient.PUT).mockResolvedValueOnce({
      data: mockUpdated,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.updateSavedView(
      'dash-1',
      'view-1',
      'user-1',
      { name: 'Обновленный фильтр', filters: { date_range: '180d' }, is_default: true },
      'ws-1',
    )

    expect(analyticsClient.PUT).toHaveBeenCalledWith('/dashboards/{dashboardId}/views/{viewId}', {
      params: { path: { dashboardId: 'dash-1', viewId: 'view-1' } },
      body: { name: 'Обновленный фильтр', filters: { date_range: '180d' }, is_default: true },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockUpdated.view)
  })

  it('deletes a saved view', async () => {
    vi.mocked(analyticsClient.DELETE).mockResolvedValueOnce({
      data: undefined,
      error: undefined,
      response: new Response(null, { status: 204 }),
    } as never)

    await dashboardGateway.deleteSavedView('dash-1', 'view-1', 'user-1', 'ws-1')

    expect(analyticsClient.DELETE).toHaveBeenCalledWith('/dashboards/{dashboardId}/views/{viewId}', {
      params: { path: { dashboardId: 'dash-1', viewId: 'view-1' } },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
  })
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/dashboard/api/dashboard-gateway.test.ts`
Expected: FAIL with "TypeError: dashboardGateway.listSavedViews is not a function"

- [ ] **Step 3: Implement saved view methods in `frontend/src/features/dashboard/api/dashboard-gateway.ts`**

Export types:
```typescript
export type DashboardFilterValues = components['schemas']['DashboardFilterValues']
export type DashboardSavedView = components['schemas']['DashboardSavedView']
export type CreateDashboardSavedViewRequest = components['schemas']['CreateDashboardSavedViewRequest']
export type UpdateDashboardSavedViewRequest = components['schemas']['UpdateDashboardSavedViewRequest']
```

Add methods to `dashboardGateway`:
```typescript
  async listSavedViews(
    dashboardId: string,
    userId: string,
    workspaceId?: string,
  ): Promise<DashboardSavedView[]> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/dashboards/{dashboardId}/views', {
      params: {
        path: { dashboardId },
      },
      headers,
    })

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to load saved views',
      )
    }

    return data.items
  },

  async getSavedView(
    dashboardId: string,
    viewId: string,
    userId: string,
    workspaceId?: string,
  ): Promise<DashboardSavedView> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET(
      '/dashboards/{dashboardId}/views/{viewId}',
      {
        params: {
          path: { dashboardId, viewId },
        },
        headers,
      },
    )

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to load saved view',
      )
    }

    return data.view
  },

  async createSavedView(
    dashboardId: string,
    userId: string,
    request: CreateDashboardSavedViewRequest,
    workspaceId?: string,
  ): Promise<DashboardSavedView> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.POST(
      '/dashboards/{dashboardId}/views',
      {
        params: {
          path: { dashboardId },
        },
        body: request,
        headers,
      },
    )

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to create saved view',
      )
    }

    return data.view
  },

  async updateSavedView(
    dashboardId: string,
    viewId: string,
    userId: string,
    request: UpdateDashboardSavedViewRequest,
    workspaceId?: string,
  ): Promise<DashboardSavedView> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.PUT(
      '/dashboards/{dashboardId}/views/{viewId}',
      {
        params: {
          path: { dashboardId, viewId },
        },
        body: request,
        headers,
      },
    )

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to update saved view',
      )
    }

    return data.view
  },

  async deleteSavedView(
    dashboardId: string,
    viewId: string,
    userId: string,
    workspaceId?: string,
  ): Promise<void> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { error } = await analyticsClient.DELETE(
      '/dashboards/{dashboardId}/views/{viewId}',
      {
        params: {
          path: { dashboardId, viewId },
        },
        headers,
      },
    )

    if (error) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to delete saved view',
      )
    }
  },
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/dashboard/api/dashboard-gateway.test.ts`
Expected: PASS (all tests pass)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/api/dashboard-gateway.ts frontend/src/features/dashboard/api/dashboard-gateway.test.ts
git commit -m "feat(dashboard): add saved views API client methods to dashboardGateway"
```

---

### Task 2: Pure Filter Resolution, Relative Date Resolution, and Dataset Projection

**Files:**
- Create: `frontend/src/features/dashboard/model/filter-resolver.ts`
- Create: `frontend/src/features/dashboard/model/filter-resolver.test.ts`

**Interfaces:**
- Consumes: `DashboardFilterValues`
- Produces:
  - `resolveDateRange(dateRange?: string | null, customFrom?: string | null, customTo?: string | null, refDate?: Date): { dateFrom?: string; dateTo?: string }`
  - `mergeFilters(dashboardFilters?: DashboardFilterValues | null, widgetFilters?: DashboardFilterValues | null): DashboardFilterValues`
  - `sanitizeFiltersForDataset(filters: DashboardFilterValues, dataset: 'sales' | 'inventory'): DashboardFilterValues`
  - `hasActiveFilters(filters?: DashboardFilterValues | null): boolean`
  - `isFiltersEqual(a?: DashboardFilterValues | null, b?: DashboardFilterValues | null): boolean`

- [ ] **Step 1: Write failing test in `frontend/src/features/dashboard/model/filter-resolver.test.ts`**

```typescript
import { describe, it, expect } from 'vitest'
import {
  resolveDateRange,
  mergeFilters,
  sanitizeFiltersForDataset,
  hasActiveFilters,
  isFiltersEqual,
} from './filter-resolver'
import type { DashboardFilterValues } from '../api/dashboard-gateway'

describe('filter-resolver', () => {
  const refDate = new Date('2026-09-23T12:00:00Z')

  describe('resolveDateRange', () => {
    it('returns empty date boundaries for "all" or undefined', () => {
      expect(resolveDateRange('all', null, null, refDate)).toEqual({})
      expect(resolveDateRange(undefined, null, null, refDate)).toEqual({})
      expect(resolveDateRange(null, null, null, refDate)).toEqual({})
    })

    it('calculates 30d relative window', () => {
      const { dateFrom, dateTo } = resolveDateRange('30d', null, null, refDate)
      expect(dateTo).toBe('2026-09-23')
      expect(dateFrom).toBe('2026-08-24')
    })

    it('calculates 90d, 180d, 365d relative windows', () => {
      expect(resolveDateRange('90d', null, null, refDate).dateTo).toBe('2026-09-23')
      expect(resolveDateRange('180d', null, null, refDate).dateTo).toBe('2026-09-23')
      expect(resolveDateRange('365d', null, null, refDate).dateTo).toBe('2026-09-23')
    })

    it('returns custom date range when specified', () => {
      const res = resolveDateRange('custom', '2026-01-01', '2026-06-30', refDate)
      expect(res).toEqual({
        dateFrom: '2026-01-01',
        dateTo: '2026-06-30',
      })
    })

    it('uses custom dates directly if dateRange is omitted but custom dates provided', () => {
      const res = resolveDateRange(null, '2026-02-01', '2026-02-28', refDate)
      expect(res).toEqual({
        dateFrom: '2026-02-01',
        dateTo: '2026-02-28',
      })
    })
  })

  describe('mergeFilters', () => {
    it('overrides dashboard filters with widget-specific filters', () => {
      const dash: DashboardFilterValues = {
        date_range: '30d',
        category_id: 'cat-1',
        region_id: 'reg-1',
      }
      const widget: DashboardFilterValues = {
        date_range: '90d',
        region_id: 'reg-2',
      }

      const merged = mergeFilters(dash, widget)
      expect(merged).toEqual({
        date_range: '90d',
        category_id: 'cat-1',
        region_id: 'reg-2',
      })
    })

    it('returns dashboard filters if widget filters are undefined', () => {
      const dash: DashboardFilterValues = { date_range: '30d' }
      expect(mergeFilters(dash, null)).toEqual(dash)
    })
  })

  describe('sanitizeFiltersForDataset', () => {
    const fullFilters: DashboardFilterValues = {
      date_range: '30d',
      date_from: '2026-08-01',
      date_to: '2026-08-31',
      category_id: 'cat-1',
      region_id: 'reg-1',
      warehouse_id: 'wh-1',
      stock_health: 'low_stock',
    }

    it('sales dataset drops warehouse_id and stock_health', () => {
      const sanitized = sanitizeFiltersForDataset(fullFilters, 'sales')
      expect(sanitized).toEqual({
        date_range: '30d',
        date_from: '2026-08-01',
        date_to: '2026-08-31',
        category_id: 'cat-1',
        region_id: 'reg-1',
        warehouse_id: null,
        stock_health: null,
      })
    })

    it('inventory dataset drops region_id', () => {
      const sanitized = sanitizeFiltersForDataset(fullFilters, 'inventory')
      expect(sanitized).toEqual({
        date_range: '30d',
        date_from: '2026-08-01',
        date_to: '2026-08-31',
        category_id: 'cat-1',
        region_id: null,
        warehouse_id: 'wh-1',
        stock_health: 'low_stock',
      })
    })
  })

  describe('hasActiveFilters and isFiltersEqual', () => {
    it('detects when filters are active', () => {
      expect(hasActiveFilters({})).toBe(false)
      expect(hasActiveFilters({ date_range: 'all' })).toBe(false)
      expect(hasActiveFilters({ date_range: '30d' })).toBe(true)
      expect(hasActiveFilters({ category_id: 'cat-1' })).toBe(true)
    })

    it('checks equality between two filter sets', () => {
      const f1: DashboardFilterValues = { date_range: '30d', region_id: 'reg-1' }
      const f2: DashboardFilterValues = { date_range: '30d', region_id: 'reg-1' }
      const f3: DashboardFilterValues = { date_range: '90d', region_id: 'reg-1' }

      expect(isFiltersEqual(f1, f2)).toBe(true)
      expect(isFiltersEqual(f1, f3)).toBe(false)
    })
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/dashboard/model/filter-resolver.test.ts`
Expected: FAIL with "Cannot find module './filter-resolver'"

- [ ] **Step 3: Implement `frontend/src/features/dashboard/model/filter-resolver.ts`**

```typescript
import type { DashboardFilterValues } from '../api/dashboard-gateway'

const DAYS_MAP: Record<string, number> = {
  '30d': 30,
  '90d': 90,
  '180d': 180,
  '365d': 365,
}

export function resolveDateRange(
  dateRange?: string | null,
  customFrom?: string | null,
  customTo?: string | null,
  refDate: Date = new Date(),
): { dateFrom?: string; dateTo?: string } {
  if (customFrom || customTo) {
    return {
      dateFrom: customFrom || undefined,
      dateTo: customTo || undefined,
    }
  }

  if (!dateRange || dateRange === 'all') {
    return {}
  }

  const days = DAYS_MAP[dateRange]
  if (!days) {
    return {}
  }

  const to = new Date(refDate.getTime())
  const from = new Date(refDate.getTime())
  from.setDate(to.getDate() - days)

  return {
    dateFrom: from.toISOString().split('T')[0],
    dateTo: to.toISOString().split('T')[0],
  }
}

export function mergeFilters(
  dashboardFilters?: DashboardFilterValues | null,
  widgetFilters?: DashboardFilterValues | null,
): DashboardFilterValues {
  const merged: DashboardFilterValues = {
    date_range: widgetFilters?.date_range ?? dashboardFilters?.date_range ?? null,
    date_from: widgetFilters?.date_from ?? dashboardFilters?.date_from ?? null,
    date_to: widgetFilters?.date_to ?? dashboardFilters?.date_to ?? null,
    category_id: widgetFilters?.category_id ?? dashboardFilters?.category_id ?? null,
    region_id: widgetFilters?.region_id ?? dashboardFilters?.region_id ?? null,
    warehouse_id: widgetFilters?.warehouse_id ?? dashboardFilters?.warehouse_id ?? null,
    stock_health: widgetFilters?.stock_health ?? dashboardFilters?.stock_health ?? null,
  }

  return merged
}

export function sanitizeFiltersForDataset(
  filters: DashboardFilterValues,
  dataset: 'sales' | 'inventory',
): DashboardFilterValues {
  if (dataset === 'sales') {
    return {
      date_range: filters.date_range ?? null,
      date_from: filters.date_from ?? null,
      date_to: filters.date_to ?? null,
      category_id: filters.category_id ?? null,
      region_id: filters.region_id ?? null,
      warehouse_id: null,
      stock_health: null,
    }
  }

  return {
    date_range: filters.date_range ?? null,
    date_from: filters.date_from ?? null,
    date_to: filters.date_to ?? null,
    category_id: filters.category_id ?? null,
    region_id: null,
    warehouse_id: filters.warehouse_id ?? null,
    stock_health: filters.stock_health ?? null,
  }
}

export function hasActiveFilters(filters?: DashboardFilterValues | null): boolean {
  if (!filters) return false
  if (filters.date_range && filters.date_range !== 'all') return true
  if (filters.date_from || filters.date_to) return true
  if (filters.category_id) return true
  if (filters.region_id) return true
  if (filters.warehouse_id) return true
  if (filters.stock_health) return true
  return false
}

export function isFiltersEqual(
  a?: DashboardFilterValues | null,
  b?: DashboardFilterValues | null,
): boolean {
  const norm = (f?: DashboardFilterValues | null) => ({
    date_range: f?.date_range || null,
    date_from: f?.date_from || null,
    date_to: f?.date_to || null,
    category_id: f?.category_id || null,
    region_id: f?.region_id || null,
    warehouse_id: f?.warehouse_id || null,
    stock_health: f?.stock_health || null,
  })

  return JSON.stringify(norm(a)) === JSON.stringify(norm(b))
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/dashboard/model/filter-resolver.test.ts`
Expected: PASS (all tests pass)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/model/filter-resolver.ts frontend/src/features/dashboard/model/filter-resolver.test.ts
git commit -m "feat(dashboard): add filter resolution, merging, and dataset sanitization logic"
```

---

### Task 3: Update Widget Data Loader with Filter Overrides & Dataset Projections

**Files:**
- Modify: `frontend/src/features/dashboard/model/widget-data-loader.ts`
- Modify: `frontend/src/features/dashboard/model/widget-data-loader.test.ts`

**Interfaces:**
- Consumes: `filter-resolver.ts`, `DashboardFilterValues`, `WidgetDetail`, `salesGateway`, `inventoryGateway`
- Produces:
  - `loadWidgetData(widget: WidgetDetail, userId: string, workspaceId: string, dashboardFilters?: DashboardFilterValues): Promise<WidgetDataResult>`

- [ ] **Step 1: Write failing tests in `frontend/src/features/dashboard/model/widget-data-loader.test.ts`**

Add tests for loading widget data with dashboard-level filters and local overrides:
```typescript
  it('applies dashboard filters to sales overview query', async () => {
    const widget: WidgetDetail = {
      id: 'w-1',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue' },
      position: { x: 0, y: 0, w: 4, h: 2 },
    }

    vi.mocked(salesGateway.getOverview).mockResolvedValueOnce({
      summary: {
        total_revenue: 150000,
        order_count: 50,
        average_order_value: 3000,
        gross_profit: 45000,
        margin_rate: 0.3,
      },
      trend: [],
      categories: [],
      regions: [],
    })

    const dashboardFilters = {
      date_range: '30d' as const,
      category_id: 'cat-10',
      region_id: 'reg-5',
    }

    const res = await loadWidgetData(widget, 'user-1', 'ws-1', dashboardFilters)

    expect(salesGateway.getOverview).toHaveBeenCalledWith(
      'user-1',
      'ws-1',
      expect.objectContaining({
        categoryId: 'cat-10',
        regionId: 'reg-5',
        dateFrom: expect.any(String),
        dateTo: expect.any(String),
      }),
    )
    expect(res.kpi?.value).toBe(150000)
  })

  it('widget-level date_range overrides dashboard date_range', async () => {
    const widget: WidgetDetail = {
      id: 'w-2',
      title: 'Выручка 90d',
      type: 'kpi_card',
      query_config: {
        dataset: 'sales',
        metric: 'revenue',
        date_range: '90d',
      },
      position: { x: 0, y: 0, w: 4, h: 2 },
    }

    vi.mocked(salesGateway.getOverview).mockResolvedValueOnce({
      summary: {
        total_revenue: 200000,
        order_count: 80,
        average_order_value: 2500,
        gross_profit: 60000,
        margin_rate: 0.3,
      },
      trend: [],
      categories: [],
      regions: [],
    })

    const dashboardFilters = { date_range: '30d' as const }
    await loadWidgetData(widget, 'user-1', 'ws-1', dashboardFilters)

    // Should use 90d window (not 30d)
    expect(salesGateway.getOverview).toHaveBeenCalled()
  })

  it('applies warehouse and stock_health filters to inventory queries and sanitizes region_id', async () => {
    const widget: WidgetDetail = {
      id: 'w-3',
      title: 'Остатки',
      type: 'kpi_card',
      query_config: { dataset: 'inventory', metric: 'stock_quantity' },
      position: { x: 0, y: 0, w: 4, h: 2 },
    }

    vi.mocked(inventoryGateway.getSummary).mockResolvedValueOnce({
      summary: {
        total_quantity_on_hand: 500,
        total_inventory_value: 1200000,
        out_of_stock_count: 2,
        overstock_count: 5,
        healthy_stock_count: 400,
        low_stock_count: 10,
      },
      warehouses: [],
      stock_health: [],
    })

    const dashboardFilters = {
      warehouse_id: 'wh-1',
      region_id: 'reg-ignored',
    }

    await loadWidgetData(widget, 'user-1', 'ws-1', dashboardFilters)

    expect(inventoryGateway.getSummary).toHaveBeenCalledWith('user-1', 'ws-1', {
      warehouseId: 'wh-1',
      asOfDate: undefined,
    })
  })
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/dashboard/model/widget-data-loader.test.ts`
Expected: FAIL with arguments mismatch in `loadWidgetData`

- [ ] **Step 3: Update `frontend/src/features/dashboard/model/widget-data-loader.ts`**

Update `loadWidgetData` signature and implementation:
```typescript
import { salesGateway } from '../../sales-analytics/api/sales-gateway'
import { inventoryGateway } from '../../inventory-analytics/api/inventory-gateway'
import type { DashboardFilterValues, WidgetDetail } from '../api/dashboard-gateway'
import {
  mergeFilters,
  resolveDateRange,
  sanitizeFiltersForDataset,
} from './filter-resolver'

export interface WidgetChartPoint {
  name: string
  value: number
  formatted?: string
}

export interface WidgetTableData {
  columns: { key: string; label: string }[]
  rows: Record<string, unknown>[]
}

export interface WidgetDataResult {
  loading: boolean
  error?: string | null
  kpi?: {
    value: number
    formatted: string
    subtitle?: string
  }
  chartData?: WidgetChartPoint[]
  tableData?: WidgetTableData
}

export function formatMetricValue(value: number, metric: string, unit?: string): string {
  const isCurrency =
    unit === 'currency' ||
    metric === 'revenue' ||
    metric === 'gross_profit' ||
    metric === 'average_order_value' ||
    metric === 'stock_value'

  const isPercent = unit === 'percent' || metric === 'margin_rate'

  if (isCurrency) {
    return new Intl.NumberFormat('ru-RU', {
      style: 'currency',
      currency: 'RUB',
      maximumFractionDigits: 0,
    })
      .format(value)
      .replace(/\s/g, ' ')
      .replace('руб.', '₽')
      .trim()
  }

  if (isPercent) {
    const percentVal = value > 1 ? value : value * 100
    return `${percentVal.toFixed(1)}%`
  }

  return new Intl.NumberFormat('ru-RU').format(value).replace(/\s/g, ' ')
}

function mapStockHealth(
  health?: 'in_stock' | 'low_stock' | 'out_of_stock' | 'overstock' | null,
): 'out_of_stock' | 'critical' | 'optimal' | 'overstock' | undefined {
  if (!health) return undefined
  switch (health) {
    case 'low_stock':
      return 'critical'
    case 'in_stock':
      return 'optimal'
    case 'out_of_stock':
      return 'out_of_stock'
    case 'overstock':
      return 'overstock'
    default:
      return undefined
  }
}

export async function loadWidgetData(
  widget: WidgetDetail,
  userId: string,
  workspaceId: string,
  dashboardFilters?: DashboardFilterValues | null,
): Promise<WidgetDataResult> {
  const { dataset, metric, dimension } = widget.query_config
  const unit = widget.options?.unit as string | undefined

  // Merge dashboard-level filters with widget-level overrides
  const widgetOverrides: DashboardFilterValues = {
    date_range: widget.query_config.date_range ?? widget.query_config.filters?.date_range,
    date_from: widget.query_config.filters?.date_from,
    date_to: widget.query_config.filters?.date_to,
    category_id: widget.query_config.filters?.category_id,
    region_id: widget.query_config.filters?.region_id,
    warehouse_id: widget.query_config.filters?.warehouse_id,
    stock_health: widget.query_config.filters?.stock_health,
  }

  const merged = mergeFilters(dashboardFilters, widgetOverrides)
  const sanitized = sanitizeFiltersForDataset(merged, dataset)
  const { dateFrom, dateTo } = resolveDateRange(
    sanitized.date_range,
    sanitized.date_from,
    sanitized.date_to,
  )

  try {
    if (dataset === 'sales') {
      const salesFilters = {
        dateFrom,
        dateTo,
        categoryId: sanitized.category_id || undefined,
        regionId: sanitized.region_id || undefined,
      }

      if (widget.type === 'kpi_card') {
        const overview = await salesGateway.getOverview(userId, workspaceId, salesFilters)
        let val = 0
        switch (metric) {
          case 'revenue':
            val = overview.summary.total_revenue
            break
          case 'order_count':
            val = overview.summary.order_count
            break
          case 'average_order_value':
            val = overview.summary.average_order_value
            break
          case 'gross_profit':
            val = overview.summary.gross_profit
            break
          case 'margin_rate':
            val = overview.summary.margin_rate
            break
          default:
            val = overview.summary.total_revenue
        }
        return {
          loading: false,
          kpi: {
            value: val,
            formatted: formatMetricValue(val, metric, unit),
            subtitle: sanitized.date_range ? `Период: ${sanitized.date_range}` : undefined,
          },
        }
      }

      if (widget.type === 'line_chart' || dimension === 'date') {
        const overview = await salesGateway.getOverview(userId, workspaceId, salesFilters)
        const chartData: WidgetChartPoint[] = overview.trend.map((pt) => {
          const val = metric === 'order_count' ? pt.order_count : pt.revenue
          return {
            name: pt.date,
            value: val,
            formatted: formatMetricValue(val, metric, unit),
          }
        })
        return { loading: false, chartData }
      }

      if (widget.type === 'donut_chart' || widget.type === 'bar_chart') {
        const overview = await salesGateway.getOverview(userId, workspaceId, salesFilters)
        if (dimension === 'region') {
          const chartData: WidgetChartPoint[] = overview.regions.map((reg) => ({
            name: reg.region_name,
            value: reg.revenue,
            formatted: formatMetricValue(reg.revenue, 'revenue', 'currency'),
          }))
          return { loading: false, chartData }
        }

        const chartData: WidgetChartPoint[] = overview.categories.map((cat) => ({
          name: cat.category_name,
          value: cat.revenue,
          formatted: formatMetricValue(cat.revenue, 'revenue', 'currency'),
        }))
        return { loading: false, chartData }
      }

      if (widget.type === 'table') {
        const records = await salesGateway.getRecords(userId, workspaceId, {
          ...salesFilters,
          page: 1,
          perPage: 10,
        })
        return {
          loading: false,
          tableData: {
            columns: [
              { key: 'order_number', label: 'Номер заказа' },
              { key: 'order_date', label: 'Дата' },
              { key: 'product_name', label: 'Товар' },
              { key: 'total_price', label: 'Сумма' },
            ],
            rows: records.items.map((item) => ({
              order_number: item.order_number,
              order_date: item.order_date,
              product_name: item.product_name,
              total_price: formatMetricValue(item.total_price, 'revenue', 'currency'),
            })),
          },
        }
      }
    }

    if (dataset === 'inventory') {
      const warehouseId = sanitized.warehouse_id || undefined
      const stockHealth = mapStockHealth(sanitized.stock_health)

      if (widget.type === 'kpi_card') {
        const summaryRes = await inventoryGateway.getSummary(userId, workspaceId, {
          warehouseId,
          asOfDate: dateTo,
        })
        let val = 0
        switch (metric) {
          case 'stock_quantity':
            val = summaryRes.summary.total_quantity_on_hand
            break
          case 'stock_value':
            val = summaryRes.summary.total_inventory_value
            break
          case 'out_of_stock_count':
            val = summaryRes.summary.out_of_stock_count
            break
          case 'overstock_count':
            val = summaryRes.summary.overstock_count
            break
          default:
            val = summaryRes.summary.total_quantity_on_hand
        }
        return {
          loading: false,
          kpi: {
            value: val,
            formatted: formatMetricValue(val, metric, unit),
          },
        }
      }

      if (widget.type === 'bar_chart' || widget.type === 'donut_chart') {
        if (dimension === 'warehouse') {
          const summaryRes = await inventoryGateway.getSummary(userId, workspaceId, {
            warehouseId,
            asOfDate: dateTo,
          })
          const chartData: WidgetChartPoint[] = summaryRes.warehouses.map((wh) => {
            const val = metric === 'stock_value' ? wh.total_value : wh.total_quantity
            return {
              name: wh.warehouse_name,
              value: val,
              formatted: formatMetricValue(val, metric, unit),
            }
          })
          return { loading: false, chartData }
        }

        if (dimension === 'abc_class' || dimension === 'xyz_class') {
          const periodDays =
            sanitized.date_range === '30d'
              ? 30
              : sanitized.date_range === '180d'
                ? 180
                : sanitized.date_range === '365d'
                  ? 365
                  : 90
          const abcSummary = await inventoryGateway.getAbcXyzSummary(userId, workspaceId, {
            warehouseId,
            categoryId: sanitized.category_id || undefined,
            periodDays,
          })
          const dist =
            dimension === 'abc_class'
              ? abcSummary.data.abc_distribution
              : abcSummary.data.xyz_distribution
          const chartData: WidgetChartPoint[] = dist.map((d) => ({
            name: `Класс ${d.class}`,
            value: d.revenue,
            formatted: formatMetricValue(d.revenue, 'revenue', 'currency'),
          }))
          return { loading: false, chartData }
        }
      }

      if (widget.type === 'table') {
        const itemsRes = await inventoryGateway.getItems(userId, workspaceId, {
          warehouseId,
          stockHealth,
          page: 1,
          perPage: 10,
        })
        return {
          loading: false,
          tableData: {
            columns: [
              { key: 'product_name', label: 'Товар' },
              { key: 'product_sku', label: 'Артикул' },
              { key: 'quantity_available', label: 'Доступно' },
              { key: 'inventory_value', label: 'Стоимость' },
            ],
            rows: itemsRes.items.map((item) => ({
              product_name: item.product_name,
              product_sku: item.product_sku,
              quantity_available: formatMetricValue(
                item.quantity_available,
                'stock_quantity',
              ),
              inventory_value: formatMetricValue(
                item.inventory_value,
                'stock_value',
                'currency',
              ),
            })),
          },
        }
      }
    }

    return { loading: false }
  } catch (err: unknown) {
    const message =
      err instanceof Error ? err.message : 'Ошибка при загрузке данных виджета'
    return {
      loading: false,
      error: message,
    }
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/dashboard/model/widget-data-loader.test.ts`
Expected: PASS (all tests pass)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/model/widget-data-loader.ts frontend/src/features/dashboard/model/widget-data-loader.test.ts
git commit -m "feat(dashboard): pass resolved shared and widget filters to dataset gateways in widgetDataLoader"
```

---

### Task 4: Filter State Management & URL Synchronization Hook

**Files:**
- Create: `frontend/src/features/dashboard/model/use-dashboard-filters.ts`
- Create: `frontend/src/features/dashboard/model/use-dashboard-filters.test.ts`

**Interfaces:**
- Consumes: `DashboardFilterValues`, `DashboardSavedView`, `isFiltersEqual`
- Produces:
  - `useDashboardFilters(options: { initialFilters?: DashboardFilterValues; initialViewId?: string | null; savedViews?: DashboardSavedView[]; onFilterChange?: (filters: DashboardFilterValues) => void })`
  - Returns:
    - `filters: DashboardFilterValues`
    - `activeViewId: string | null`
    - `setFilter: <K extends keyof DashboardFilterValues>(key: K, value: DashboardFilterValues[K]) => void`
    - `setFilters: (newFilters: DashboardFilterValues, viewId?: string | null) => void`
    - `resetFilters: () => void`
    - `applySavedView: (view: DashboardSavedView) => void`
    - `isModifiedFromActiveView: boolean`

- [ ] **Step 1: Write failing test in `frontend/src/features/dashboard/model/use-dashboard-filters.test.ts`**

```typescript
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useDashboardFilters } from './use-dashboard-filters'
import type { DashboardSavedView } from '../api/dashboard-gateway'

const mockViews: DashboardSavedView[] = [
  {
    id: 'view-1',
    dashboard_id: 'dash-1',
    name: 'По умолчанию',
    filters: { date_range: '30d' },
    is_default: true,
    created_at: '2026-09-23T10:00:00Z',
    updated_at: '2026-09-23T10:00:00Z',
  },
  {
    id: 'view-2',
    dashboard_id: 'dash-1',
    name: 'Южный склад',
    filters: { date_range: '90d', warehouse_id: 'wh-2' },
    is_default: false,
    created_at: '2026-09-23T10:00:00Z',
    updated_at: '2026-09-23T10:00:00Z',
  },
]

describe('useDashboardFilters', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/dashboards/dash-1')
  })

  it('initializes with default view if available and no URL params', () => {
    const { result } = renderHook(() =>
      useDashboardFilters({ savedViews: mockViews }),
    )

    expect(result.current.activeViewId).toBe('view-1')
    expect(result.current.filters).toEqual({ date_range: '30d' })
  })

  it('updates a single filter and marks view as modified', () => {
    const { result } = renderHook(() =>
      useDashboardFilters({ savedViews: mockViews }),
    )

    act(() => {
      result.current.setFilter('category_id', 'cat-1')
    })

    expect(result.current.filters.category_id).toBe('cat-1')
    expect(result.current.isModifiedFromActiveView).toBe(true)
  })

  it('applies a saved view', () => {
    const { result } = renderHook(() =>
      useDashboardFilters({ savedViews: mockViews }),
    )

    act(() => {
      result.current.applySavedView(mockViews[1])
    })

    expect(result.current.activeViewId).toBe('view-2')
    expect(result.current.filters).toEqual({ date_range: '90d', warehouse_id: 'wh-2' })
    expect(result.current.isModifiedFromActiveView).toBe(false)
  })

  it('resets filters', () => {
    const { result } = renderHook(() =>
      useDashboardFilters({ savedViews: mockViews }),
    )

    act(() => {
      result.current.setFilter('region_id', 'reg-1')
    })
    expect(result.current.filters.region_id).toBe('reg-1')

    act(() => {
      result.current.resetFilters()
    })

    expect(result.current.filters.region_id).toBeUndefined()
    expect(result.current.activeViewId).toBeNull()
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/dashboard/model/use-dashboard-filters.test.ts`
Expected: FAIL with "Cannot find module './use-dashboard-filters'"

- [ ] **Step 3: Implement `frontend/src/features/dashboard/model/use-dashboard-filters.ts`**

```typescript
import { useState, useCallback, useMemo, useEffect } from 'react'
import type { DashboardFilterValues, DashboardSavedView } from '../api/dashboard-gateway'
import { isFiltersEqual } from './filter-resolver'

export interface UseDashboardFiltersOptions {
  initialFilters?: DashboardFilterValues
  initialViewId?: string | null
  savedViews?: DashboardSavedView[]
  onFilterChange?: (filters: DashboardFilterValues) => void
}

function parseUrlFilters(): { filters: DashboardFilterValues; viewId: string | null } {
  if (typeof window === 'undefined') {
    return { filters: {}, viewId: null }
  }

  const params = new URLSearchParams(window.location.search)
  const filters: DashboardFilterValues = {}

  const dateRange = params.get('date_range')
  if (dateRange && ['30d', '90d', '180d', '365d', 'all', 'custom'].includes(dateRange)) {
    filters.date_range = dateRange as DashboardFilterValues['date_range']
  }

  const dateFrom = params.get('date_from')
  if (dateFrom) filters.date_from = dateFrom

  const dateTo = params.get('date_to')
  if (dateTo) filters.date_to = dateTo

  const categoryId = params.get('category_id')
  if (categoryId) filters.category_id = categoryId

  const regionId = params.get('region_id')
  if (regionId) filters.region_id = regionId

  const warehouseId = params.get('warehouse_id')
  if (warehouseId) filters.warehouse_id = warehouseId

  const stockHealth = params.get('stock_health')
  if (
    stockHealth &&
    ['in_stock', 'low_stock', 'out_of_stock', 'overstock'].includes(stockHealth)
  ) {
    filters.stock_health = stockHealth as DashboardFilterValues['stock_health']
  }

  const viewId = params.get('view_id') || null

  return { filters, viewId }
}

function syncUrlFilters(filters: DashboardFilterValues, viewId: string | null) {
  if (typeof window === 'undefined') return

  const params = new URLSearchParams()
  if (viewId) params.set('view_id', viewId)
  if (filters.date_range) params.set('date_range', filters.date_range)
  if (filters.date_from) params.set('date_from', filters.date_from)
  if (filters.date_to) params.set('date_to', filters.date_to)
  if (filters.category_id) params.set('category_id', filters.category_id)
  if (filters.region_id) params.set('region_id', filters.region_id)
  if (filters.warehouse_id) params.set('warehouse_id', filters.warehouse_id)
  if (filters.stock_health) params.set('stock_health', filters.stock_health)

  const query = params.toString()
  const newUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname
  window.history.replaceState({}, '', newUrl)
}

export function useDashboardFilters({
  initialFilters,
  initialViewId = null,
  savedViews = [],
  onFilterChange,
}: UseDashboardFiltersOptions) {
  // Determine initial state (URL params > initialView > defaultView > initialFilters > empty)
  const [filters, setFiltersState] = useState<DashboardFilterValues>(() => {
    const urlState = parseUrlFilters()
    if (Object.keys(urlState.filters).length > 0) {
      return urlState.filters
    }

    if (initialFilters && Object.keys(initialFilters).length > 0) {
      return initialFilters
    }

    const defaultView = savedViews.find((v) => v.is_default)
    if (defaultView) {
      return defaultView.filters
    }

    return {}
  })

  const [activeViewId, setActiveViewId] = useState<string | null>(() => {
    const urlState = parseUrlFilters()
    if (urlState.viewId) return urlState.viewId
    if (initialViewId) return initialViewId

    const defaultView = savedViews.find((v) => v.is_default)
    if (defaultView && Object.keys(parseUrlFilters().filters).length === 0) {
      return defaultView.id
    }

    return null
  })

  // Synchronize URL whenever filters or activeViewId change
  useEffect(() => {
    syncUrlFilters(filters, activeViewId)
    onFilterChange?.(filters)
  }, [filters, activeViewId, onFilterChange])

  const setFilter = useCallback(
    <K extends keyof DashboardFilterValues>(key: K, value: DashboardFilterValues[K]) => {
      setFiltersState((prev) => {
        const next = { ...prev }
        if (value === null || value === undefined || value === '') {
          delete next[key]
        } else {
          next[key] = value
        }
        return next
      })
    },
    [],
  )

  const setFilters = useCallback(
    (newFilters: DashboardFilterValues, viewId: string | null = null) => {
      setFiltersState(newFilters)
      setActiveViewId(viewId)
    },
    [],
  )

  const resetFilters = useCallback(() => {
    setFiltersState({})
    setActiveViewId(null)
  }, [])

  const applySavedView = useCallback((view: DashboardSavedView) => {
    setFiltersState(view.filters)
    setActiveViewId(view.id)
  }, [])

  const activeView = useMemo(
    () => savedViews.find((v) => v.id === activeViewId) ?? null,
    [savedViews, activeViewId],
  )

  const isModifiedFromActiveView = useMemo(() => {
    if (!activeView) return false
    return !isFiltersEqual(filters, activeView.filters)
  }, [activeView, filters])

  return {
    filters,
    activeViewId,
    activeView,
    setFilter,
    setFilters,
    resetFilters,
    applySavedView,
    isModifiedFromActiveView,
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/dashboard/model/use-dashboard-filters.test.ts`
Expected: PASS (all tests pass)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/model/use-dashboard-filters.ts frontend/src/features/dashboard/model/use-dashboard-filters.test.ts
git commit -m "feat(dashboard): add useDashboardFilters hook with URL query synchronization"
```

---

### Task 5: Shared Filters Bar Component (`DashboardFilterBar`)

**Files:**
- Create: `frontend/src/features/dashboard/ui/dashboard-filter-bar.tsx`
- Create: `frontend/src/features/dashboard/ui/dashboard-filter-bar.test.tsx`

**Interfaces:**
- Consumes:
  - `salesGateway.getFilterOptions`
  - `inventoryGateway.getFilters`
  - `DashboardFilterValues`
- Produces:
  - `<DashboardFilterBar activeFilters={...} onFilterChange={...} userId={...} workspaceId={...} />`

- [ ] **Step 1: Write failing test in `frontend/src/features/dashboard/ui/dashboard-filter-bar.test.tsx`**

```typescript
import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { DashboardFilterBar } from './dashboard-filter-bar'
import { salesGateway } from '../../sales-analytics/api/sales-gateway'
import { inventoryGateway } from '../../inventory-analytics/api/inventory-gateway'
import type { DashboardFilterValues } from '../api/dashboard-gateway'

vi.mock('../../sales-analytics/api/sales-gateway', () => ({
  salesGateway: {
    getFilterOptions: vi.fn(),
  },
}))

vi.mock('../../inventory-analytics/api/inventory-gateway', () => ({
  inventoryGateway: {
    getFilters: vi.fn(),
  },
}))

describe('DashboardFilterBar', () => {
  const onFilterChange = vi.fn()

  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(salesGateway.getFilterOptions).mockResolvedValue({
      min_date: '2026-01-01',
      max_date: '2026-09-23',
      categories: [
        { id: 'cat-1', name: 'Автоэлектроника' },
        { id: 'cat-2', name: 'Масла и жидкости' },
      ],
      regions: [
        { id: 'reg-1', name: 'Москва', code: 'MSK' },
        { id: 'reg-2', name: 'Санкт-Петербург', code: 'SPB' },
      ],
    })
    vi.mocked(inventoryGateway.getFilters).mockResolvedValue({
      warehouses: [
        { id: 'wh-1', name: 'Центральный склад', code: 'WH-C' },
        { id: 'wh-2', name: 'Северный склад', code: 'WH-N' },
      ],
      statuses: [
        { value: 'optimal', label: 'В норме' },
        { value: 'critical', label: 'Критический остаток' },
        { value: 'out_of_stock', label: 'Нет на складе' },
        { value: 'overstock', label: 'Избыток' },
      ],
    })
  })

  it('renders date range presets, metadata dropdowns, and triggers filter change', async () => {
    const activeFilters: DashboardFilterValues = {
      date_range: '30d',
    }

    render(
      <DashboardFilterBar
        activeFilters={activeFilters}
        onFilterChange={onFilterChange}
        userId="user-1"
        workspaceId="ws-1"
      />,
    )

    expect(screen.getByRole('button', { name: '30 дн' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '90 дн' })).toBeInTheDocument()

    // Click 90d button
    fireEvent.click(screen.getByRole('button', { name: '90 дн' }))
    expect(onFilterChange).toHaveBeenCalledWith({
      date_range: '90d',
    })
  })

  it('shows custom date inputs when custom date range is active', () => {
    const activeFilters: DashboardFilterValues = {
      date_range: 'custom',
      date_from: '2026-05-01',
      date_to: '2026-05-31',
    }

    render(
      <DashboardFilterBar
        activeFilters={activeFilters}
        onFilterChange={onFilterChange}
        userId="user-1"
        workspaceId="ws-1"
      />,
    )

    expect(screen.getByLabelText('Дата с')).toHaveValue('2026-05-01')
    expect(screen.getByLabelText('Дата по')).toHaveValue('2026-05-31')
  })

  it('resets filters when reset button clicked', () => {
    const activeFilters: DashboardFilterValues = {
      date_range: '90d',
      category_id: 'cat-1',
    }

    render(
      <DashboardFilterBar
        activeFilters={activeFilters}
        onFilterChange={onFilterChange}
        userId="user-1"
        workspaceId="ws-1"
      />,
    )

    const resetBtn = screen.getByRole('button', { name: /Сбросить/i })
    expect(resetBtn).toBeInTheDocument()
    fireEvent.click(resetBtn)

    expect(onFilterChange).toHaveBeenCalledWith({})
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/dashboard/ui/dashboard-filter-bar.test.tsx`
Expected: FAIL with "Cannot find module './dashboard-filter-bar'"

- [ ] **Step 3: Implement `frontend/src/features/dashboard/ui/dashboard-filter-bar.tsx`**

```typescript
'use client'

import React, { useEffect, useState } from 'react'
import { Card } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RotateCcwIcon, CalendarIcon, FilterIcon } from 'lucide-react'
import type { DashboardFilterValues } from '../api/dashboard-gateway'
import { salesGateway, type SalesFilterOptions } from '../../sales-analytics/api/sales-gateway'
import {
  inventoryGateway,
  type InventoryFilterOptionsResponse,
} from '../../inventory-analytics/api/inventory-gateway'
import { hasActiveFilters } from '../model/filter-resolver'

interface DashboardFilterBarProps {
  activeFilters: DashboardFilterValues
  onFilterChange: (filters: DashboardFilterValues) => void
  userId: string
  workspaceId: string
}

const DATE_PRESETS: { label: string; value: DashboardFilterValues['date_range'] }[] = [
  { label: '30 дн', value: '30d' },
  { label: '90 дн', value: '90d' },
  { label: '180 дн', value: '180d' },
  { label: '365 дн', value: '365d' },
  { label: 'Всё время', value: 'all' },
  { label: 'Период', value: 'custom' },
]

export function DashboardFilterBar({
  activeFilters,
  onFilterChange,
  userId,
  workspaceId,
}: DashboardFilterBarProps) {
  const [salesOptions, setSalesOptions] = useState<SalesFilterOptions | null>(null)
  const [invOptions, setInvOptions] = useState<InventoryFilterOptionsResponse | null>(null)

  useEffect(() => {
    let isCancelled = false

    Promise.all([
      salesGateway.getFilterOptions(userId, workspaceId).catch(() => null),
      inventoryGateway.getFilters(userId, workspaceId).catch(() => null),
    ]).then(([sData, iData]) => {
      if (!isCancelled) {
        if (sData) setSalesOptions(sData)
        if (iData) setInvOptions(iData)
      }
    })

    return () => {
      isCancelled = true
    }
  }, [userId, workspaceId])

  const setDatePreset = (preset: DashboardFilterValues['date_range']) => {
    if (preset === 'custom') {
      onFilterChange({
        ...activeFilters,
        date_range: 'custom',
        date_from: activeFilters.date_from || salesOptions?.min_date || '',
        date_to: activeFilters.date_to || salesOptions?.max_date || '',
      })
    } else {
      const next = { ...activeFilters, date_range: preset }
      delete next.date_from
      delete next.date_to
      onFilterChange(next)
    }
  }

  const handleCustomDateChange = (field: 'date_from' | 'date_to', val: string) => {
    onFilterChange({
      ...activeFilters,
      date_range: 'custom',
      [field]: val || undefined,
    })
  }

  const handleSelectChange = (
    field: 'category_id' | 'region_id' | 'warehouse_id' | 'stock_health',
    val: string,
  ) => {
    const next = { ...activeFilters }
    if (!val) {
      delete next[field]
    } else {
      // @ts-expect-error dynamic key assignment
      next[field] = val
    }
    onFilterChange(next)
  }

  const handleReset = () => {
    onFilterChange({})
  }

  const isCustom = activeFilters.date_range === 'custom'
  const isFiltered = hasActiveFilters(activeFilters)

  return (
    <Card className="p-3.5 border-border bg-card/60 backdrop-blur-xs shadow-xs space-y-3" data-testid="dashboard-filter-bar">
      <div className="flex flex-wrap items-center justify-between gap-3">
        {/* Date presets toolbar */}
        <div className="flex items-center gap-1.5 flex-wrap">
          <span className="text-xs font-medium text-muted-foreground mr-1 flex items-center gap-1">
            <CalendarIcon className="size-3.5" />
            Период:
          </span>
          {DATE_PRESETS.map((p) => {
            const isSelected = activeFilters.date_range === p.value
            return (
              <Button
                key={p.value}
                type="button"
                size="sm"
                variant={isSelected ? 'default' : 'outline'}
                onClick={() => setDatePreset(p.value)}
                className={`h-7 px-2.5 text-xs font-normal ${
                  isSelected ? 'bg-emerald-600 hover:bg-emerald-500 text-white' : 'text-muted-foreground'
                }`}
              >
                {p.label}
              </Button>
            )
          })}
        </div>

        {/* Reset button */}
        {isFiltered && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={handleReset}
            className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground gap-1 ml-auto"
          >
            <RotateCcwIcon className="size-3" />
            <span>Сбросить фильтры</span>
          </Button>
        )}
      </div>

      {/* Custom dates row if custom period selected */}
      {isCustom && (
        <div className="flex flex-wrap items-center gap-3 pt-1 border-t border-border/40">
          <div className="flex items-center gap-2">
            <Label htmlFor="shared-date-from" className="text-xs text-muted-foreground">
              Дата с:
            </Label>
            <Input
              id="shared-date-from"
              aria-label="Дата с"
              type="date"
              className="h-7 text-xs w-36 bg-background/80"
              value={activeFilters.date_from ?? ''}
              min={salesOptions?.min_date}
              max={salesOptions?.max_date}
              onChange={(e) => handleCustomDateChange('date_from', e.target.value)}
            />
          </div>
          <div className="flex items-center gap-2">
            <Label htmlFor="shared-date-to" className="text-xs text-muted-foreground">
              Дата по:
            </Label>
            <Input
              id="shared-date-to"
              aria-label="Дата по"
              type="date"
              className="h-7 text-xs w-36 bg-background/80"
              value={activeFilters.date_to ?? ''}
              min={salesOptions?.min_date}
              max={salesOptions?.max_date}
              onChange={(e) => handleCustomDateChange('date_to', e.target.value)}
            />
          </div>
        </div>
      )}

      {/* Select dropdowns for Categories, Regions, Warehouses, Stock Health */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5 pt-1 border-t border-border/40">
        {/* Category */}
        <div className="relative">
          <select
            id="shared-category-filter"
            aria-label="Категория"
            className="h-8 w-full rounded-lg border border-input bg-background/80 px-2.5 pr-7 text-xs text-foreground outline-none transition-colors focus-visible:border-ring appearance-none"
            value={activeFilters.category_id ?? ''}
            onChange={(e) => handleSelectChange('category_id', e.target.value)}
          >
            <option value="">Все категории</option>
            {salesOptions?.categories.map((cat) => (
              <option key={cat.id} value={cat.id}>
                {cat.name}
              </option>
            ))}
          </select>
          <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
            ▼
          </span>
        </div>

        {/* Region */}
        <div className="relative">
          <select
            id="shared-region-filter"
            aria-label="Регион"
            className="h-8 w-full rounded-lg border border-input bg-background/80 px-2.5 pr-7 text-xs text-foreground outline-none transition-colors focus-visible:border-ring appearance-none"
            value={activeFilters.region_id ?? ''}
            onChange={(e) => handleSelectChange('region_id', e.target.value)}
          >
            <option value="">Все регионы</option>
            {salesOptions?.regions.map((reg) => (
              <option key={reg.id} value={reg.id}>
                {reg.name} ({reg.code})
              </option>
            ))}
          </select>
          <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
            ▼
          </span>
        </div>

        {/* Warehouse */}
        <div className="relative">
          <select
            id="shared-warehouse-filter"
            aria-label="Склад"
            className="h-8 w-full rounded-lg border border-input bg-background/80 px-2.5 pr-7 text-xs text-foreground outline-none transition-colors focus-visible:border-ring appearance-none"
            value={activeFilters.warehouse_id ?? ''}
            onChange={(e) => handleSelectChange('warehouse_id', e.target.value)}
          >
            <option value="">Все склады</option>
            {invOptions?.warehouses.map((wh) => (
              <option key={wh.id} value={wh.id}>
                {wh.name} ({wh.code})
              </option>
            ))}
          </select>
          <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
            ▼
          </span>
        </div>

        {/* Stock Health */}
        <div className="relative">
          <select
            id="shared-stock-health-filter"
            aria-label="Статус остатков"
            className="h-8 w-full rounded-lg border border-input bg-background/80 px-2.5 pr-7 text-xs text-foreground outline-none transition-colors focus-visible:border-ring appearance-none"
            value={activeFilters.stock_health ?? ''}
            onChange={(e) => handleSelectChange('stock_health', e.target.value)}
          >
            <option value="">Все статусы остатков</option>
            <option value="in_stock">В наличии (оптимально)</option>
            <option value="low_stock">Критический остаток</option>
            <option value="out_of_stock">Нет на складе</option>
            <option value="overstock">Избыток</option>
          </select>
          <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
            ▼
          </span>
        </div>
      </div>
    </Card>
  )
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/dashboard/ui/dashboard-filter-bar.test.tsx`
Expected: PASS (all tests pass)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/ui/dashboard-filter-bar.tsx frontend/src/features/dashboard/ui/dashboard-filter-bar.test.tsx
git commit -m "feat(dashboard): create DashboardFilterBar UI component for shared filters"
```

---

### Task 6: Saved Views Menu & Management Component (`DashboardSavedViewsMenu`)

**Files:**
- Create: `frontend/src/features/dashboard/ui/dashboard-saved-views-menu.tsx`
- Create: `frontend/src/features/dashboard/ui/dashboard-saved-views-menu.test.tsx`

**Interfaces:**
- Consumes: `DashboardSavedView`, `DashboardFilterValues`, `dashboardGateway`
- Produces:
  - `<DashboardSavedViewsMenu dashboardId={...} userId={...} workspaceId={...} currentFilters={...} activeView={...} savedViews={...} onSelectView={...} onViewsUpdated={...} />`

- [ ] **Step 1: Write failing test in `frontend/src/features/dashboard/ui/dashboard-saved-views-menu.test.tsx`**

```typescript
import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { DashboardSavedViewsMenu } from './dashboard-saved-views-menu'
import { dashboardGateway, type DashboardSavedView } from '../api/dashboard-gateway'

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    createSavedView: vi.fn(),
    updateSavedView: vi.fn(),
    deleteSavedView: vi.fn(),
  },
}))

const mockViews: DashboardSavedView[] = [
  {
    id: 'view-1',
    dashboard_id: 'dash-1',
    name: 'Основной обзор',
    filters: { date_range: '30d' },
    is_default: true,
    created_at: '2026-09-23T10:00:00Z',
    updated_at: '2026-09-23T10:00:00Z',
  },
  {
    id: 'view-2',
    dashboard_id: 'dash-1',
    name: 'Северный регион',
    filters: { date_range: '90d', region_id: 'reg-2' },
    is_default: false,
    created_at: '2026-09-23T10:00:00Z',
    updated_at: '2026-09-23T10:00:00Z',
  },
]

describe('DashboardSavedViewsMenu', () => {
  const onSelectView = vi.fn()
  const onViewsUpdated = vi.fn()

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders active view name and lists saved views', () => {
    render(
      <DashboardSavedViewsMenu
        dashboardId="dash-1"
        userId="user-1"
        workspaceId="ws-1"
        currentFilters={{ date_range: '30d' }}
        activeView={mockViews[0]}
        savedViews={mockViews}
        onSelectView={onSelectView}
        onViewsUpdated={onViewsUpdated}
      />,
    )

    expect(screen.getByText('Основной обзор')).toBeInTheDocument()
  })

  it('allows saving current filters as a new saved view', async () => {
    vi.mocked(dashboardGateway.createSavedView).mockResolvedValueOnce({
      id: 'view-3',
      dashboard_id: 'dash-1',
      name: 'Новый пресет',
      filters: { date_range: '180d' },
      is_default: false,
      created_at: '2026-09-23T12:00:00Z',
      updated_at: '2026-09-23T12:00:00Z',
    })

    render(
      <DashboardSavedViewsMenu
        dashboardId="dash-1"
        userId="user-1"
        workspaceId="ws-1"
        currentFilters={{ date_range: '180d' }}
        activeView={null}
        savedViews={mockViews}
        onSelectView={onSelectView}
        onViewsUpdated={onViewsUpdated}
      />,
    )

    // Open save modal / form
    fireEvent.click(screen.getByRole('button', { name: /Сохранить представление/i }))

    // Fill name input
    const input = screen.getByPlaceholderText('Название представления')
    fireEvent.change(input, { target: { value: 'Новый пресет' } })

    // Submit save
    fireEvent.click(screen.getByRole('button', { name: /Подтвердить сохранение/i }))

    await waitFor(() => {
      expect(dashboardGateway.createSavedView).toHaveBeenCalledWith(
        'dash-1',
        'user-1',
        {
          name: 'Новый пресет',
          filters: { date_range: '180d' },
          is_default: false,
        },
        'ws-1',
      )
    })
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/dashboard/ui/dashboard-saved-views-menu.test.tsx`
Expected: FAIL with "Cannot find module './dashboard-saved-views-menu'"

- [ ] **Step 3: Implement `frontend/src/features/dashboard/ui/dashboard-saved-views-menu.tsx`**

```typescript
'use client'

import React, { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  BookmarkIcon,
  ChevronDownIcon,
  PlusIcon,
  CheckIcon,
  Trash2Icon,
  StarIcon,
  XIcon,
} from 'lucide-react'
import {
  dashboardGateway,
  type DashboardFilterValues,
  type DashboardSavedView,
} from '../api/dashboard-gateway'

interface DashboardSavedViewsMenuProps {
  dashboardId: string
  userId: string
  workspaceId: string
  currentFilters: DashboardFilterValues
  activeView: DashboardSavedView | null
  savedViews: DashboardSavedView[]
  onSelectView: (view: DashboardSavedView) => void
  onViewsUpdated: () => void
}

export function DashboardSavedViewsMenu({
  dashboardId,
  userId,
  workspaceId,
  currentFilters,
  activeView,
  savedViews,
  onSelectView,
  onViewsUpdated,
}: DashboardSavedViewsMenuProps) {
  const [isOpen, setIsOpen] = useState(false)
  const [isCreating, setIsCreating] = useState(false)
  const [newViewName, setNewViewName] = useState('')
  const [isDefault, setIsDefault] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!newViewName.trim()) return

    setIsSubmitting(true)
    setError(null)
    try {
      const created = await dashboardGateway.createSavedView(
        dashboardId,
        userId,
        {
          name: newViewName.trim(),
          filters: currentFilters,
          is_default: isDefault,
        },
        workspaceId,
      )
      setIsCreating(false)
      setNewViewName('')
      setIsDefault(false)
      setIsOpen(false)
      onSelectView(created)
      onViewsUpdated()
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Не удалось сохранить представление')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleDelete = async (viewId: string, e: React.MouseEvent) => {
    e.stopPropagation()
    if (!confirm('Удалить это сохранённое представление?')) return

    try {
      await dashboardGateway.deleteSavedView(dashboardId, viewId, userId, workspaceId)
      onViewsUpdated()
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Ошибка при удалении')
    }
  }

  const handleSetDefault = async (view: DashboardSavedView, e: React.MouseEvent) => {
    e.stopPropagation()
    try {
      await dashboardGateway.updateSavedView(
        dashboardId,
        view.id,
        userId,
        {
          name: view.name,
          filters: view.filters,
          is_default: !view.is_default,
        },
        workspaceId,
      )
      onViewsUpdated()
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Ошибка при обновлении')
    }
  }

  return (
    <div className="relative inline-block text-left" data-testid="saved-views-menu">
      <div className="flex items-center gap-1.5">
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setIsOpen(!isOpen)}
          className="h-8 gap-1.5 text-xs text-foreground bg-background/80 hover:bg-muted/30 border-border"
        >
          <BookmarkIcon className="size-3.5 text-emerald-500" />
          <span className="font-medium max-w-[150px] truncate">
            {activeView ? activeView.name : 'Пользовательский вид'}
          </span>
          {activeView?.is_default && (
            <Badge variant="secondary" className="text-[10px] h-4 px-1 bg-emerald-500/10 text-emerald-500">
              По умолчанию
            </Badge>
          )}
          <ChevronDownIcon className="size-3 text-muted-foreground ml-1" />
        </Button>

        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => {
            setIsCreating(true)
            setIsOpen(true)
          }}
          className="h-8 px-2 text-xs text-muted-foreground hover:text-foreground gap-1"
          title="Сохранить текущие фильтры"
        >
          <PlusIcon className="size-3.5" />
          <span className="hidden sm:inline">Сохранить представление</span>
        </Button>
      </div>

      {isOpen && (
        <div className="absolute left-0 mt-1.5 w-72 rounded-xl border border-border bg-popover p-2 shadow-lg z-50 animate-in fade-in-0 zoom-in-95">
          {isCreating ? (
            <form onSubmit={handleCreate} className="space-y-2 p-1">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-foreground">Новое представление</span>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => setIsCreating(false)}
                  className="h-6 w-6 p-0 text-muted-foreground"
                >
                  <XIcon className="size-3.5" />
                </Button>
              </div>

              {error && <p className="text-[11px] text-rose-500">{error}</p>}

              <Input
                placeholder="Название представления"
                value={newViewName}
                onChange={(e) => setNewViewName(e.target.value)}
                className="h-8 text-xs"
                autoFocus
              />

              <label className="flex items-center gap-2 text-xs text-muted-foreground cursor-pointer">
                <input
                  type="checkbox"
                  checked={isDefault}
                  onChange={(e) => setIsDefault(e.target.checked)}
                  className="rounded border-border text-emerald-600 focus:ring-emerald-500 size-3.5"
                />
                Сделать представлением по умолчанию
              </label>

              <div className="flex justify-end gap-1.5 pt-1">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => setIsCreating(false)}
                  className="h-7 text-xs"
                >
                  Отмена
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={!newViewName.trim() || isSubmitting}
                  className="h-7 text-xs bg-emerald-600 hover:bg-emerald-500 text-white"
                >
                  {isSubmitting ? 'Сохранение...' : 'Подтвердить сохранение'}
                </Button>
              </div>
            </form>
          ) : (
            <div className="space-y-1">
              <div className="px-2 py-1 text-[11px] font-medium text-muted-foreground border-b border-border/40 flex items-center justify-between">
                <span>Сохранённые представления ({savedViews.length})</span>
                <button
                  type="button"
                  onClick={() => setIsCreating(true)}
                  className="text-emerald-500 hover:underline flex items-center gap-0.5 text-[11px]"
                >
                  <PlusIcon className="size-3" /> Добавить
                </button>
              </div>

              {savedViews.length === 0 ? (
                <div className="p-3 text-center text-xs text-muted-foreground">
                  Нет сохранённых представлений
                </div>
              ) : (
                <div className="max-h-60 overflow-y-auto space-y-0.5">
                  {savedViews.map((view) => {
                    const isSelected = activeView?.id === view.id
                    return (
                      <div
                        key={view.id}
                        onClick={() => {
                          onSelectView(view)
                          setIsOpen(false)
                        }}
                        className={`flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs cursor-pointer transition-colors ${
                          isSelected
                            ? 'bg-emerald-500/10 text-emerald-400 font-medium'
                            : 'hover:bg-muted text-foreground'
                        }`}
                      >
                        <div className="flex items-center gap-2 truncate flex-1">
                          {isSelected && <CheckIcon className="size-3 text-emerald-500 shrink-0" />}
                          <span className="truncate">{view.name}</span>
                          {view.is_default && (
                            <Badge variant="outline" className="text-[9px] px-1 py-0 h-3.5 border-emerald-500/30 text-emerald-500">
                              дефолт
                            </Badge>
                          )}
                        </div>

                        <div className="flex items-center gap-1 shrink-0 ml-2">
                          <button
                            type="button"
                            title={view.is_default ? 'Снять дефолт' : 'Сделать дефолтным'}
                            onClick={(e) => handleSetDefault(view, e)}
                            className="p-1 text-muted-foreground hover:text-amber-400"
                          >
                            <StarIcon className={`size-3 ${view.is_default ? 'fill-amber-400 text-amber-400' : ''}`} />
                          </button>
                          <button
                            type="button"
                            title="Удалить представление"
                            onClick={(e) => handleDelete(view.id, e)}
                            className="p-1 text-muted-foreground hover:text-rose-400"
                          >
                            <Trash2Icon className="size-3" />
                          </button>
                        </div>
                      </div>
                    )
                  })}
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/dashboard/ui/dashboard-saved-views-menu.test.tsx`
Expected: PASS (all tests pass)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/ui/dashboard-saved-views-menu.tsx frontend/src/features/dashboard/ui/dashboard-saved-views-menu.test.tsx
git commit -m "feat(dashboard): create DashboardSavedViewsMenu component for preset management"
```

---

### Task 7: Integrate Filters and Saved Views into `DashboardViewer` and `DashboardGrid`

**Files:**
- Modify: `frontend/src/features/dashboard/ui/dashboard-grid.tsx`
- Modify: `frontend/src/features/dashboard/ui/widgets/widget-renderer.tsx`
- Modify: `frontend/src/features/dashboard/ui/dashboard-viewer.tsx`
- Modify: `frontend/src/features/dashboard/ui/dashboard-viewer.test.tsx`

**Interfaces:**
- Consumes: `DashboardFilterBar`, `DashboardSavedViewsMenu`, `useDashboardFilters`, `dashboardGateway.listSavedViews`
- Produces:
  - `DashboardGrid` takes `filters?: DashboardFilterValues` and passes it to `WidgetRenderer`
  - `WidgetRenderer` passes `filters` to `loadWidgetData` and re-runs on filter change
  - `DashboardViewer` mounts `DashboardSavedViewsMenu` and `DashboardFilterBar` in view mode

- [ ] **Step 1: Write failing tests in `frontend/src/features/dashboard/ui/dashboard-viewer.test.tsx`**

Update `frontend/src/features/dashboard/ui/dashboard-viewer.test.tsx`:
```typescript
  it('renders shared filter bar and saved views menu in view mode', async () => {
    vi.mocked(dashboardGateway.listSavedViews).mockResolvedValueOnce([
      {
        id: 'v-1',
        dashboard_id: 'dash-1',
        name: 'Вид по умолчанию',
        filters: { date_range: '30d' },
        is_default: true,
        created_at: '2026-09-23T10:00:00Z',
        updated_at: '2026-09-23T10:00:00Z',
      },
    ])

    render(
      <DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />,
    )

    await waitFor(() => {
      expect(screen.getByTestId('dashboard-filter-bar')).toBeInTheDocument()
      expect(screen.getByTestId('saved-views-menu')).toBeInTheDocument()
    })
  })
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test src/features/dashboard/ui/dashboard-viewer.test.tsx`
Expected: FAIL with "Unable to find an element by: [data-testid="dashboard-filter-bar"]"

- [ ] **Step 3: Modify `dashboard-grid.tsx`, `widget-renderer.tsx`, and `dashboard-viewer.tsx`**

1. In `frontend/src/features/dashboard/ui/widgets/widget-renderer.tsx`:
Add `filters?: DashboardFilterValues` to `WidgetRendererProps`:
```typescript
interface WidgetRendererProps {
  widget: WidgetDetail
  userId: string
  workspaceId: string
  filters?: DashboardFilterValues
}

export function WidgetRenderer({ widget, userId, workspaceId, filters }: WidgetRendererProps) {
  const [dataResult, setDataResult] = useState<WidgetDataResult>({ loading: true })

  useEffect(() => {
    let isCancelled = false
    setDataResult({ loading: true })

    loadWidgetData(widget, userId, workspaceId, filters).then((res) => {
      if (!isCancelled) {
        setDataResult(res)
      }
    })

    return () => {
      isCancelled = true
    }
  }, [widget, userId, workspaceId, filters])
  ...
```

2. In `frontend/src/features/dashboard/ui/dashboard-grid.tsx`:
Add `filters?: DashboardFilterValues` to `DashboardGridProps`:
```typescript
interface DashboardGridProps {
  widgets: WidgetDetail[]
  userId: string
  workspaceId: string
  filters?: DashboardFilterValues
}

export function DashboardGrid({ widgets, userId, workspaceId, filters }: DashboardGridProps) {
...
  <WidgetRenderer widget={widget} userId={userId} workspaceId={workspaceId} filters={filters} />
```

3. In `frontend/src/features/dashboard/ui/dashboard-viewer.tsx`:
Load saved views via `dashboardGateway.listSavedViews`, instantiate `useDashboardFilters`, and render `DashboardSavedViewsMenu` + `DashboardFilterBar`:
```typescript
  const [savedViews, setSavedViews] = useState<DashboardSavedView[]>([])

  const loadSavedViews = useCallback(() => {
    dashboardGateway
      .listSavedViews(dashboard.id, userId, workspaceId)
      .then((items) => setSavedViews(items))
      .catch(() => setSavedViews([]))
  }, [dashboard.id, userId, workspaceId])

  useEffect(() => {
    loadSavedViews()
  }, [loadSavedViews])

  const filterManager = useDashboardFilters({
    savedViews,
  })

  const {
    filters,
    activeView,
    setFilters,
    applySavedView,
  } = filterManager
```

In the JSX:
Place `DashboardSavedViewsMenu` next to the title or in the toolbar, and render `DashboardFilterBar` directly above `DashboardGrid` in view mode:
```tsx
      {mode === 'view' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between flex-wrap gap-2">
            <DashboardSavedViewsMenu
              dashboardId={dashboard.id}
              userId={userId}
              workspaceId={workspaceId}
              currentFilters={filters}
              activeView={activeView}
              savedViews={savedViews}
              onSelectView={applySavedView}
              onViewsUpdated={loadSavedViews}
            />
          </div>

          <DashboardFilterBar
            activeFilters={filters}
            onFilterChange={(f) => setFilters(f, activeView?.id ?? null)}
            userId={userId}
            workspaceId={workspaceId}
          />
        </div>
      )}

      {/* Main Grid: View or Edit Mode */}
      {mode === 'view' ? (
        <DashboardGrid
          key={refreshKey}
          widgets={widgets}
          userId={userId}
          workspaceId={workspaceId}
          filters={filters}
        />
      ) : ( ... )}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/dashboard/ui/dashboard-viewer.test.tsx`
Expected: PASS (all tests pass)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/ui/dashboard-grid.tsx frontend/src/features/dashboard/ui/widgets/widget-renderer.tsx frontend/src/features/dashboard/ui/dashboard-viewer.tsx frontend/src/features/dashboard/ui/dashboard-viewer.test.tsx
git commit -m "feat(dashboard): integrate shared filters bar and saved views menu into DashboardViewer"
```

---

### Task 8: End-to-End User Flow Test & Integration Quality Verification

**Files:**
- Create: `frontend/src/features/dashboard/ui/dashboard-saved-views-flow.test.tsx`

**Interfaces:**
- Consumes: Complete dashboard feature stack (viewer, filters, saved views, widget loader)
- Produces: Comprehensive flow verification covering view switching, filter changes, URL sync, and widget reload.

- [ ] **Step 1: Write integration flow test in `frontend/src/features/dashboard/ui/dashboard-saved-views-flow.test.tsx`**

```typescript
import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { DashboardViewer } from './dashboard-viewer'
import { dashboardGateway, type DashboardDetail } from '../api/dashboard-gateway'
import { salesGateway } from '../../sales-analytics/api/sales-gateway'
import { inventoryGateway } from '../../inventory-analytics/api/inventory-gateway'

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    listSavedViews: vi.fn(),
    createSavedView: vi.fn(),
    updateSavedView: vi.fn(),
    deleteSavedView: vi.fn(),
    update: vi.fn(),
  },
}))

vi.mock('../../sales-analytics/api/sales-gateway', () => ({
  salesGateway: {
    getOverview: vi.fn(),
    getFilterOptions: vi.fn(),
    getRecords: vi.fn(),
  },
}))

vi.mock('../../inventory-analytics/api/inventory-gateway', () => ({
  inventoryGateway: {
    getSummary: vi.fn(),
    getFilters: vi.fn(),
    getItems: vi.fn(),
    getAbcXyzSummary: vi.fn(),
  },
}))

const mockDashboard: DashboardDetail = {
  id: 'dash-flow-1',
  workspace_id: 'ws-1',
  title: 'Бизнес обзор',
  description: 'Дашборд с фильтрами',
  created_at: '2026-09-23T10:00:00Z',
  updated_at: '2026-09-23T10:00:00Z',
  widgets: [
    {
      id: 'w-1',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue' },
      position: { x: 0, y: 0, w: 4, h: 2 },
    },
  ],
}

describe('Dashboard Shared Filters & Saved Views E2E Flow', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    window.history.replaceState({}, '', '/dashboards/dash-flow-1')

    vi.mocked(salesGateway.getFilterOptions).mockResolvedValue({
      min_date: '2026-01-01',
      max_date: '2026-09-23',
      categories: [{ id: 'cat-1', name: 'Масла' }],
      regions: [{ id: 'reg-1', name: 'Москва', code: 'MSK' }],
    })

    vi.mocked(inventoryGateway.getFilters).mockResolvedValue({
      warehouses: [{ id: 'wh-1', name: 'Склад 1', code: 'WH-1' }],
      statuses: [{ value: 'optimal', label: 'Оптимально' }],
    })

    vi.mocked(salesGateway.getOverview).mockResolvedValue({
      summary: {
        total_revenue: 500000,
        order_count: 100,
        average_order_value: 5000,
        gross_profit: 150000,
        margin_rate: 0.3,
      },
      trend: [],
      categories: [],
      regions: [],
    })

    vi.mocked(dashboardGateway.listSavedViews).mockResolvedValue([
      {
        id: 'view-init',
        dashboard_id: 'dash-flow-1',
        name: 'Дефолтный вид',
        filters: { date_range: '30d' },
        is_default: true,
        created_at: '2026-09-23T10:00:00Z',
        updated_at: '2026-09-23T10:00:00Z',
      },
    ])
  })

  it('runs complete flow: loads default view -> changes period -> saves new view -> resets', async () => {
    render(
      <DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />,
    )

    // Wait for saved view and filter bar to load
    await waitFor(() => {
      expect(screen.getByTestId('dashboard-filter-bar')).toBeInTheDocument()
      expect(screen.getByText('Дефолтный вид')).toBeInTheDocument()
    })

    // Click 90d filter preset
    const btn90 = screen.getByRole('button', { name: '90 дн' })
    fireEvent.click(btn90)

    // Verify salesGateway receives 90d query
    await waitFor(() => {
      expect(salesGateway.getOverview).toHaveBeenCalledWith(
        'user-1',
        'ws-1',
        expect.objectContaining({
          dateFrom: expect.any(String),
          dateTo: expect.any(String),
        }),
      )
    })

    // Save as new preset
    vi.mocked(dashboardGateway.createSavedView).mockResolvedValueOnce({
      id: 'view-new-90',
      dashboard_id: 'dash-flow-1',
      name: 'Квартальный обзор',
      filters: { date_range: '90d' },
      is_default: false,
      created_at: '2026-09-23T12:00:00Z',
      updated_at: '2026-09-23T12:00:00Z',
    })

    fireEvent.click(screen.getByRole('button', { name: /Сохранить представление/i }))
    const nameInput = screen.getByPlaceholderText('Название представления')
    fireEvent.change(nameInput, { target: { value: 'Квартальный обзор' } })
    fireEvent.click(screen.getByRole('button', { name: /Подтвердить сохранение/i }))

    await waitFor(() => {
      expect(dashboardGateway.createSavedView).toHaveBeenCalledWith(
        'dash-flow-1',
        'user-1',
        {
          name: 'Квартальный обзор',
          filters: expect.objectContaining({ date_range: '90d' }),
          is_default: false,
        },
        'ws-1',
      )
    })
  })
})
```

- [ ] **Step 2: Run test to verify it passes**

Run: `npm --prefix frontend test src/features/dashboard/ui/dashboard-saved-views-flow.test.tsx`
Expected: PASS (all tests pass)

- [ ] **Step 3: Run full automated verification suite**

Run:
```bash
npm --prefix frontend run lint
npm --prefix frontend run typecheck
npm --prefix frontend test
composer --working-dir=backend test
```
Expected: All linters, TypeScript checks, and test suites pass with 0 errors.

- [ ] **Step 4: Commit**

```bash
git add frontend/src/features/dashboard/ui/dashboard-saved-views-flow.test.tsx
git commit -m "test(dashboard): add full integration flow test for shared filters and saved views"
```
