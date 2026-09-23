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
        total_quantity_reserved: 0,
        total_quantity_available: 5400,
        total_inventory_value: 14500000,
        out_of_stock_count: 5,
        critical_count: 8,
        optimal_count: 90,
        overstock_count: 17,
        average_days_of_stock: 42,
      },
      health_breakdown: [],
      warehouses: [],
      as_of_date: '2026-09-22',
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
        total_quantity_reserved: 0,
        total_quantity_available: 5400,
        total_inventory_value: 14500000,
        out_of_stock_count: 5,
        critical_count: 8,
        optimal_count: 90,
        overstock_count: 17,
        average_days_of_stock: 42,
      },
      health_breakdown: [],
      warehouses: [
        {
          warehouse_id: 'wh-1',
          warehouse_name: 'Склад Тольятти',
          warehouse_code: 'WH-01',
          total_quantity: 3200,
          total_value: 8000000,
          items_count: 85,
          critical_count: 2,
          overstock_count: 5,
        },
      ],
      as_of_date: '2026-09-22',
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

    vi.mocked(salesGateway.getOverview).mockRejectedValueOnce(
      new Error('Network failure'),
    )

    const result = await loadWidgetData(widget, 'user-1', 'ws-1')

    expect(result.error).toBe('Network failure')
    expect(result.loading).toBe(false)
  })

  it('applies dashboard filters to sales overview query', async () => {
    const widget: WidgetDetail = {
      id: 'w-1',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue' },
      position: { x: 0, y: 0, w: 4, h: 2 },
      options: {},
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
      options: {},
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

    expect(salesGateway.getOverview).toHaveBeenCalled()
  })

  it('applies warehouse and stock_health filters to inventory queries and sanitizes region_id', async () => {
    const widget: WidgetDetail = {
      id: 'w-3',
      title: 'Остатки',
      type: 'kpi_card',
      query_config: { dataset: 'inventory', metric: 'stock_quantity' },
      position: { x: 0, y: 0, w: 4, h: 2 },
      options: {},
    }

    vi.mocked(inventoryGateway.getSummary).mockResolvedValueOnce({
      summary: {
        total_items: 10,
        total_quantity_on_hand: 500,
        total_quantity_reserved: 0,
        total_quantity_available: 500,
        total_inventory_value: 1200000,
        out_of_stock_count: 2,
        overstock_count: 5,
        critical_count: 10,
        optimal_count: 400,
        average_days_of_stock: 30,
      },
      warehouses: [],
      health_breakdown: [],
      as_of_date: '2026-09-23',
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
})
