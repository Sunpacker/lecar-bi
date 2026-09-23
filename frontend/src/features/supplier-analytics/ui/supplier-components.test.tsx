import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { SupplierKpiCards } from './supplier-kpi-cards'
import { SupplierStatusBreakdown } from './supplier-status-breakdown'
import { SupplierFiltersBar } from './supplier-filters-bar'
import { SupplierTrendsChart } from './supplier-trends-chart'
import type { SupplierSummary, DeliveryStatusBreakdownItem, SupplierTrendPoint } from '../api/supplier-gateway'

describe('Supplier Analytics UI Components', () => {
  const mockSummary: SupplierSummary = {
    total_deliveries: 120,
    on_time_deliveries: 102,
    delayed_deliveries: 12,
    partial_deliveries: 6,
    total_spend: 3450000,
    total_ordered_quantity: 15000,
    total_received_quantity: 14750,
    total_defect_quantity: 45,
    on_time_rate: 85.0,
    delay_rate: 10.0,
    fulfillment_rate: 98.3,
    defect_rate: 0.3,
    average_lead_time_days: 6.8,
    average_delay_days: 3.5,
  }

  const mockBreakdown: DeliveryStatusBreakdownItem[] = [
    { status: 'on_time', count: 102, share_percentage: 85.0, quantity: 13000 },
    { status: 'delayed', count: 12, share_percentage: 10.0, quantity: 1250 },
    { status: 'partial', count: 6, share_percentage: 5.0, quantity: 500 },
  ]

  const mockTrends: SupplierTrendPoint[] = [
    {
      period: '2025-01',
      deliveries_count: 10,
      on_time_deliveries: 9,
      total_spend: 250000,
      on_time_rate: 90.0,
      fulfillment_rate: 99.0,
      avg_lead_time_days: 6.0,
    },
    {
      period: '2025-02',
      deliveries_count: 12,
      on_time_deliveries: 10,
      total_spend: 310000,
      on_time_rate: 83.3,
      fulfillment_rate: 97.5,
      avg_lead_time_days: 7.2,
    },
  ]

  it('renders SupplierKpiCards with accurate metrics', () => {
    render(<SupplierKpiCards summary={mockSummary} />)

    expect(screen.getByTestId('kpi-total-spend')).toBeDefined()
    expect(screen.getByTestId('kpi-total-deliveries')).toBeDefined()
    expect(screen.getByTestId('kpi-on-time-rate')).toBeDefined()
    expect(screen.getByTestId('kpi-fulfillment-rate')).toBeDefined()
    expect(screen.getByTestId('kpi-lead-time')).toBeDefined()
    expect(screen.getByTestId('kpi-defect-rate')).toBeDefined()

    expect(screen.getByText('85%')).toBeDefined()
    expect(screen.getByText('98.3%')).toBeDefined()
    expect(screen.getByText('6.8 дн.')).toBeDefined()
  })

  it('renders SupplierStatusBreakdown with all status categories', () => {
    render(<SupplierStatusBreakdown breakdown={mockBreakdown} totalDeliveries={120} />)

    expect(screen.getByText('В срок')).toBeDefined()
    expect(screen.getByText('С задержкой')).toBeDefined()
    expect(screen.getByText('Частично')).toBeDefined()
    expect(screen.getByText('102 заказов (85%)')).toBeDefined()
  })

  it('renders SupplierTrendsChart without crashing', () => {
    const { container } = render(<SupplierTrendsChart trends={mockTrends} />)
    expect(container).toBeDefined()
  })

  it('renders SupplierFiltersBar and invokes filter changes', () => {
    const onFilterChange = vi.fn()
    const options = {
      suppliers: [
        { id: 'sup-1', name: 'Bosch' },
        { id: 'sup-2', name: 'Brembo' },
      ],
      warehouses: [
        { id: 'wh-1', name: 'Центральный' },
      ],
      statuses: [
        { value: 'on_time', label: 'В срок' },
      ],
      min_date: '2025-01-01',
      max_date: '2025-12-31',
    }

    render(
      <SupplierFiltersBar
        options={options}
        filters={{ dateFrom: '2025-01-01', dateTo: '2025-12-31' }}
        onFilterChange={onFilterChange}
      />
    )

    expect(screen.getByText('Все поставщики')).toBeDefined()
    expect(screen.getByText('Все склады')).toBeDefined()

    const resetButton = screen.getByRole('button', { name: /Сбросить/i })
    fireEvent.click(resetButton)
    expect(onFilterChange).toHaveBeenCalled()
  })
})
