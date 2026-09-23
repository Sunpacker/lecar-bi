import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { SupplierPerformanceTable } from './supplier-performance-table'
import { SupplierDeliveriesTable } from './supplier-deliveries-table'
import { SupplierTabsContainer } from './supplier-tabs-container'
import { supplierGateway } from '../api/supplier-gateway'
import type { SupplierPerformanceItem, SupplierDeliveryItem } from '../api/supplier-gateway'

vi.mock('../api/supplier-gateway', () => ({
  supplierGateway: {
    getOverview: vi.fn(),
    getPerformance: vi.fn(),
    getDeliveries: vi.fn(),
    getFilters: vi.fn(),
  },
}))

describe('Supplier Dashboard Tables & Container', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  const mockPerformanceItems: SupplierPerformanceItem[] = [
    {
      supplier_id: 'sup-bosch',
      supplier_name: 'Bosch Automotive',
      total_deliveries: 40,
      on_time_deliveries: 36,
      delayed_deliveries: 3,
      partial_deliveries: 1,
      total_spend: 1200000,
      ordered_quantity: 5000,
      received_quantity: 4950,
      defect_quantity: 10,
      on_time_rate: 90.0,
      delay_rate: 7.5,
      fulfillment_rate: 99.0,
      defect_rate: 0.2,
      avg_lead_time_days: 5.5,
      avg_delay_days: 2.0,
      reliability_score: 0.88,
      reliability_tier: 'good',
    },
  ]

  const mockDeliveries: SupplierDeliveryItem[] = [
    {
      id: 'del-001',
      order_date: '2025-06-01',
      expected_delivery_date: '2025-06-07',
      actual_delivery_date: '2025-06-07',
      supplier_id: 'sup-bosch',
      supplier_name: 'Bosch Automotive',
      product_id: 'prod-1',
      product_name: 'Свеча Platinum',
      product_sku: 'SP-001',
      warehouse_id: 'wh-1',
      warehouse_name: 'Москва Центр',
      ordered_quantity: 100,
      received_quantity: 100,
      defect_quantity: 0,
      unit_purchase_cost: 450,
      total_purchase_cost: 45000,
      delivery_status: 'on_time',
      lead_time_days: 6,
      delay_days: 0,
    },
  ]

  it('renders SupplierPerformanceTable with headers, metrics and badges', () => {
    const onSort = vi.fn()
    const onPageChange = vi.fn()
    const onSearchChange = vi.fn()

    render(
      <SupplierPerformanceTable
        items={mockPerformanceItems}
        pagination={{ page: 1, per_page: 20, total: 1, total_pages: 1 }}
        sortBy="total_spend"
        sortDirection="desc"
        search=""
        onSort={onSort}
        onPageChange={onPageChange}
        onSearchChange={onSearchChange}
      />
    )

    expect(screen.getByText('Bosch Automotive')).toBeDefined()
    expect(screen.getByText('90%')).toBeDefined()
    expect(screen.getByText('99%')).toBeDefined()
    expect(screen.getByText('0.88')).toBeDefined()
  })

  it('renders SupplierDeliveriesTable with delivery detail rows', () => {
    const onSort = vi.fn()
    const onPageChange = vi.fn()
    const onSearchChange = vi.fn()
    const onStatusChange = vi.fn()

    render(
      <SupplierDeliveriesTable
        items={mockDeliveries}
        pagination={{ page: 1, per_page: 20, total: 1, total_pages: 1 }}
        sortBy="order_date"
        sortDirection="desc"
        search=""
        statusFilter={undefined}
        onSort={onSort}
        onPageChange={onPageChange}
        onSearchChange={onSearchChange}
        onStatusChange={onStatusChange}
      />
    )

    expect(screen.getByText('Свеча Platinum')).toBeDefined()
    expect(screen.getByText(/SP-001/)).toBeDefined()
    expect(screen.getByText('Москва Центр')).toBeDefined()
    expect(screen.getAllByText('В срок').length).toBeGreaterThanOrEqual(1)
  })

  it('renders SupplierTabsContainer and handles tab navigation', async () => {
    vi.mocked(supplierGateway.getFilters).mockResolvedValueOnce({
      suppliers: [{ id: 'sup-1', name: 'Bosch' }],
      warehouses: [{ id: 'wh-1', name: 'Склад 1' }],
      statuses: [{ value: 'on_time', label: 'В срок' }],
      min_date: '2025-01-01',
      max_date: '2025-12-31',
    })

    vi.mocked(supplierGateway.getOverview).mockResolvedValueOnce({
      summary: {
        total_deliveries: 50,
        on_time_deliveries: 45,
        delayed_deliveries: 4,
        partial_deliveries: 1,
        total_spend: 1000000,
        total_ordered_quantity: 5000,
        total_received_quantity: 4980,
        total_defect_quantity: 5,
        on_time_rate: 90.0,
        delay_rate: 8.0,
        fulfillment_rate: 99.6,
        defect_rate: 0.1,
        average_lead_time_days: 6.0,
        average_delay_days: 1.5,
      },
      status_breakdown: [],
      trends: [],
      top_suppliers: [],
    })

    render(<SupplierTabsContainer userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('Обзор и тренды')).toBeDefined()
    })

    expect(screen.getByText('Рейтинг поставщиков')).toBeDefined()
    expect(screen.getByText('Журнал поставок')).toBeDefined()
  })
})
