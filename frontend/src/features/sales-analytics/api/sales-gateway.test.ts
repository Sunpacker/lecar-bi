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
    } as never)

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
    } as never)

    await expect(salesGateway.getOverview('user-1', 'ws-1')).rejects.toThrow(
      'Unauthorized',
    )
  })

  it('fetches sales records with filters, pagination and sorting', async () => {
    const mockRecords = {
      items: [
        {
          id: 'item-1',
          order_id: 'ord-1',
          order_number: 'ORD-101',
          order_date: '2026-01-15',
          product_id: 'prod-1',
          product_name: 'Колодки',
          product_sku: 'BRK-1',
          category_id: 'cat-1',
          category_name: 'Тормоза',
          region_id: 'reg-1',
          region_name: 'Москва',
          brand_name: 'Brembo',
          quantity: 2,
          unit_price: 3500,
          total_price: 7000,
          gross_profit: 2500,
          status: 'completed',
        },
      ],
      pagination: {
        page: 2,
        per_page: 20,
        total: 50,
        total_pages: 3,
      },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockRecords,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await salesGateway.getRecords('user-1', 'ws-1', {
      categoryId: 'cat-1',
      page: 2,
      perPage: 20,
      sortBy: 'total_price',
      sortDirection: 'desc',
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith('/analytics/sales/records', {
      params: {
        query: {
          date_from: undefined,
          date_to: undefined,
          category_id: 'cat-1',
          region_id: undefined,
          page: 2,
          per_page: 20,
          sort_by: 'total_price',
          sort_direction: 'desc',
        },
      },
      headers: {
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result.pagination.total).toBe(50)
    expect(result.items[0].product_name).toBe('Колодки')
  })
})
