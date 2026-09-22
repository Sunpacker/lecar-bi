import React from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AbcXyzMatrixGrid } from './abc-xyz-matrix-grid'
import { AbcXyzMethodologyCard } from './abc-xyz-methodology-card'
import { AbcXyzFiltersBar } from './abc-xyz-filters-bar'
import { AbcXyzItemsTable } from './abc-xyz-items-table'
import { AbcXyzView } from './abc-xyz-view'
import { InventoryTabsContainer } from './inventory-tabs-container'
import { inventoryGateway } from '../api/inventory-gateway'
import type { AbcXyzMatrixCell, AbcXyzProductItem } from '../api/inventory-gateway'

vi.mock('../api/inventory-gateway', () => ({
  inventoryGateway: {
    getSummary: vi.fn(),
    getItems: vi.fn(),
    getFilters: vi.fn(),
    getAbcXyzSummary: vi.fn(),
    getAbcXyzItems: vi.fn(),
  },
}))

let currentSearchParamTab: string | null = null

vi.mock('next/navigation', () => ({
  useSearchParams: () => ({
    get: (key: string) => {
      if (key === 'tab') return currentSearchParamTab
      return null
    },
    toString: () => (currentSearchParamTab ? `tab=${currentSearchParamTab}` : ''),
  }),
}))

const mockMatrix: AbcXyzMatrixCell[] = [
  {
    code: 'AX',
    label: 'Ключевые стабильные',
    description: 'Высокий оборот, стабильный спрос',
    recommendation: 'Just-in-Time, автоматические заказы',
    count: 5,
    count_share: 0.1,
    revenue: 500000,
    revenue_share: 0.5,
    inventory_value: 200000,
    inventory_value_share: 0.4,
  },
  {
    code: 'AY',
    label: 'Ключевые сезонные',
    description: 'Высокий оборот, сезонность',
    recommendation: 'Страховой запас для сглаживания',
    count: 3,
    count_share: 0.06,
    revenue: 250000,
    revenue_share: 0.25,
    inventory_value: 120000,
    inventory_value_share: 0.24,
  },
  {
    code: 'AZ',
    label: 'Ключевые редкие',
    description: 'Высокий оборот, спонтанный спрос',
    recommendation: 'Заказ под клиента',
    count: 1,
    count_share: 0.02,
    revenue: 50000,
    revenue_share: 0.05,
    inventory_value: 30000,
    inventory_value_share: 0.06,
  },
  {
    code: 'BX',
    label: 'Средние стабильные',
    description: 'Умеренный оборот, высокая точность',
    recommendation: 'Регулярные партии',
    count: 4,
    count_share: 0.08,
    revenue: 80000,
    revenue_share: 0.08,
    inventory_value: 40000,
    inventory_value_share: 0.08,
  },
  {
    code: 'BY',
    label: 'Средние сезонные',
    description: 'Умеренный оборот, сезонность',
    recommendation: 'Гибкие партии',
    count: 5,
    count_share: 0.1,
    revenue: 50000,
    revenue_share: 0.05,
    inventory_value: 35000,
    inventory_value_share: 0.07,
  },
  {
    code: 'BZ',
    label: 'Средние нерегулярные',
    description: 'Умеренный оборот, редкие продажи',
    recommendation: 'По заявкам',
    count: 2,
    count_share: 0.04,
    revenue: 20000,
    revenue_share: 0.02,
    inventory_value: 15000,
    inventory_value_share: 0.03,
  },
  {
    code: 'CX',
    label: 'Низкие стабильные',
    description: 'Длинный хвост, регулярный расход',
    recommendation: 'Редкие крупные поставки',
    count: 10,
    count_share: 0.2,
    revenue: 25000,
    revenue_share: 0.025,
    inventory_value: 50000,
    inventory_value_share: 0.1,
  },
  {
    code: 'CY',
    label: 'Низкие сезонные',
    description: 'Длинный хвост, сезонность',
    recommendation: 'Снижение остатка',
    count: 10,
    count_share: 0.2,
    revenue: 15000,
    revenue_share: 0.015,
    inventory_value: 30000,
    inventory_value_share: 0.06,
  },
  {
    code: 'CZ',
    label: 'Низкие неликвиды',
    description: 'Длинный хвост, спонтанный спрос',
    recommendation: 'Вывод из ассортимента',
    count: 10,
    count_share: 0.2,
    revenue: 10000,
    revenue_share: 0.01,
    inventory_value: 20000,
    inventory_value_share: 0.04,
  },
]

