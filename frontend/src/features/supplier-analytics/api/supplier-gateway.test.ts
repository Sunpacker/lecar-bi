import { describe, it, expect, vi, beforeEach } from 'vitest'
import { supplierGateway } from './supplier-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    GET: vi.fn(),
  },
}))

describe('supplierGateway', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches supplier overview with headers and query parameters', async () => {
    const mockOverview = {
      summary: {
        total_deliveries: 100,
        on_time_deliveries: 85,
        delayed_deliveries: 10,
        partial_deliveries: 5,
        total_spend: 1500000,
        total_ordered_quantity: 10000,
        total_received_quantity: 9800,
        total_defect_quantity: 45,
        on_time_rate: 85.0,
        delay_rate: 10.0,
        fulfillment_rate: 98.0,
        defect_rate: 0.5,
        average_lead_time_days: 6.5,
        average_delay_days: 3.2,
      },
      status_breakdown: [],
      trends: [],
      top_suppliers: [],
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockOverview,
      error: undefined,
    } as any)

    const result = await supplierGateway.getOverview('user-1', 'ws-1', {
      dateFrom: '2025-01-01',
      dateTo: '2025-12-31',
      supplierId: 'sup-1',
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/suppliers/overview',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        params: {
          query: {
            date_from: '2025-01-01',
            date_to: '2025-12-31',
            supplier_id: 'sup-1',
            warehouse_id: undefined,
          },
        },
      }),
    )
    expect(result).toEqual(mockOverview)
  })

  it('fetches supplier performance with sorting and pagination', async () => {
    const mockPerformance = {
      items: [
        {
          supplier_id: 'sup-1',
          supplier_name: 'Bosch',
          total_deliveries: 50,
          total_spend: 800000,
          on_time_rate: 90.0,
          fulfillment_rate: 99.0,
          defect_rate: 0.2,
          reliability_score: 0.88,
          reliability_tier: 'good' as const,
        },
      ],
      pagination: {
        page: 1,
        per_page: 20,
        total: 1,
        total_pages: 1,
      },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockPerformance,
      error: undefined,
    } as any)

    const result = await supplierGateway.getPerformance('user-1', 'ws-1', {
      page: 1,
      perPage: 20,
      sortBy: 'total_spend',
      sortDirection: 'desc',
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/suppliers/performance',
      expect.objectContaining({
        params: {
          query: expect.objectContaining({
            page: 1,
            per_page: 20,
            sort_by: 'total_spend',
            sort_direction: 'desc',
          }),
        },
      }),
    )
    expect(result).toEqual(mockPerformance)
  })

  it('fetches deliveries journal with filters', async () => {
    const mockDeliveries = {
      items: [],
      pagination: {
        page: 1,
        per_page: 10,
        total: 0,
        total_pages: 0,
      },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockDeliveries,
      error: undefined,
    } as any)

    const result = await supplierGateway.getDeliveries('user-1', 'ws-1', {
      status: 'delayed',
      page: 1,
      perPage: 10,
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/suppliers/deliveries',
      expect.objectContaining({
        params: {
          query: expect.objectContaining({
            status: 'delayed',
            page: 1,
            per_page: 10,
          }),
        },
      }),
    )
    expect(result).toEqual(mockDeliveries)
  })

  it('fetches filter options', async () => {
    const mockFilters = {
      suppliers: [{ id: 's1', name: 'Supplier 1' }],
      warehouses: [{ id: 'w1', name: 'Warehouse 1' }],
      statuses: [{ value: 'on_time', label: 'В срок' }],
      min_date: '2025-01-01',
      max_date: '2025-12-31',
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockFilters,
      error: undefined,
    } as any)

    const result = await supplierGateway.getFilters('user-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/analytics/suppliers/filters',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
      }),
    )
    expect(result).toEqual(mockFilters)
  })

  it('throws descriptive error on API error response', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: undefined,
      error: { message: 'Workspace forbidden' },
    } as any)

    await expect(supplierGateway.getOverview('user-1', 'ws-1')).rejects.toThrow(
      'Workspace forbidden',
    )
  })
})
