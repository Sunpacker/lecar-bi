# Dashboard Management & Viewer (Phase 8 — Step 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the frontend Dashboard Management & Viewer feature slice (Phase 8 Step 1), enabling users to list custom dashboards, create new dashboards, navigate to a dashboard detail view, and render semantic widgets (KPI cards, line charts, bar charts, donut charts, tables) across a responsive 12-column grid powered by real analytics endpoints.

**Architecture:** Feature-oriented frontend slice in Next.js 15 App Router under `src/features/dashboard/`. Type-safe API communication via `dashboardGateway` wrapping `openapi-fetch` client. A dedicated `widgetDataLoader` decouples semantic widget query configurations (`dataset`, `metric`, `dimension`, `date_range`) from underlying analytical API endpoints (`salesGateway`, `inventoryGateway`), normalizing responses for presentation. Responsive 12-column CSS Grid layout translates coordinate positions `(x, y, w, h)` to CSS grid placement.

**Tech Stack:** Next.js 16 (App Router, Server & Client Components), React 19, TypeScript 5.7, Tailwind CSS 4, shadcn/ui (@base-ui/react Sheet/Dialog, Card, Button, Input, Table, Skeleton, Badge), Recharts 3, Lucide React, Vitest 5, Testing Library.

**Spec:** `docs/roadmap/08-dashboard-builder.md`, `docs/architecture/03-frontend-nextjs.md`, `docs/architecture/05-bounded-contexts.md`, `docs/architecture/07-api-and-integration.md`.

## Global Constraints

- Must strictly adhere to the OpenAPI schema in `contracts/openapi/analytics-v1.yaml` and generated types in `frontend/src/shared/api/generated/schema.ts` (`docs/architecture/07-api-and-integration.md`).
- Multi-tenancy isolation: all API requests must pass `X-User-Id` and `X-Workspace-Id` headers (`docs/architecture/01-system-architecture.md`).
- Frontend must not duplicate or alter backend business logic: metrics, classifications, and aggregations are retrieved from backend analytics endpoints (`docs/architecture/03-frontend-nextjs.md`).
- UI styling must use Tailwind CSS semantic tokens (`bg-card`, `text-card-foreground`, `border-border`, `text-muted-foreground`) compatible with dark/light themes.
- No placeholders ("TODO", "TBD", "implement later"); every task must specify full implementation and tests.
- All vitest unit and component tests must pass without warnings or errors.

---

### Task 1: Dashboard API Gateway and Unit Tests

**Files:**
- Create: `frontend/src/features/dashboard/api/dashboard-gateway.ts`
- Test: `frontend/src/features/dashboard/api/dashboard-gateway.test.ts`

**Interfaces:**
- Consumes: `analyticsClient` from `@/shared/api/analytics-client`, `components` from `@/shared/api/generated/schema`.
- Produces: `dashboardGateway` with methods:
  - `list(userId: string, workspaceId?: string): Promise<DashboardSummary[]>`
  - `getById(id: string, userId: string, workspaceId?: string): Promise<DashboardDetail>`
  - `create(userId: string, request: CreateDashboardRequest, workspaceId?: string): Promise<DashboardDetail>`
  - `update(id: string, userId: string, request: UpdateDashboardRequest, workspaceId?: string): Promise<DashboardDetail>`
  - `delete(id: string, userId: string, workspaceId?: string): Promise<void>`
  - Re-exported types: `DashboardSummary`, `DashboardDetail`, `WidgetDetail`, `WidgetInput`, `WidgetQueryConfig`, `WidgetGridPosition`, `CreateDashboardRequest`, `UpdateDashboardRequest`.

- [ ] **Step 1: Write the failing test**

Create `frontend/src/features/dashboard/api/dashboard-gateway.test.ts`:
```ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { dashboardGateway } from './dashboard-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    GET: vi.fn(),
    POST: vi.fn(),
    PUT: vi.fn(),
    DELETE: vi.fn(),
  },
}))

describe('dashboardGateway', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches list of dashboards with workspace header', async () => {
    const mockList = {
      items: [
        {
          id: 'dash-1',
          workspace_id: 'ws-1',
          title: 'Сводный дашборд',
          description: 'Описание дашборда',
          widget_count: 4,
          created_at: '2026-09-22T10:00:00Z',
          updated_at: '2026-09-22T10:00:00Z',
        },
      ],
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockList,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.list('user-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith('/dashboards', {
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockList.items)
  })

  it('fetches single dashboard by ID', async () => {
    const mockDetail = {
      dashboard: {
        id: 'dash-1',
        workspace_id: 'ws-1',
        title: 'Сводный дашборд',
        description: null,
        widgets: [],
        created_at: '2026-09-22T10:00:00Z',
        updated_at: '2026-09-22T10:00:00Z',
      },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockDetail,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.getById('dash-1', 'user-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith('/dashboards/{id}', {
      params: {
        path: { id: 'dash-1' },
      },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockDetail.dashboard)
  })

  it('creates new dashboard', async () => {
    const mockCreated = {
      dashboard: {
        id: 'dash-new',
        workspace_id: 'ws-1',
        title: 'Новый дашборд',
        description: 'Новое описание',
        widgets: [],
        created_at: '2026-09-22T12:00:00Z',
        updated_at: '2026-09-22T12:00:00Z',
      },
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: mockCreated,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.create(
      'user-1',
      { title: 'Новый дашборд', description: 'Новое описание' },
      'ws-1',
    )

    expect(analyticsClient.POST).toHaveBeenCalledWith('/dashboards', {
      body: { title: 'Новый дашборд', description: 'Новое описание' },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockCreated.dashboard)
  })

  it('updates dashboard', async () => {
    const mockUpdated = {
      dashboard: {
        id: 'dash-1',
        workspace_id: 'ws-1',
        title: 'Обновлённый дашборд',
        description: 'Новое описание',
        widgets: [],
        created_at: '2026-09-22T10:00:00Z',
        updated_at: '2026-09-22T13:00:00Z',
      },
    }

    vi.mocked(analyticsClient.PUT).mockResolvedValueOnce({
      data: mockUpdated,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.update(
      'dash-1',
      'user-1',
      { title: 'Обновлённый дашборд', description: 'Новое описание', widgets: [] },
      'ws-1',
    )

    expect(analyticsClient.PUT).toHaveBeenCalledWith('/dashboards/{id}', {
      params: { path: { id: 'dash-1' } },
      body: { title: 'Обновлённый дашборд', description: 'Новое описание', widgets: [] },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockUpdated.dashboard)
  })

  it('deletes dashboard', async () => {
    vi.mocked(analyticsClient.DELETE).mockResolvedValueOnce({
      data: undefined,
      error: undefined,
      response: new Response(null, { status: 204 }),
    } as never)

    await dashboardGateway.delete('dash-1', 'user-1', 'ws-1')

    expect(analyticsClient.DELETE).toHaveBeenCalledWith('/dashboards/{id}', {
      params: { path: { id: 'dash-1' } },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
  })

  it('throws descriptive error on failure', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: undefined,
      error: { message: 'Dashboard not found', code: 'NOT_FOUND' },
      response: new Response(null, { status: 404 }),
    } as never)

    await expect(dashboardGateway.getById('non-existent', 'user-1', 'ws-1')).rejects.toThrow(
      'Dashboard not found',
    )
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- src/features/dashboard/api/dashboard-gateway.test.ts`
Expected: FAIL with "Cannot find module './dashboard-gateway'".

