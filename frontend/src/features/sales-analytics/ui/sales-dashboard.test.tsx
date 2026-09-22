import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor, fireEvent } from '@testing-library/react'
import React from 'react'
import { SalesDashboard } from './sales-dashboard'
import { salesGateway } from '../api/sales-gateway'

vi.mock('../api/sales-gateway', () => ({
  salesGateway: {
    getOverview: vi.fn(),
    getFilterOptions: vi.fn(),
    getRecords: vi.fn(),
  },
}))

describe('SalesDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    window.location.search = ''
  })

  const mockOverviewData = {
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
  }

  const mockRecordsData = {
    items: [
      {
        id: 'rec-1',
        order_id: 'ord-1',
        order_number: 'ORD-5001',
        order_date: '2025-01-01',
        product_id: 'prod-1',
        product_name: 'Зимняя резина R16',
        product_sku: 'TIRE-01',
        category_id: 'cat-1',
        category_name: 'Шины',
        region_id: 'reg-1',
        region_name: 'Москва',
        brand_name: 'Nokian',
        quantity: 4,
        unit_price: 7500,
        total_price: 30000,
        gross_profit: 9000,
        status: 'completed',
      },
    ],
    pagination: {
      page: 1,
      per_page: 10,
      total: 1,
      total_pages: 1,
    },
  }

  it('renders loading skeleton then displays dashboard data and detail table', async () => {
    vi.mocked(salesGateway.getFilterOptions).mockResolvedValueOnce({
      categories: [{ id: 'cat-1', name: 'Шины' }],
      regions: [{ id: 'reg-1', name: 'Москва', code: 'MSK' }],
      min_date: '2025-01-01',
      max_date: '2025-12-31',
    })
    vi.mocked(salesGateway.getOverview).mockResolvedValueOnce(mockOverviewData)
    vi.mocked(salesGateway.getRecords).mockResolvedValueOnce(mockRecordsData)

    render(<SalesDashboard userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByTestId('sales-dashboard-loading')).toBeDefined()

    await waitFor(() => {
      expect(screen.getByText('Аналитика продаж')).toBeDefined()
      expect(screen.getByTestId('kpi-revenue').textContent).toContain('500')
      expect(screen.getByText('Детализация продаж')).toBeDefined()
      expect(screen.getByText('ORD-5001')).toBeDefined()
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
    vi.mocked(salesGateway.getRecords).mockResolvedValueOnce({
      items: [],
      pagination: { page: 1, per_page: 10, total: 0, total_pages: 1 },
    })

    render(<SalesDashboard userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('Нет данных о продажах за выбранный период')).toBeDefined()
    })
  })

  it('cross-filtering: clicking category updates active filter and reloads records', async () => {
    vi.mocked(salesGateway.getFilterOptions).mockResolvedValue({
      categories: [{ id: 'cat-1', name: 'Шины' }],
      regions: [{ id: 'reg-1', name: 'Москва', code: 'MSK' }],
      min_date: '2025-01-01',
      max_date: '2025-12-31',
    })
    vi.mocked(salesGateway.getOverview).mockResolvedValue(mockOverviewData)
    vi.mocked(salesGateway.getRecords).mockResolvedValue(mockRecordsData)

    render(<SalesDashboard userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('Аналитика продаж')).toBeDefined()
    })

    const categoryItem = screen.getByRole('button', { name: /Шины/i })
    fireEvent.click(categoryItem)

    await waitFor(() => {
      expect(salesGateway.getOverview).toHaveBeenCalledWith(
        'user-1',
        'ws-1',
        expect.objectContaining({ categoryId: 'cat-1' }),
      )
      expect(salesGateway.getRecords).toHaveBeenCalledWith(
        'user-1',
        'ws-1',
        expect.objectContaining({ categoryId: 'cat-1' }),
      )
    })
  })

  it('reset button clears filters and resets pagination', async () => {
    vi.mocked(salesGateway.getFilterOptions).mockResolvedValue({
      categories: [{ id: 'cat-1', name: 'Шины' }],
      regions: [{ id: 'reg-1', name: 'Москва', code: 'MSK' }],
      min_date: '2025-01-01',
      max_date: '2025-12-31',
    })
    vi.mocked(salesGateway.getOverview).mockResolvedValue(mockOverviewData)
    vi.mocked(salesGateway.getRecords).mockResolvedValue(mockRecordsData)

    render(<SalesDashboard userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('Аналитика продаж')).toBeDefined()
    })

    const resetButton = screen.getByRole('button', { name: /^Сбросить$/ })
    fireEvent.click(resetButton)

    await waitFor(() => {
      expect(salesGateway.getOverview).toHaveBeenCalledWith('user-1', 'ws-1', {})
      expect(salesGateway.getRecords).toHaveBeenCalledWith(
        'user-1',
        'ws-1',
        expect.objectContaining({ page: 1 }),
      )
    })
  })
})
