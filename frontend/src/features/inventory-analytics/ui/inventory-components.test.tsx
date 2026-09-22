import React from 'react'
import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'
import { InventoryKpiCards } from './inventory-kpi-cards'
import { InventoryHealthBreakdown } from './inventory-health-breakdown'
import { InventoryWarehouseBreakdown } from './inventory-warehouse-breakdown'
import { InventoryFiltersBar } from './inventory-filters-bar'
import { InventoryItemsTable } from './inventory-items-table'

describe('Inventory UI Components', () => {
  const mockSummary = {
    total_items: 45,
    total_quantity_on_hand: 1200,
    total_quantity_reserved: 150,
    total_quantity_available: 1050,
    total_inventory_value: 3450000,
    critical_count: 4,
    overstock_count: 6,
    out_of_stock_count: 2,
    optimal_count: 33,
    average_days_of_stock: 32.5,
  }

  it('renders KPI cards with correct formatted values', () => {
    render(<InventoryKpiCards summary={mockSummary} />)

    expect(screen.getByText('Стоимость остатков')).toBeDefined()
    expect(screen.getByText('Доступно шт.')).toBeDefined()
    expect(screen.getByText('Критические запасы')).toBeDefined()
    expect(screen.getByText('Избыточный запас')).toBeDefined()
    expect(screen.getByText('4 поз.')).toBeDefined()
    expect(screen.getByText('6 поз.')).toBeDefined()
  })

  it('renders health breakdown distribution badges', () => {
    const health = [
      {
        status: 'optimal' as const,
        label: 'В норме',
        items_count: 33,
        total_value: 2500000,
        share: 0.73,
      },
      {
        status: 'critical' as const,
        label: 'Критический',
        items_count: 4,
        total_value: 200000,
        share: 0.09,
      },
      {
        status: 'overstock' as const,
        label: 'Избыток',
        items_count: 6,
        total_value: 700000,
        share: 0.13,
      },
      {
        status: 'out_of_stock' as const,
        label: 'Дефицит',
        items_count: 2,
        total_value: 0,
        share: 0.05,
      },
    ]

    render(
      <InventoryHealthBreakdown
        items={health}
        selectedStatus={null}
        onSelectStatus={() => {}}
      />,
    )

    expect(screen.getByText('Распределение здоровья запасов')).toBeDefined()
    expect(screen.getByText('В норме: 33')).toBeDefined()
    expect(screen.getByText('Критический: 4')).toBeDefined()
  })

  it('renders warehouse cards and triggers onSelectWarehouse', () => {
    const onSelect = vi.fn()
    const warehouses = [
      {
        warehouse_id: 'wh-msk',
        warehouse_name: 'Москва Центр',
        warehouse_code: 'WH-MSK-01',
        total_quantity: 600,
        total_value: 1800000,
        items_count: 25,
        critical_count: 2,
        overstock_count: 3,
      },
    ]

    render(
      <InventoryWarehouseBreakdown
        warehouses={warehouses}
        selectedWarehouseId={null}
        onSelectWarehouse={onSelect}
      />,
    )

    expect(screen.getByText('Москва Центр')).toBeDefined()
    const card = screen.getByText('Москва Центр').closest('button')
    if (card) fireEvent.click(card)
    expect(onSelect).toHaveBeenCalledWith('wh-msk')
  })

  it('renders items table with records and pagination controls', () => {
    const onPageChange = vi.fn()
    const onSortChange = vi.fn()
    const items = [
      {
        id: '1',
        product_id: 'p1',
        product_name: 'Шина зимняя Conti',
        product_sku: 'CONTI-01',
        category_id: 'c1',
        category_name: 'Шины',
        warehouse_id: 'wh1',
        warehouse_name: 'Москва',
        warehouse_code: 'WH-MSK',
        quantity_on_hand: 50,
        quantity_reserved: 10,
        quantity_available: 40,
        unit_cost: 4500,
        inventory_value: 225000,
        sales_velocity: 2.5,
        days_of_stock: 16.0,
        stock_health: 'optimal' as const,
        stock_health_label: 'В норме',
        safety_stock: 15,
        reorder_point: 30,
      },
    ]

    render(
      <InventoryItemsTable
        items={items}
        pagination={{ page: 1, per_page: 20, total: 1, total_pages: 1 }}
        sortBy="quantity_available"
        sortDirection="asc"
        onSort={onSortChange}
        onPageChange={onPageChange}
      />,
    )

    expect(screen.getByText('Шина зимняя Conti')).toBeDefined()
    expect(screen.getByText(/CONTI-01/)).toBeDefined()
    expect(screen.getByText('16.0 дн.')).toBeDefined()
    expect(screen.getByText('В норме')).toBeDefined()
  })
})