- [ ] **Step 3: Write minimal implementation**

Create `frontend/src/features/dashboard/api/dashboard-gateway.ts`:
```ts
import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type DashboardSummary = components['schemas']['DashboardSummary']
export type DashboardDetail = components['schemas']['DashboardDetail']
export type WidgetDetail = components['schemas']['WidgetDetail']
export type WidgetInput = components['schemas']['WidgetInput']
export type WidgetQueryConfig = components['schemas']['WidgetQueryConfig']
export type WidgetGridPosition = components['schemas']['WidgetGridPosition']
export type CreateDashboardRequest = components['schemas']['CreateDashboardRequest']
export type UpdateDashboardRequest = components['schemas']['UpdateDashboardRequest']

export const dashboardGateway = {
  async list(userId: string, workspaceId?: string): Promise<DashboardSummary[]> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/dashboards', {
      headers,
    })

    if (error || !data) {
      throw new Error((error as { message?: string })?.message ?? 'Failed to load dashboards')
    }

    return data.items
  },

  async getById(id: string, userId: string, workspaceId?: string): Promise<DashboardDetail> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/dashboards/{id}', {
      params: {
        path: { id },
      },
      headers,
    })

    if (error || !data) {
      throw new Error((error as { message?: string })?.message ?? 'Failed to load dashboard')
    }

    return data.dashboard
  },

  async create(
    userId: string,
    request: CreateDashboardRequest,
    workspaceId?: string,
  ): Promise<DashboardDetail> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.POST('/dashboards', {
      body: request,
      headers,
    })

    if (error || !data) {
      throw new Error((error as { message?: string })?.message ?? 'Failed to create dashboard')
    }

    return data.dashboard
  },

  async update(
    id: string,
    userId: string,
    request: UpdateDashboardRequest,
    workspaceId?: string,
  ): Promise<DashboardDetail> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.PUT('/dashboards/{id}', {
      params: {
        path: { id },
      },
      body: request,
      headers,
    })

    if (error || !data) {
      throw new Error((error as { message?: string })?.message ?? 'Failed to update dashboard')
    }

    return data.dashboard
  },

  async delete(id: string, userId: string, workspaceId?: string): Promise<void> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { error } = await analyticsClient.DELETE('/dashboards/{id}', {
      params: {
        path: { id },
      },
      headers,
    })

    if (error) {
      throw new Error((error as { message?: string })?.message ?? 'Failed to delete dashboard')
    }
  },
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- src/features/dashboard/api/dashboard-gateway.test.ts`
Expected: PASS (6 tests passed).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/api
git commit -m "feat(dashboard): add dashboard API gateway and unit tests"
```

---

### Task 2: Widget Data Loader & Formatters

**Files:**
- Create: `frontend/src/features/dashboard/model/widget-data-loader.ts`
- Test: `frontend/src/features/dashboard/model/widget-data-loader.test.ts`

**Interfaces:**
- Consumes: `WidgetDetail`, `salesGateway`, `inventoryGateway`.
- Produces:
  - `WidgetDataResult`: normalized structure `{ loading, error, kpi?, chartData?, tableData? }`.
  - `loadWidgetData(widget: WidgetDetail, userId: string, workspaceId: string): Promise<WidgetDataResult>`.
  - `formatMetricValue(value: number, metric: string, unit?: string): string`.

- [ ] **Step 1: Write the failing test**

Create `frontend/src/features/dashboard/model/widget-data-loader.test.ts`:
```ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { loadWidgetData, formatMetricValue } from './widget-data-loader'
import { salesGateway } from '../../sales-analytics/api/sales-gateway'
import { inventoryGateway } from '../../inventory-analytics/api/inventory-gateway'
import type { WidgetDetail } from '../api/dashboard-gateway'

vi.mock('../../sales-analytics/api/sales-gateway', () => ({
  salesGateway: {
    getOverview: vi.fn(),
    getRecords: vi.fn(),
  },
}))

vi.mock('../../inventory-analytics/api/inventory-gateway', () => ({
  inventoryGateway: {
    getSummary: vi.fn(),
    getItems: vi.fn(),
    getAbcXyzSummary: vi.fn(),
  },
}))