const mockProductItem: AbcXyzProductItem = {
  id: 'item-1',
  product_id: 'p-1',
  product_name: 'Тормозные колодки Premium',
  product_sku: 'BP-001',
  category_id: 'cat-1',
  category_name: 'Тормозная система',
  brand_name: 'Brembo',
  supplier_id: 'sup-1',
  supplier_name: 'Brembo Corp',
  total_revenue: 500000,
  revenue_share: 0.5,
  cumulative_revenue_share: 0.5,
  abc_class: 'A',
  total_units_sold: 250,
  period_sales: [80, 85, 85],
  average_sales: 83.3,
  standard_deviation: 2.9,
  coefficient_of_variation: 12.5,
  xyz_class: 'X',
  abc_xyz_group: 'AX',
  current_stock: 45,
  inventory_value: 90000,
}

describe('AbcXyzMatrixGrid', () => {
  it('renders matrix grid with 3x3 layout and triggers cell click', () => {
    const handleSelect = vi.fn()
    render(
      <AbcXyzMatrixGrid
        matrix={mockMatrix}
        selectedGroup={null}
        onSelectGroup={handleSelect}
      />,
    )

    expect(screen.getByText('Класс A')).toBeDefined()
    expect(screen.getByText('Класс B')).toBeDefined()
    expect(screen.getByText('Класс C')).toBeDefined()
    expect(screen.getByText('Класс X')).toBeDefined()
    expect(screen.getByText('Класс Y')).toBeDefined()
    expect(screen.getByText('Класс Z')).toBeDefined()

    // Find AX button
    const axBtn = screen.getByRole('button', {
      name: /сегмент ax: 5 товаров/i,
    })
    fireEvent.click(axBtn)
    expect(handleSelect).toHaveBeenCalledWith('AX')
  })

  it('toggles selection when clicking an already selected cell', () => {
    const handleSelect = vi.fn()
    render(
      <AbcXyzMatrixGrid
        matrix={mockMatrix}
        selectedGroup="AX"
        onSelectGroup={handleSelect}
      />,
    )

    const axBtn = screen.getByRole('button', {
      name: /сегмент ax: 5 товаров/i,
    })
    fireEvent.click(axBtn)
    expect(handleSelect).toHaveBeenCalledWith(null)
  })
})

describe('AbcXyzMethodologyCard', () => {
  it('toggles collapsible explanation on button click', () => {
    render(<AbcXyzMethodologyCard />)

    expect(screen.queryByText(/ABC-анализ \(вклад в выручку\)/i)).toBeNull()

    const toggleBtn = screen.getByRole('button', { name: /как читать матрицу/i })
    fireEvent.click(toggleBtn)

    expect(screen.getByText(/ABC-анализ \(вклад в выручку\)/i)).toBeDefined()
    expect(screen.getByText(/XYZ-анализ \(предсказуемость спроса\)/i)).toBeDefined()
    expect(
      screen.getByText(/Стратегии управления запасами по 9 сегментам/i),
    ).toBeDefined()

    fireEvent.click(screen.getByRole('button', { name: /скрыть справку/i }))
    expect(screen.queryByText(/ABC-анализ \(вклад в выручку\)/i)).toBeNull()
  })
})

describe('AbcXyzFiltersBar', () => {
  it('renders filters and fires events', () => {
    const handlePeriodChange = vi.fn()
    const handleWarehouseChange = vi.fn()
    const handleCategoryChange = vi.fn()
    const handleSupplierChange = vi.fn()
    const handleGroupChange = vi.fn()
    const handleSearchChange = vi.fn()
    const handleReset = vi.fn()

    render(
      <AbcXyzFiltersBar
        filterOptions={{
          warehouses: [{ id: 'wh-1', name: 'Центральный склад', code: 'WH-01' }],
          categories: [{ id: 'cat-1', name: 'Тормозная система', code: 'BRAKE' }],
          suppliers: [{ id: 'sup-1', name: 'Brembo' }],
          statuses: [],
          latest_snapshot_date: '2026-03-01',
        }}
        periodDays={90}
        warehouseId={null}
        categoryId={null}
        supplierId={null}
        selectedGroup={null}
        search=""
        onPeriodChange={handlePeriodChange}
        onWarehouseChange={handleWarehouseChange}
        onCategoryChange={handleCategoryChange}
        onSupplierChange={handleSupplierChange}
        onGroupChange={handleGroupChange}
        onSearchChange={handleSearchChange}
        onReset={handleReset}
      />,
    )

    // Search input
    const searchInput = screen.getByPlaceholderText('Поиск по артикулу или названию...')
    fireEvent.change(searchInput, { target: { value: 'колодки' } })
    expect(handleSearchChange).toHaveBeenCalledWith('колодки')

    // Quick group button
    const axBtn = screen.getByRole('button', { name: 'AX' })
    fireEvent.click(axBtn)
    expect(handleGroupChange).toHaveBeenCalledWith('AX')
  })
})

