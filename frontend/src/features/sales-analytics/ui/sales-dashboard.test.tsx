import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import React from 'react'
import { SalesDashboard } from './sales-dashboard'
import { salesGateway } from '../api/sales-gateway'

vi.mock('../api/sales-gateway', () => ({
  salesGateway: {
    getOverview: vi.fn(),
    getFilterOptions: vi.fn(),
  },
}))

describe('SalesDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders loading skeleton then displays dashboard data', async () => {
    vi.mocked(salesGateway.getFilterOptions).mockResolvedValueOnce({
      categories: [{ id: 'cat-1', name: 'Шины' }],
      regions: [{ id: 'reg-1', name: 'Москва', code: 'MSK' }],
      min_date: '2025-01-01',
      max_date: '2025-12-31',
    })

    vi.mocked(salesGateway.getOverview).mockResolvedValueOnce({
      summary: {
        total_revenue: 500000,
        order_count: 100,
        average_order_value: 5000,
        gross_profit: 150000,
        margin_rate: 0.3,
      },
      trend: [{ date: '2025-01-01', revenue: 50000, order_count: 10 }],
      categories: [
        {
          category_id: 'cat-1',
          category_name: 'Шины',
          revenue: 500000,
          order_count: 100,
          revenue_share: 1.0,
        },
      ],
      regions: [
        {
          region_id: 'reg-1',
          region_name: 'Москва',
          region_code: 'MSK',
          revenue: 500000,
          order_count: 100,
          revenue_share: 1.0,
        },
      ],
    })

    render(<SalesDashboard userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByTestId('sales-dashboard-loading')).toBeDefined()

    await waitFor(() => {
      expect(screen.getByText('Аналитика продаж')).toBeDefined()
      expect(screen.getByTestId('kpi-revenue').textContent).toContain('500')
    })
  })

  it('renders empty state when order count is zero', async () => {
    vi.mocked(salesGateway.getFilterOptions).mockResolvedValueOnce({
      categories: [],
      regions: [],
      min_date: '2025-01-01',
      max_date: '2025-12-31',
    })

    vi.mocked(salesGateway.getOverview).mockResolvedValueOnce({
      summary: {
        total_revenue: 0,
        order_count: 0,
        average_order_value: 0,
        gross_profit: 0,
        margin_rate: 0,
      },
      trend: [],
      categories: [],
      regions: [],
    })

    render(<SalesDashboard userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('Нет данных о продажах за выбранный период')).toBeDefined()
    })
  })
})