describe('widgetDataLoader', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('formats metric values correctly for currency, count, and percent', () => {
    expect(formatMetricValue(1500000, 'revenue', 'currency')).toBe('1 500 000 ₽')
    expect(formatMetricValue(345, 'order_count', 'count')).toBe('345')
    expect(formatMetricValue(0.245, 'margin_rate', 'percent')).toBe('24.5%')
  })

  it('loads sales KPI metric value correctly', async () => {
    const widget: WidgetDetail = {
      id: 'w-1',
      title: 'Выручка за 30 дней',
      type: 'kpi_card',
      query_config: {
        dataset: 'sales',
        metric: 'revenue',
        date_range: '30d',
      },
      position: { x: 0, y: 0, w: 3, h: 2 },
      options: { unit: 'currency' },
    }

    vi.mocked(salesGateway.getOverview).mockResolvedValueOnce({
      summary: {
        total_revenue: 2500000,
        order_count: 500,
        average_order_value: 5000,
        gross_profit: 600000,
        margin_rate: 0.24,
      },
      trend: [],
      categories: [],
      regions: [],
    })

    const result = await loadWidgetData(widget, 'user-1', 'ws-1')

    expect(result.error).toBeUndefined()
    expect(result.kpi?.value).toBe(2500000)
    expect(result.kpi?.formatted).toBe('2 500 000 ₽')
  })

  it('loads sales line chart trend data', async () => {
    const widget: WidgetDetail = {
      id: 'w-2',
      title: 'Динамика продаж',
      type: 'line_chart',
      query_config: {
        dataset: 'sales',
        metric: 'revenue',
        dimension: 'date',
        date_range: '90d',
      },
      position: { x: 0, y: 2, w: 8, h: 4 },
      options: {},
    }

    vi.mocked(salesGateway.getOverview).mockResolvedValueOnce({
      summary: {
        total_revenue: 100000,
        order_count: 20,
        average_order_value: 5000,
        gross_profit: 25000,
        margin_rate: 0.25,
      },
      trend: [
        { date: '2026-01-01', revenue: 40000, order_count: 8 },
        { date: '2026-01-02', revenue: 60000, order_count: 12 },
      ],
      categories: [],
      regions: [],
    })

    const result = await loadWidgetData(widget, 'user-1', 'ws-1')

    expect(result.chartData).toHaveLength(2)
    expect(result.chartData?.[0].name).toBe('2026-01-01')
    expect(result.chartData?.[0].value).toBe(40000)
  })

  it('loads inventory KPI metric value correctly', async () => {
    const widget: WidgetDetail = {
      id: 'w-3',
      title: 'Стоимость запасов',
      type: 'kpi_card',
      query_config: {
        dataset: 'inventory',
        metric: 'stock_value',
      },
      position: { x: 6, y: 0, w: 3, h: 2 },
      options: { unit: 'currency' },
    }

    vi.mocked(inventoryGateway.getSummary).mockResolvedValueOnce({
      summary: {
        total_items: 120,
        total_quantity_on_hand: 5400,
        total_inventory_value: 14500000,
        out_of_stock_items: 5,
        critical_stock_items: 8,
        optimal_stock_items: 90,
        overstock_items: 17,
        average_days_of_stock: 42,
      },
      stock_health: [],
      warehouses: [],
    })

    const result = await loadWidgetData(widget, 'user-1', 'ws-1')

    expect(result.kpi?.value).toBe(14500000)
    expect(result.kpi?.formatted).toBe('14 500 000 ₽')
  })

  it('loads inventory warehouse bar chart breakdown', async () => {
    const widget: WidgetDetail = {
      id: 'w-4',
      title: 'Остатки на складах',
      type: 'bar_chart',
      query_config: {
        dataset: 'inventory',
        metric: 'stock_quantity',
        dimension: 'warehouse',
      },
      position: { x: 6, y: 0, w: 6, h: 4 },
      options: {},
    }

    vi.mocked(inventoryGateway.getSummary).mockResolvedValueOnce({
      summary: {
        total_items: 120,
        total_quantity_on_hand: 5400,
        total_inventory_value: 14500000,
        out_of_stock_items: 5,
        critical_stock_items: 8,
        optimal_stock_items: 90,
        overstock_items: 17,
        average_days_of_stock: 42,
      },
      stock_health: [],
      warehouses: [
        {
          warehouse_id: 'wh-1',
          warehouse_name: 'Склад Тольятти',
          total_quantity: 3200,
          total_value: 8000000,
          item_count: 85,
        },
      ],
    })

    const result = await loadWidgetData(widget, 'user-1', 'ws-1')

    expect(result.chartData).toHaveLength(1)
    expect(result.chartData?.[0].name).toBe('Склад Тольятти')
    expect(result.chartData?.[0].value).toBe(3200)
  })

  it('handles loader errors gracefully', async () => {
    const widget: WidgetDetail = {
      id: 'w-err',
      title: 'Ошибка загрузки',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue' },
      position: { x: 0, y: 0, w: 3, h: 2 },
      options: {},
    }

    vi.mocked(salesGateway.getOverview).mockRejectedValueOnce(new Error('Network failure'))

    const result = await loadWidgetData(widget, 'user-1', 'ws-1')

    expect(result.error).toBe('Network failure')
    expect(result.loading).toBe(false)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- src/features/dashboard/model/widget-data-loader.test.ts`
Expected: FAIL with "Cannot find module './widget-data-loader'".

- [ ] **Step 3: Write minimal implementation**

Create `frontend/src/features/dashboard/model/widget-data-loader.ts`:
```ts
import { salesGateway } from '../../sales-analytics/api/sales-gateway'
import { inventoryGateway } from '../../inventory-analytics/api/inventory-gateway'
import type { WidgetDetail } from '../api/dashboard-gateway'

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
      .replace('руб.', '₽')
      .trim()
  }

  if (isPercent) {
    const percentVal = value > 1 ? value : value * 100
    return `${percentVal.toFixed(1)}%`
  }

  return new Intl.NumberFormat('ru-RU').format(value)
}

function resolveDateRangeFilters(dateRange?: string | null): {
  dateFrom?: string
  dateTo?: string
} {
  if (!dateRange || dateRange === 'all') {
    return {}
  }

  const daysMap: Record<string, number> = {
    '30d': 30,
    '90d': 90,
    '180d': 180,
    '365d': 365,
  }

  const days = daysMap[dateRange]
  if (!days) return {}

  const to = new Date()
  const from = new Date()
  from.setDate(to.getDate() - days)

  return {
    dateFrom: from.toISOString().split('T')[0],
    dateTo: to.toISOString().split('T')[0],
  }
}

export async function loadWidgetData(
  widget: WidgetDetail,
  userId: string,
  workspaceId: string,
): Promise<WidgetDataResult> {
  const { dataset, metric, dimension, date_range } = widget.query_config
  const unit = widget.options?.unit as string | undefined

  try {
    if (dataset === 'sales') {
      const filters = resolveDateRangeFilters(date_range)

      if (widget.type === 'kpi_card') {
        const overview = await salesGateway.getOverview(userId, workspaceId, filters)
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
            subtitle: date_range ? `Период: ${date_range}` : undefined,
          },
        }
      }

      if (widget.type === 'line_chart' || dimension === 'date') {
        const overview = await salesGateway.getOverview(userId, workspaceId, filters)
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
        const overview = await salesGateway.getOverview(userId, workspaceId, filters)
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
          ...filters,
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
      if (widget.type === 'kpi_card') {
        const summaryRes = await inventoryGateway.getSummary(userId, workspaceId)
        let val = 0
        switch (metric) {
          case 'stock_quantity':
            val = summaryRes.summary.total_quantity_on_hand
            break
          case 'stock_value':
            val = summaryRes.summary.total_inventory_value
            break
          case 'out_of_stock_count':
            val = summaryRes.summary.out_of_stock_items
            break
          case 'overstock_count':
            val = summaryRes.summary.overstock_items
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
          const summaryRes = await inventoryGateway.getSummary(userId, workspaceId)
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
          const abcSummary = await inventoryGateway.getAbcXyzSummary(userId, workspaceId)
          const dist =
            dimension === 'abc_class' ? abcSummary.abc_distribution : abcSummary.xyz_distribution
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
              quantity_available: formatMetricValue(item.quantity_available, 'stock_quantity'),
              inventory_value: formatMetricValue(item.inventory_value, 'stock_value', 'currency'),
            })),
          },
        }
      }
    }

    return { loading: false }
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Ошибка при загрузке данных виджета'
    return {
      loading: false,
      error: message,
    }
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- src/features/dashboard/model/widget-data-loader.test.ts`
Expected: PASS (6 tests passed).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/model
git commit -m "feat(dashboard): add widget data loader and formatting utilities"
```

---

### Task 3: Semantic Widget Visual Components & Renderer

