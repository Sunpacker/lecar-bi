import React from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { InventoryDashboard } from './inventory-dashboard'
import { inventoryGateway } from '../api/inventory-gateway'

vi.mock('../api/inventory-gateway', () => ({
  inventoryGateway: {
    getSummary: vi.fn(),
    getItems: vi.fn(),
    getFilters: vi.fn(),
  },
}))

vi.mock('next/navigation', () => ({
  useSearchParams: () => new URLSearchParams(),
}))

describe('InventoryDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders skeleton on loading and displays data on success', async () => {
    vi.mocked(inventoryGateway.getFilters).mockResolvedValueOnce({
      warehouses: [{ id: 'wh-1', name: 'Москва', code: 'WH-01' }],
      statuses: [{ value: 'critical', label: 'Критический' }],
      categories: [{ id: 'c1', name: 'Тормоза', code: 'BR' }],
      suppliers: [{ id: 's1', name: 'Brembo' }],
      latest_snapshot_date: '2025-12-31',
    })

    vi.mocked(inventoryGateway.getSummary).mockResolvedValueOnce({
      summary: {
        total_items: 10,
        total_quantity_on_hand: 500,
        total_quantity_reserved: 50,
        total_quantity_available: 450,
        total_inventory_value: 1200000,
        critical_count: 2,
        overstock_count: 1,
        out_of_stock_count: 0,
        optimal_count: 7,
        average_days_of_stock: 22.0,
      },
      health_breakdown: [
        {
          status: 'optimal' as const,
          label: 'В норме',
          items_count: 7,
          total_value: 800000,
          share: 0.7,
        },
      ],
      warehouses: [
        {
          warehouse_id: 'wh-1',
          warehouse_name: 'Москва',
          warehouse_code: 'WH-01',
          total_quantity: 450,
          total_value: 1200000,
          items_count: 10,
          critical_count: 2,
          overstock_count: 1,
        },
      ],
      as_of_date: '2025-12-31',
    })

    vi.mocked(inventoryGateway.getItems).mockResolvedValueOnce({
      items: [
        {
          id: '1',
          product_id: 'p1',
          product_name: 'Колодки Brembo',
          product_sku: 'BR-01',
          category_id: 'c1',
          category_name: 'Тормоза',
          warehouse_id: 'wh-1',
          warehouse_name: 'Москва',
          warehouse_code: 'WH-01',
          quantity_on_hand: 20,
          quantity_reserved: 2,
          quantity_available: 18,
          unit_cost: 2500,
          inventory_value: 50000,
          sales_velocity: 1.2,
          days_of_stock: 15.0,
          stock_health: 'optimal' as const,
          stock_health_label: 'В норме',
          safety_stock: 10,
          reorderPoint: 20,
        } as any,
      ],
      pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 },
    })

    render(<InventoryDashboard userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByTestId('inventory-loading-skeleton')).toBeDefined()

    await waitFor(() => {
      expect(screen.getByText('Стоимость остатков')).toBeDefined()
      expect(screen.getByText('Колодки Brembo')).toBeDefined()
    })
  })
})
