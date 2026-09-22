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