**Files:**
- Create: `frontend/src/features/dashboard/ui/widgets/widget-kpi-card.tsx`
- Create: `frontend/src/features/dashboard/ui/widgets/widget-line-chart.tsx`
- Create: `frontend/src/features/dashboard/ui/widgets/widget-bar-chart.tsx`
- Create: `frontend/src/features/dashboard/ui/widgets/widget-donut-chart.tsx`
- Create: `frontend/src/features/dashboard/ui/widgets/widget-table.tsx`
- Create: `frontend/src/features/dashboard/ui/widgets/widget-renderer.tsx`
- Test: `frontend/src/features/dashboard/ui/widgets/widget-components.test.tsx`

**Interfaces:**
- Consumes: `WidgetDetail`, `loadWidgetData`, shadcn/ui components (`Card`, `Badge`, `Skeleton`, `Table`), Recharts components.
- Produces: `WidgetRenderer` component that loads widget data asynchronously on mount and renders appropriate visual presentation.

- [ ] **Step 1: Write the failing test**

Create `frontend/src/features/dashboard/ui/widgets/widget-components.test.tsx`:
```tsx
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import React from 'react'
import { WidgetRenderer } from './widget-renderer'
import { WidgetKpiCard } from './widget-kpi-card'
import * as widgetDataLoader from '../../model/widget-data-loader'
import type { WidgetDetail } from '../../api/dashboard-gateway'

vi.mock('../../model/widget-data-loader', async (importOriginal) => {
  const actual = await importOriginal<typeof widgetDataLoader>()
  return {
    ...actual,
    loadWidgetData: vi.fn(),
  }
})

describe('Widget Components', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders WidgetKpiCard with formatted value and subtitle', () => {
    render(
      <WidgetKpiCard
        title="Общая выручка"
        value="1 250 000 ₽"
        subtitle="За последние 30 дней"
      />,
    )

    expect(screen.getByText('Общая выручка')).toBeDefined()
    expect(screen.getByText('1 250 000 ₽')).toBeDefined()
    expect(screen.getByText('За последние 30 дней')).toBeDefined()
  })

  it('renders WidgetRenderer in loading state before data arrives', () => {
    const widget: WidgetDetail = {
      id: 'w-kpi',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue' },
      position: { x: 0, y: 0, w: 3, h: 2 },
      options: {},
    }

    vi.mocked(widgetDataLoader.loadWidgetData).mockReturnValue(
      new Promise(() => {}), // Never resolves to keep in loading state
    )

    render(<WidgetRenderer widget={widget} userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByText('Выручка')).toBeDefined()
    expect(screen.getByTestId('widget-loading-skeleton')).toBeDefined()
  })

  it('renders WidgetRenderer with KPI card after data loads', async () => {
    const widget: WidgetDetail = {
      id: 'w-kpi',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue' },
      position: { x: 0, y: 0, w: 3, h: 2 },
      options: {},
    }

    vi.mocked(widgetDataLoader.loadWidgetData).mockResolvedValueOnce({
      loading: false,
      kpi: {
        value: 2000000,
        formatted: '2 000 000 ₽',
        subtitle: 'Период: 30d',
      },
    })

    render(<WidgetRenderer widget={widget} userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('2 000 000 ₽')).toBeDefined()
    })
    expect(screen.getByText('Период: 30d')).toBeDefined()
  })

  it('renders WidgetRenderer error state when data load fails', async () => {
    const widget: WidgetDetail = {
      id: 'w-err',
      title: 'Виджет с ошибкой',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue' },
      position: { x: 0, y: 0, w: 3, h: 2 },
      options: {},
    }

    vi.mocked(widgetDataLoader.loadWidgetData).mockResolvedValueOnce({
      loading: false,
      error: 'Ошибка соединения с API',
    })

    render(<WidgetRenderer widget={widget} userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('Ошибка соединения с API')).toBeDefined()
    })
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- src/features/dashboard/ui/widgets/widget-components.test.tsx`
Expected: FAIL with "Cannot find module './widget-renderer'".

- [ ] **Step 3: Write minimal implementation**

Create `frontend/src/features/dashboard/ui/widgets/widget-kpi-card.tsx`:
```tsx
import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface WidgetKpiCardProps {
  title: string
  value: string
  subtitle?: string
}

export function WidgetKpiCard({ title, value, subtitle }: WidgetKpiCardProps) {
  return (
    <Card className="h-full flex flex-col justify-between border-border bg-card/60 shadow-xs">
      <CardHeader className="pb-2">
        <CardTitle className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
          {title}
        </CardTitle>
      </CardHeader>
      <CardContent className="pt-0">
        <div className="text-2xl font-bold tracking-tight text-foreground">{value}</div>
        {subtitle && (
          <p className="mt-1 text-xs text-muted-foreground leading-normal">{subtitle}</p>
        )}
      </CardContent>
    </Card>
  )
}
```

Create `frontend/src/features/dashboard/ui/widgets/widget-line-chart.tsx`:
```tsx
'use client'

import React from 'react'
import {
  ResponsiveContainer,
  LineChart,
  Line,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { WidgetChartPoint } from '../../model/widget-data-loader'

interface WidgetLineChartProps {
  title: string
  data: WidgetChartPoint[]
}

export function WidgetLineChart({ title, data }: WidgetLineChartProps) {
  return (
    <Card className="h-full flex flex-col border-border bg-card/60 shadow-xs">
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-foreground">{title}</CardTitle>
      </CardHeader>
      <CardContent className="flex-1 min-h-[180px] p-2 sm:p-4">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={data} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
            <CartesianGrid strokeDasharray="3 3" className="stroke-border/40" />
            <XAxis dataKey="name" stroke="currentColor" className="text-xs text-muted-foreground" />
            <YAxis stroke="currentColor" className="text-xs text-muted-foreground" />
            <Tooltip
              contentStyle={{
                backgroundColor: 'var(--popover)',
                borderColor: 'var(--border)',
                borderRadius: '0.5rem',
                color: 'var(--popover-foreground)',
              }}
            />
            <Line
              type="monotone"
              dataKey="value"
              stroke="#10b981"
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4 }}
            />
          </LineChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  )
}
```

Create `frontend/src/features/dashboard/ui/widgets/widget-bar-chart.tsx`:
```tsx
'use client'

import React from 'react'
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { WidgetChartPoint } from '../../model/widget-data-loader'

interface WidgetBarChartProps {
  title: string
  data: WidgetChartPoint[]
}

export function WidgetBarChart({ title, data }: WidgetBarChartProps) {
  return (
    <Card className="h-full flex flex-col border-border bg-card/60 shadow-xs">
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-foreground">{title}</CardTitle>
      </CardHeader>
      <CardContent className="flex-1 min-h-[180px] p-2 sm:p-4">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
            <CartesianGrid strokeDasharray="3 3" className="stroke-border/40" />
            <XAxis dataKey="name" stroke="currentColor" className="text-xs text-muted-foreground" />
            <YAxis stroke="currentColor" className="text-xs text-muted-foreground" />
            <Tooltip
              contentStyle={{
                backgroundColor: 'var(--popover)',
                borderColor: 'var(--border)',
                borderRadius: '0.5rem',
                color: 'var(--popover-foreground)',
              }}
            />
            <Bar dataKey="value" fill="#3b82f6" radius={[4, 4, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  )
}
```