describe('AbcXyzItemsTable', () => {
  it('renders products and triggers sort and pagination', () => {
    const handleSort = vi.fn()
    const handlePageChange = vi.fn()

    render(
      <AbcXyzItemsTable
        items={[mockProductItem]}
        pagination={{ page: 1, per_page: 20, total: 25, total_pages: 2 }}
        sortBy="total_revenue"
        sortDirection="desc"
        loading={false}
        onSort={handleSort}
        onPageChange={handlePageChange}
      />,
    )

    expect(screen.getByText('Тормозные колодки Premium')).toBeDefined()
    expect(screen.getByText('BP-001')).toBeDefined()
    expect(screen.getByText('Brembo Corp')).toBeDefined()

    // Click sort on product name
    const nameHead = screen.getByText('Товар / Артикул')
    fireEvent.click(nameHead)
    expect(handleSort).toHaveBeenCalledWith('product_name')

    // Click next page
    const nextBtn = screen.getByRole('button', { name: /вперед/i })
    fireEvent.click(nextBtn)
    expect(handlePageChange).toHaveBeenCalledWith(2)
  })
})

describe('AbcXyzView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders loading skeleton and then full view with summary and items', async () => {
    vi.mocked(inventoryGateway.getFilters).mockResolvedValueOnce({
      warehouses: [{ id: 'wh-1', name: 'Москва', code: 'MOW' }],
      categories: [{ id: 'cat-1', name: 'Тормоза', code: 'BR' }],
      suppliers: [{ id: 'sup-1', name: 'Brembo' }],
      statuses: [],
      latest_snapshot_date: '2026-03-01',
    })

    vi.mocked(inventoryGateway.getAbcXyzSummary).mockResolvedValueOnce({
      data: {
        total_products: 50,
        total_revenue: 1000000,
        total_inventory_value: 500000,
        period_days: 90,
        start_date: '2025-12-01',
        end_date: '2026-03-01',
        abc_distribution: [
          {
            class: 'A',
            label: 'Класс A',
            count: 5,
            count_share: 0.1,
            revenue: 800000,
            revenue_share: 0.8,
          },
          {
            class: 'B',
            label: 'Класс B',
            count: 15,
            count_share: 0.3,
            revenue: 150000,
            revenue_share: 0.15,
          },
          {
            class: 'C',
            label: 'Класс C',
            count: 30,
            count_share: 0.6,
            revenue: 50000,
            revenue_share: 0.05,
          },
        ],
        xyz_distribution: [
          {
            class: 'X',
            label: 'Класс X',
            count: 15,
            count_share: 0.3,
            revenue: 600000,
            revenue_share: 0.6,
          },
          {
            class: 'Y',
            label: 'Класс Y',
            count: 20,
            count_share: 0.4,
            revenue: 300000,
            revenue_share: 0.3,
          },
          {
            class: 'Z',
            label: 'Класс Z',
            count: 15,
            count_share: 0.3,
            revenue: 100000,
            revenue_share: 0.1,
          },
        ],
        matrix: mockMatrix,
      },
    })

    vi.mocked(inventoryGateway.getAbcXyzItems).mockResolvedValueOnce({
      items: [mockProductItem],
      pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 },
    })

    render(<AbcXyzView userId="u-1" workspaceId="ws-1" />)

    expect(screen.getByTestId('abc-xyz-loading-skeleton')).toBeDefined()

    await waitFor(() => {
      expect(screen.getByText('Всего позиций в анализе')).toBeDefined()
      expect(screen.getByText('50')).toBeDefined()
      expect(screen.getByText('Класс A (Ключевые)')).toBeDefined()
      expect(screen.getByText('Тормозные колодки Premium')).toBeDefined()
    })
  })
})

describe('InventoryTabsContainer', () => {
  it('renders Overview tab by default', () => {
    currentSearchParamTab = null
    render(<InventoryTabsContainer userId="u-1" workspaceId="ws-1" />)
    expect(screen.getByText('Обзор остатков')).toBeDefined()
    expect(screen.getByText('ABC / XYZ Анализ')).toBeDefined()
  })

  it('renders AbcXyzView when tab=abc-xyz', () => {
    currentSearchParamTab = 'abc-xyz'
    render(<InventoryTabsContainer userId="u-1" workspaceId="ws-1" />)
    expect(screen.getByTestId('abc-xyz-loading-skeleton')).toBeDefined()
  })
})
