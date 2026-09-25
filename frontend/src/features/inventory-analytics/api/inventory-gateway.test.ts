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
          'X-Workspace-Id': 'ws-1',
        },
        params: {
          query: {
            warehouse_id: 'wh-1',
            as_of_date: undefined,
          },
        },
      }),
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
      }),
    )
    expect(result).toEqual(mockItems)
  })

  it('fetches inventory filters', async () => {
    const mockFilters = {
      warehouses: [],
      statuses: [],
      latest_snapshot_date: '2025-12-31',
      categories: [],
      suppliers: [],
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
          'X-Workspace-Id': 'ws-1',
        },
      }),
    )
    expect(result).toEqual(mockFilters)
  })

  it('fetches ABC/XYZ summary with filters', async () => {
    const mockSummary = {
      data: {
        total_products: 10,
        total_revenue: 500000,
        total_inventory_value: 300000,
        matrix: [],
        abc_distribution: [],
        xyz_distribution: [],
        period_days: 90,
        start_date: '2025-10-01',
        end_date: '2025-12-31',
      },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockSummary,
      error: undefined,
    } as any)

    const result = await inventoryGateway.getAbcXyzSummary('user-1', 'ws-1', {
      periodDays: 90,
      categoryId: 'cat-1',
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/inventory/abc-xyz/summary',
      expect.objectContaining({
        headers: {
          'X-Workspace-Id': 'ws-1',
        },
        params: {
          query: {
            period_days: 90,
            category_id: 'cat-1',
            warehouse_id: undefined,
            supplier_id: undefined,
          },
        },
      }),
    )
    expect(result).toEqual(mockSummary)
  })

  it('fetches ABC/XYZ product items with pagination, search, and group filter', async () => {
    const mockItems = {
      items: [],
      pagination: { page: 1, per_page: 20, total: 0, total_pages: 0 },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockItems,
      error: undefined,
    } as any)

    const result = await inventoryGateway.getAbcXyzItems('user-1', 'ws-1', {
      group: 'AX',
      search: 'brake',
      page: 1,
      perPage: 20,
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/inventory/abc-xyz/items',
      expect.objectContaining({
        headers: {
          'X-Workspace-Id': 'ws-1',
        },
        params: {
          query: {
            period_days: undefined,
            warehouse_id: undefined,
            category_id: undefined,
            supplier_id: undefined,
            abc_class: undefined,
            xyz_class: undefined,
            group: 'AX',
            search: 'brake',
            page: 1,
            per_page: 20,
            sort_by: undefined,
            sort_direction: undefined,
          },
        },
      }),
    )
    expect(result).toEqual(mockItems)
  })

  it('fetches forecast with product and warehouse path params', async () => {
    const mockForecast = {
      workspace_id: 'ws-1',
      product_id: 'prod-100',
      product_name: 'Тормозные колодки Front',
      product_sku: 'BP-100',
      warehouse_id: 'wh-100',
      warehouse_name: 'Центральный склад МСК',
      as_of_date: '2025-12-31',
      generated_at: '2025-12-31T23:59:59Z',
      horizon_days: 28,
      model_method: 'seasonal_naive_dow',
      model_version: '1.0.0',
      status: 'ready',
      data_freshness: {
        sales_date: '2025-12-31',
        inventory_date: '2025-12-31',
        supplier_date: '2025-12-31',
      },
      points: [
        {
          date: '2026-01-01',
          point_estimate: 12.5,
          lower_bound: 8.0,
          upper_bound: 17.0,
          interval_level: 0.8,
          is_stockout_day: false,
        },
      ],
      measured_history: [
        {
          date: '2025-12-30',
          point_estimate: 10.0,
          is_stockout_day: false,
        },
      ],
      quality_metrics: [
        {
          metric_name: 'wape',
          metric_value: 0.185,
          horizon_days: 28,
          evaluation_windows: 4,
        },
      ],
      stock_risk: {
        current_quantity_available: 120,
        current_safety_stock: 40,
        current_reorder_point: 80,
        estimated_depletion_date: '2026-01-10',
        estimated_reorder_threshold_date: '2026-01-05',
        estimated_order_placement_date: '2025-12-29',
        median_lead_time_days: 7,
        assumptions: 'Расчет исчерпания выполнен без учета ожидаемых поступлений',
      },
      assumptions: ['Расчет исчерпания выполнен без учета ожидаемых поступлений'],
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockForecast,
      error: undefined,
    } as any)

    const result = await inventoryGateway.getForecast(
      'user-1',
      'ws-1',
      'prod-100',
      'wh-100',
      {
        horizonDays: 28,
      },
    )

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/forecasts/{productId}/{warehouseId}',
      expect.objectContaining({
        headers: {
          'X-Workspace-Id': 'ws-1',
        },
        params: {
          path: {
            productId: 'prod-100',
            warehouseId: 'wh-100',
          },
          query: {
            horizon_days: 28,
            as_of_date: undefined,
          },
        },
      }),
    )
    expect(result).toEqual(mockForecast)
  })

  it('throws error when forecast fetch fails', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: undefined,
      error: { message: 'Forecast not found' },
    } as any)

    await expect(
      inventoryGateway.getForecast('user-1', 'ws-1', 'prod-404', 'wh-404'),
    ).rejects.toThrow('Forecast not found')
  })
})