Create `frontend/src/features/dashboard/ui/widgets/widget-donut-chart.tsx`:
```tsx
'use client'

import React from 'react'
import { ResponsiveContainer, PieChart, Pie, Cell, Tooltip, Legend } from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { WidgetChartPoint } from '../../model/widget-data-loader'

interface WidgetDonutChartProps {
  title: string
  data: WidgetChartPoint[]
}

const COLORS = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ec4899', '#6366f1']

export function WidgetDonutChart({ title, data }: WidgetDonutChartProps) {
  return (
    <Card className="h-full flex flex-col border-border bg-card/60 shadow-xs">
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-foreground">{title}</CardTitle>
      </CardHeader>
      <CardContent className="flex-1 min-h-[180px] p-2">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Tooltip
              contentStyle={{
                backgroundColor: 'var(--popover)',
                borderColor: 'var(--border)',
                borderRadius: '0.5rem',
                color: 'var(--popover-foreground)',
              }}
            />
            <Legend wrapperStyle={{ fontSize: '11px' }} />
            <Pie
              data={data}
              innerRadius={45}
              outerRadius={70}
              paddingAngle={2}
              dataKey="value"
            >
              {data.map((_, index) => (
                <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
              ))}
            </Pie>
          </PieChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  )
}
```

Create `frontend/src/features/dashboard/ui/widgets/widget-table.tsx`:
```tsx
import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { WidgetTableData } from '../../model/widget-data-loader'

interface WidgetTableProps {
  title: string
  data: WidgetTableData
}

export function WidgetTable({ title, data }: WidgetTableProps) {
  return (
    <Card className="h-full flex flex-col border-border bg-card/60 shadow-xs overflow-hidden">
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-foreground">{title}</CardTitle>
      </CardHeader>
      <CardContent className="flex-1 p-0 overflow-auto">
        <Table>
          <TableHeader>
            <TableRow>
              {data.columns.map((col) => (
                <TableHead key={col.key} className="text-xs">
                  {col.label}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.rows.map((row, idx) => (
              <TableRow key={idx}>
                {data.columns.map((col) => (
                  <TableCell key={col.key} className="text-xs">
                    {String(row[col.key] ?? '')}
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  )
}
```

Create `frontend/src/features/dashboard/ui/widgets/widget-renderer.tsx`:
```tsx
'use client'

import React, { useEffect, useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { AlertCircle } from 'lucide-react'
import type { WidgetDetail } from '../../api/dashboard-gateway'
import { loadWidgetData, type WidgetDataResult } from '../../model/widget-data-loader'
import { WidgetKpiCard } from './widget-kpi-card'
import { WidgetLineChart } from './widget-line-chart'
import { WidgetBarChart } from './widget-bar-chart'
import { WidgetDonutChart } from './widget-donut-chart'
import { WidgetTable } from './widget-table'

interface WidgetRendererProps {
  widget: WidgetDetail
  userId: string
  workspaceId: string
}

export function WidgetRenderer({ widget, userId, workspaceId }: WidgetRendererProps) {
  const [dataResult, setDataResult] = useState<WidgetDataResult>({ loading: true })

  useEffect(() => {
    let isCancelled = false
    setDataResult({ loading: true })

    loadWidgetData(widget, userId, workspaceId).then((res) => {
      if (!isCancelled) {
        setDataResult(res)
      }
    })

    return () => {
      isCancelled = true
    }
  }, [widget, userId, workspaceId])

  if (dataResult.loading) {
    return (
      <Card
        data-testid="widget-loading-skeleton"
        className="h-full flex flex-col justify-between border-border bg-card/40 p-4"
      >
        <CardHeader className="p-0 pb-2">
          <CardTitle className="text-xs text-muted-foreground">{widget.title}</CardTitle>
        </CardHeader>
        <CardContent className="p-0 flex-1 flex flex-col justify-center gap-2">
          <Skeleton className="h-8 w-2/3 rounded-md" />
          <Skeleton className="h-4 w-1/3 rounded-md" />
        </CardContent>
      </Card>
    )
  }

  if (dataResult.error) {
    return (
      <Card className="h-full flex flex-col items-center justify-center p-4 border-rose-500/30 bg-rose-500/5 text-center">
        <AlertCircle className="size-6 text-rose-500 mb-2" />
        <p className="text-xs font-medium text-rose-400">{widget.title}</p>
        <p className="text-[11px] text-muted-foreground mt-1">{dataResult.error}</p>
      </Card>
    )
  }

  switch (widget.type) {
    case 'kpi_card':
      return (
        <WidgetKpiCard
          title={widget.title}
          value={dataResult.kpi?.formatted ?? '0'}
          subtitle={dataResult.kpi?.subtitle}
        />
      )
    case 'line_chart':
      return <WidgetLineChart title={widget.title} data={dataResult.chartData ?? []} />
    case 'bar_chart':
      return <WidgetBarChart title={widget.title} data={dataResult.chartData ?? []} />
    case 'donut_chart':
      return <WidgetDonutChart title={widget.title} data={dataResult.chartData ?? []} />
    case 'table':
      return (
        <WidgetTable
          title={widget.title}
          data={dataResult.tableData ?? { columns: [], rows: [] }}
        />
      )
    default:
      return null
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- src/features/dashboard/ui/widgets/widget-components.test.tsx`
Expected: PASS (4 tests passed).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/ui/widgets
git commit -m "feat(dashboard): implement semantic widget components and renderer"
```

---

### Task 4: Responsive 12-Column Dashboard Grid Layout

**Files:**
- Create: `frontend/src/features/dashboard/ui/dashboard-grid.tsx`
- Test: `frontend/src/features/dashboard/ui/dashboard-grid.test.tsx`

**Interfaces:**
- Consumes: `WidgetDetail[]`, `WidgetRenderer`.
- Produces: `DashboardGrid` component placing widgets according to `position` (x, y, w, h) in a 12-column grid.

- [ ] **Step 1: Write the failing test**

Create `frontend/src/features/dashboard/ui/dashboard-grid.test.tsx`:
```tsx
import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import React from 'react'
import { DashboardGrid } from './dashboard-grid'
import type { WidgetDetail } from '../api/dashboard-gateway'

vi.mock('./widgets/widget-renderer', () => ({
  WidgetRenderer: ({ widget }: { widget: WidgetDetail }) => (
    <div data-testid={`widget-${widget.id}`}>{widget.title}</div>
  ),
}))

