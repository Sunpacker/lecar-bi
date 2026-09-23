import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
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
      latest_snapshot_date: '2026-09-23',
      categories: [],
      suppliers: [],
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
