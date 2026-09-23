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
      options: {},
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
      latest_snapshot_date: '2026-09-23',
      categories: [],
      suppliers: [],
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