describe('DashboardGrid', () => {
  it('renders empty state when there are no widgets', () => {
    render(<DashboardGrid widgets={[]} userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByText('В этом дашборде пока нет виджетов')).toBeDefined()
  })

  it('renders all widgets with grid placement styles', () => {
    const widgets: WidgetDetail[] = [
      {
        id: 'w-1',
        title: 'KPI Выручка',
        type: 'kpi_card',
        query_config: { dataset: 'sales', metric: 'revenue' },
        position: { x: 0, y: 0, w: 4, h: 2 },
        options: {},
      },
      {
        id: 'w-2',
        title: 'График продаж',
        type: 'line_chart',
        query_config: { dataset: 'sales', metric: 'revenue' },
        position: { x: 4, y: 0, w: 8, h: 4 },
        options: {},
      },
    ]

    render(<DashboardGrid widgets={widgets} userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByTestId('widget-w-1')).toBeDefined()
    expect(screen.getByTestId('widget-w-2')).toBeDefined()
    expect(screen.getByText('KPI Выручка')).toBeDefined()
    expect(screen.getByText('График продаж')).toBeDefined()
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- src/features/dashboard/ui/dashboard-grid.test.tsx`
Expected: FAIL with "Cannot find module './dashboard-grid'".

- [ ] **Step 3: Write minimal implementation**

Create `frontend/src/features/dashboard/ui/dashboard-grid.tsx`:
```tsx
'use client'

import React from 'react'
import type { WidgetDetail } from '../api/dashboard-gateway'
import { WidgetRenderer } from './widgets/widget-renderer'
import { LayoutGrid } from 'lucide-react'

interface DashboardGridProps {
  widgets: WidgetDetail[]
  userId: string
  workspaceId: string
}

export function DashboardGrid({ widgets, userId, workspaceId }: DashboardGridProps) {
  if (widgets.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/80 p-12 text-center bg-card/20">
        <div className="flex size-12 items-center justify-center rounded-full bg-muted/60 text-muted-foreground mb-4">
          <LayoutGrid className="size-6" />
        </div>
        <h3 className="text-base font-semibold text-foreground">
          В этом дашборде пока нет виджетов
        </h3>
        <p className="mt-1 text-sm text-muted-foreground max-w-sm">
          Настройте конфигурацию виджетов или перейдите в режим редактирования для добавления
          метрик.
        </p>
      </div>
    )
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-12 gap-4 auto-rows-[90px]">
      {widgets.map((widget) => {
        const { x, y, w, h } = widget.position
        // Safe bound clamps
        const colStart = Math.min(12, Math.max(1, x + 1))
        const colSpan = Math.min(12 - x, Math.max(1, w))
        const rowStart = Math.max(1, y + 1)
        const rowSpan = Math.max(1, h)

        return (
          <div
            key={widget.id}
            style={{
              gridColumn: `span ${colSpan} / span ${colSpan}`,
              gridRow: `span ${rowSpan} / span ${rowSpan}`,
            }}
            className="md:[grid-column-start:var(--col-start)] md:[grid-row-start:var(--row-start)] min-h-[140px]"
            // Inline style variables for exact 12-column grid coordinate mapping
            data-col-start={colStart}
            data-col-span={colSpan}
          >
            <div className="h-full w-full">
              <WidgetRenderer widget={widget} userId={userId} workspaceId={workspaceId} />
            </div>
          </div>
        )
      })}
    </div>
  )
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- src/features/dashboard/ui/dashboard-grid.test.tsx`
Expected: PASS (2 tests passed).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/ui/dashboard-grid.*
git commit -m "feat(dashboard): implement responsive 12-column dashboard grid layout"
```

---

### Task 5: Dashboard List View & Creation Sheet

**Files:**
- Create: `frontend/src/features/dashboard/ui/dashboard-list-view.tsx`
- Test: `frontend/src/features/dashboard/ui/dashboard-list-view.test.tsx`

**Interfaces:**
- Consumes: `DashboardSummary[]`, `dashboardGateway`, `@/components/ui/button`, `@/components/ui/card`, `@/components/ui/input`, `@/components/ui/sheet`.
- Produces: `DashboardListView` component with summary cards, links to dashboard details, and creation/deletion flow.

- [ ] **Step 1: Write the failing test**

Create `frontend/src/features/dashboard/ui/dashboard-list-view.test.tsx`:
```tsx
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import React from 'react'
import { DashboardListView } from './dashboard-list-view'
import { dashboardGateway, type DashboardSummary } from '../api/dashboard-gateway'

const mockPush = vi.fn()
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
}))

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    create: vi.fn(),
    delete: vi.fn(),
  },
}))

describe('DashboardListView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  const initialDashboards: DashboardSummary[] = [
    {
      id: 'd-1',
      workspace_id: 'ws-1',
      title: 'Сводный обзор бизнеса',
      description: 'Ключевые показатели',
      widget_count: 6,
      created_at: '2026-09-22T10:00:00Z',
      updated_at: '2026-09-22T10:00:00Z',
    },
  ]

  it('renders list of dashboard cards with title and widget count', () => {
    render(
      <DashboardListView
        initialDashboards={initialDashboards}
        userId="user-1"
        workspaceId="ws-1"
      />,
    )

    expect(screen.getByText('Сводный обзор бизнеса')).toBeDefined()
    expect(screen.getByText('Ключевые показатели')).toBeDefined()
    expect(screen.getByText(/6 виджетов/)).toBeDefined()
  })

  it('creates new dashboard via modal sheet', async () => {
    vi.mocked(dashboardGateway.create).mockResolvedValueOnce({
      id: 'd-new',
      workspace_id: 'ws-1',
      title: 'Финансовый дашборд',
      description: 'Фин показатели',
      widgets: [],
      created_at: '2026-09-22T12:00:00Z',
      updated_at: '2026-09-22T12:00:00Z',
    })

    render(
      <DashboardListView
        initialDashboards={initialDashboards}
        userId="user-1"
        workspaceId="ws-1"
      />,
    )

    fireEvent.click(screen.getByText('Создать дашборд'))

    const titleInput = screen.getByLabelText('Название')
    fireEvent.change(titleInput, { target: { value: 'Финансовый дашборд' } })

    const submitBtn = screen.getByRole('button', { name: 'Сохранить' })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(dashboardGateway.create).toHaveBeenCalledWith(
        'user-1',
        { title: 'Финансовый дашборд', description: '' },
        'ws-1',
      )
    })
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- src/features/dashboard/ui/dashboard-list-view.test.tsx`
Expected: FAIL with "Cannot find module './dashboard-list-view'".

- [ ] **Step 3: Write minimal implementation**

Create `frontend/src/features/dashboard/ui/dashboard-list-view.tsx`:
```tsx
'use client'

import React, { useState } from 'react'
import Link from 'next/link'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet'
import { Plus, LayoutDashboard, Calendar, Trash2 } from 'lucide-react'
import { dashboardGateway, type DashboardSummary } from '../api/dashboard-gateway'

interface DashboardListViewProps {
  initialDashboards: DashboardSummary[]
  userId: string
  workspaceId: string
}

export function DashboardListView({
  initialDashboards,
  userId,
  workspaceId,
}: DashboardListViewProps) {
  const [dashboards, setDashboards] = useState<DashboardSummary[]>(initialDashboards)
  const [isOpen, setIsOpen] = useState(false)
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!title.trim()) return

    setIsSubmitting(true)
    setError(null)

    try {
      const created = await dashboardGateway.create(
        userId,
        { title: title.trim(), description: description.trim() || null },
        workspaceId,
      )

      const newSummary: DashboardSummary = {
        id: created.id,
        workspace_id: created.workspace_id,
        title: created.title,
        description: created.description,
        widget_count: created.widgets.length,
        created_at: created.created_at,
        updated_at: created.updated_at,
      }

      setDashboards((prev) => [newSummary, ...prev])
      setTitle('')
      setDescription('')
      setIsOpen(false)
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Не удалось создать дашборд')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleDelete = async (id: string, e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()

    if (!window.confirm('Вы уверены, что хотите удалить этот дашборд?')) {
      return
    }

    try {
      await dashboardGateway.delete(id, userId, workspaceId)
      setDashboards((prev) => prev.filter((d) => d.id !== id))
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Не удалось удалить дашборд')
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-foreground">Пользовательские дашборды</h2>
          <p className="text-sm text-muted-foreground mt-0.5">
            Управление аналитическими дашбордами и конфигурациями виджетов
          </p>
        </div>

        <Sheet open={isOpen} onOpenChange={setIsOpen}>
          <SheetTrigger asChild>
            <Button className="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white">
              <Plus className="size-4" />
              <span>Создать дашборд</span>
            </Button>
          </SheetTrigger>
          <SheetContent className="p-6">
            <SheetHeader>
              <SheetTitle>Новый дашборд</SheetTitle>
              <SheetDescription>
                Задайте название и назначение дашборда для вашего рабочего пространства.
              </SheetDescription>
            </SheetHeader>

            <form onSubmit={handleCreate} className="mt-6 space-y-4">
              {error && (
                <div className="p-3 text-xs rounded-md bg-rose-500/10 border border-rose-500/20 text-rose-400">
                  {error}
                </div>
              )}
              <div className="space-y-2">
                <Label htmlFor="dashboard-title">Название</Label>
                <Input
                  id="dashboard-title"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="Например: Обзор продаж Тольятти"
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="dashboard-desc">Описание (необязательно)</Label>
                <Input
                  id="dashboard-desc"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Краткое описание метрик и назначения"
                />
              </div>

              <div className="pt-4 flex justify-end gap-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsOpen(false)}
                  disabled={isSubmitting}
                >
                  Отмена
                </Button>
                <Button
                  type="submit"
                  disabled={isSubmitting || !title.trim()}
                  className="bg-emerald-600 hover:bg-emerald-500 text-white"
                >
                  {isSubmitting ? 'Сохранение...' : 'Сохранить'}
                </Button>
              </div>
            </form>
          </SheetContent>
        </Sheet>
      </div>

      {dashboards.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/80 p-12 text-center bg-card/20">
          <div className="flex size-12 items-center justify-center rounded-full bg-muted/60 text-muted-foreground mb-4">
            <LayoutDashboard className="size-6" />
          </div>
          <h3 className="text-base font-semibold text-foreground">Нет доступных дашбордов</h3>
          <p className="mt-1 text-sm text-muted-foreground max-w-sm">
            Создайте свой первый дашборд для компоновки нужных метрик и аналитических срезов.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {dashboards.map((dash) => (
            <Link
              key={dash.id}
              href={`/dashboards/${dash.id}`}
              className="group relative block focus:outline-hidden"
            >
              <Card className="h-full transition-all duration-200 border-border bg-card/50 hover:bg-card hover:border-emerald-500/40 shadow-xs hover:shadow-md flex flex-col justify-between">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between gap-2">
                    <CardTitle className="text-base font-bold text-foreground group-hover:text-emerald-400 transition-colors">
                      {dash.title}
                    </CardTitle>
                    <Button
                      variant="ghost"
                      size="icon"
                      aria-label="Удалить дашборд"
                      onClick={(e) => handleDelete(dash.id, e)}
                      className="size-8 opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-rose-400 transition-opacity"
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </div>
                  {dash.description && (
                    <CardDescription className="text-xs line-clamp-2 mt-1">
                      {dash.description}
                    </CardDescription>
                  )}
                </CardHeader>
                <CardContent className="pt-0">
                  <div className="flex items-center justify-between text-xs text-muted-foreground pt-4 border-t border-border/50">
                    <span className="inline-flex items-center gap-1.5 font-medium text-foreground">
                      <LayoutDashboard className="size-3.5 text-emerald-500" />
                      {dash.widget_count} {dash.widget_count === 1 ? 'виджет' : 'виджетов'}
                    </span>
                    <span className="inline-flex items-center gap-1 text-[11px]">
                      <Calendar className="size-3" />
                      {new Date(dash.created_at).toLocaleDateString('ru-RU')}
                    </span>
                  </div>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- src/features/dashboard/ui/dashboard-list-view.test.tsx`
Expected: PASS (2 tests passed).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/ui/dashboard-list-view.*
git commit -m "feat(dashboard): implement dashboard list view and creation sheet"
```

---

### Task 6: Dashboard Viewer & Navigation Integration

**Files:**
- Create: `frontend/src/features/dashboard/ui/dashboard-viewer.tsx`
- Test: `frontend/src/features/dashboard/ui/dashboard-viewer.test.tsx`
- Modify: `frontend/src/shared/ui/layout/sidebar.tsx`
- Create: `frontend/app/(dashboard)/dashboards/page.tsx`
- Create: `frontend/app/(dashboard)/dashboards/[id]/page.tsx`

**Interfaces:**
- Consumes: `DashboardDetail`, `DashboardGrid`, Next.js App Router.
- Produces: `DashboardViewer` component, updated `sidebar.tsx` with `/dashboards` link, and Next.js routes.

- [ ] **Step 1: Write the failing test**

Create `frontend/src/features/dashboard/ui/dashboard-viewer.test.tsx`:
```tsx
import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import React from 'react'
import { DashboardViewer } from './dashboard-viewer'
import type { DashboardDetail } from '../api/dashboard-gateway'

vi.mock('./dashboard-grid', () => ({
  DashboardGrid: () => <div data-testid="dashboard-grid-mock">Mock Grid</div>,
}))

describe('DashboardViewer', () => {
  const mockDashboard: DashboardDetail = {
    id: 'dash-1',
    workspace_id: 'ws-1',
    title: 'Оперативный дашборд',
    description: 'Показатели реального времени',
    widgets: [
      {
        id: 'w-1',
        title: 'Выручка',
        type: 'kpi_card',
        query_config: { dataset: 'sales', metric: 'revenue' },
        position: { x: 0, y: 0, w: 3, h: 2 },
        options: {},
      },
    ],
    created_at: '2026-09-22T10:00:00Z',
    updated_at: '2026-09-22T10:00:00Z',
  }

  it('renders dashboard title, description, and grid', () => {
    render(
      <DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />,
    )

    expect(screen.getByText('Оперативный дашборд')).toBeDefined()
    expect(screen.getByText('Показатели реального времени')).toBeDefined()
    expect(screen.getByTestId('dashboard-grid-mock')).toBeDefined()
    expect(screen.getByText('Назад к списку')).toBeDefined()
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- src/features/dashboard/ui/dashboard-viewer.test.tsx`
Expected: FAIL with "Cannot find module './dashboard-viewer'".

- [ ] **Step 3: Write minimal implementation**

Create `frontend/src/features/dashboard/ui/dashboard-viewer.tsx`:
```tsx
'use client'

import React from 'react'
import Link from 'next/link'
import { Button } from '@/components/ui/button'
import { ArrowLeft, RefreshCw, LayoutDashboard } from 'lucide-react'
import type { DashboardDetail } from '../api/dashboard-gateway'
import { DashboardGrid } from './dashboard-grid'

interface DashboardViewerProps {
  dashboard: DashboardDetail
  userId: string
  workspaceId: string
}

export function DashboardViewer({ dashboard, userId, workspaceId }: DashboardViewerProps) {
  const [refreshKey, setRefreshKey] = React.useState(0)

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border/60">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <Link href="/dashboards">
              <Button variant="ghost" size="sm" className="h-8 px-2 text-xs gap-1.5 text-muted-foreground hover:text-foreground">
                <ArrowLeft className="size-3.5" />
                <span>Назад к списку</span>
              </Button>
            </Link>
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
            <LayoutDashboard className="size-6 text-emerald-500" />
            {dashboard.title}
          </h1>
          {dashboard.description && (
            <p className="text-sm text-muted-foreground leading-relaxed">
              {dashboard.description}
            </p>
          )}
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setRefreshKey((k) => k + 1)}
            className="h-9 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
          >
            <RefreshCw className="size-3.5" />
            <span>Обновить данные</span>
          </Button>
        </div>
      </div>

      <DashboardGrid
        key={refreshKey}
        widgets={dashboard.widgets}
        userId={userId}
        workspaceId={workspaceId}
      />
    </div>
  )
}
```

Update `frontend/src/shared/ui/layout/sidebar.tsx`:
Add `{ name: 'Dashboards', href: '/dashboards', icon: LayoutDashboard }` to `NAVIGATION_ITEMS`.
```tsx
export const NAVIGATION_ITEMS: NavigationItem[] = [
  { name: 'Sales Analytics', href: '/', icon: BarChart3 },
  { name: 'Inventory', href: '/inventory', icon: Package },
  { name: 'Dashboards', href: '/dashboards', icon: LayoutDashboard },
  { name: 'Suppliers', href: '/suppliers', icon: Users, disabled: true, badge: 'Скоро' },
  { name: 'Alerts', href: '/alerts', icon: Bell, disabled: true, badge: 'Скоро' },
  { name: 'Settings', href: '/settings', icon: Settings, disabled: true, badge: 'Скоро' },
]
```

Create `frontend/app/(dashboard)/dashboards/page.tsx`:
```tsx
import React from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '../../../src/features/auth/model/session'
import { workspaceGateway } from '../../../src/features/workspace/api/workspace-gateway'
import { dashboardGateway } from '../../../src/features/dashboard/api/dashboard-gateway'
import { DashboardListView } from '../../../src/features/dashboard/ui/dashboard-list-view'

export const dynamic = 'force-dynamic'

export default async function DashboardsPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const userId = session.userId
  const currentWorkspace = await workspaceGateway.getCurrentWorkspace(userId).catch(() => null)
  const workspaceId = currentWorkspace?.workspace.id ?? 'ws-1'

  const dashboards = await dashboardGateway.list(userId, workspaceId).catch(() => [])

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <DashboardListView
        initialDashboards={dashboards}
        userId={userId}
        workspaceId={workspaceId}
      />
    </main>
  )
}
```

Create `frontend/app/(dashboard)/dashboards/[id]/page.tsx`:
```tsx
import React from 'react'
import { notFound, redirect } from 'next/navigation'
import { getSession } from '../../../../src/features/auth/model/session'
import { workspaceGateway } from '../../../../src/features/workspace/api/workspace-gateway'
import { dashboardGateway } from '../../../../src/features/dashboard/api/dashboard-gateway'
import { DashboardViewer } from '../../../../src/features/dashboard/ui/dashboard-viewer'

export const dynamic = 'force-dynamic'

interface DashboardDetailPageProps {
  params: Promise<{ id: string }>
}

export default async function DashboardDetailPage({ params }: DashboardDetailPageProps) {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const { id } = await params
  const userId = session.userId
  const currentWorkspace = await workspaceGateway.getCurrentWorkspace(userId).catch(() => null)
  const workspaceId = currentWorkspace?.workspace.id ?? 'ws-1'

  let dashboard
  try {
    dashboard = await dashboardGateway.getById(id, userId, workspaceId)
  } catch {
    notFound()
  }

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <DashboardViewer
        dashboard={dashboard}
        userId={userId}
        workspaceId={workspaceId}
      />
    </main>
  )
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm --prefix frontend test -- src/features/dashboard/ui/dashboard-viewer.test.tsx`
Expected: PASS (1 test passed).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard frontend/src/shared/ui/layout/sidebar.tsx frontend/app/\(dashboard\)/dashboards
git commit -m "feat(dashboard): implement dashboard viewer, navigation and next.js pages"
```

---

### Task 7: Full Test Suite, Typecheck & Verification

**Files:**
- Verification only: all test suites, type checking, linting, contract verification.

- [ ] **Step 1: Run frontend test suite**
Run: `npm --prefix frontend test`
Expected: All tests pass (including existing and new dashboard tests).

- [ ] **Step 2: Run frontend typecheck**
Run: `npm --prefix frontend run typecheck`
Expected: PASS with 0 errors.

- [ ] **Step 3: Run frontend lint**
Run: `npm --prefix frontend run lint`
Expected: PASS with 0 warnings/errors.

- [ ] **Step 4: Run backend test suite**
Run: `(cd backend && ./vendor/bin/phpunit)`
Expected: PASS with 113+ tests.

- [ ] **Step 5: Commit progress update to roadmap**
Update `docs/roadmap/08-dashboard-builder.md` with completed Frontend Dashboard Management & Viewer milestone.
```bash
git add docs/roadmap/08-dashboard-builder.md
git commit -m "docs(roadmap): update Phase 8 progress with dashboard viewer implementation"
```
