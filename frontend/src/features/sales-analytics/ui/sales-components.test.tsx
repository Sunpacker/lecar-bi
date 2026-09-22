import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import React from 'react'
import { SalesKpiCards } from './sales-kpi-cards'
import { SalesCategoryBreakdownView } from './sales-category-breakdown'
import { SalesRegionalBreakdownView } from './sales-regional-breakdown'
import { SalesFiltersBar } from './sales-filters-bar'
import { SalesTrendChart } from './sales-trend-chart'

describe('Sales Analytics UI Components', () => {
  it('renders KPI cards with correct metric values', () => {
    render(
      <SalesKpiCards
        summary={{
          total_revenue: 1500000,
          order_count: 250,
          average_order_value: 6000,
          gross_profit: 450000,
          margin_rate: 0.3,
        }}
      />,
    )

    expect(screen.getByText('Выручка')).toBeDefined()
    expect(screen.getByText('Заказы')).toBeDefined()
    expect(screen.getByText('Средний чек')).toBeDefined()
    expect(screen.getByText('Маржинальность')).toBeDefined()
    expect(screen.getByText('250')).toBeDefined()
    expect(screen.getByText('30.0%')).toBeDefined()
  })

  it('renders category breakdown items and percentage bars', () => {
    render(
      <SalesCategoryBreakdownView
        categories={[
          {
            category_id: 'cat-1',
            category_name: 'Аккумуляторы',
            revenue: 300000,
            order_count: 50,
            revenue_share: 0.2,
          },
        ]}
      />,
    )

    expect(screen.getByText('Аккумуляторы')).toBeDefined()
    expect(screen.getByText(/20\.0%/)).toBeDefined()
  })

  it('renders regional breakdown items', () => {
    render(
      <SalesRegionalBreakdownView
        regions={[
          {
            region_id: 'reg-1',
            region_name: 'Москва',
            region_code: 'MSK',
            revenue: 750000,
            order_count: 100,
            revenue_share: 0.5,
          },
        ]}
      />,
    )

    expect(screen.getByText('Москва')).toBeDefined()
    expect(screen.getByText('MSK')).toBeDefined()
    expect(screen.getByText(/50\.0%/)).toBeDefined()
  })

  it('triggers filter changes on user selection', () => {
    const onFilterChange = vi.fn()
    render(
      <SalesFiltersBar
        filterOptions={{
          categories: [{ id: 'cat-1', name: 'Масла' }],
          regions: [{ id: 'reg-1', name: 'Москва', code: 'MSK' }],
          min_date: '2025-01-01',
          max_date: '2025-12-31',
        }}
        activeFilters={{}}
        onFilterChange={onFilterChange}
      />,
    )

    const categorySelect = screen.getByLabelText('Категория')
    fireEvent.change(categorySelect, { target: { value: 'cat-1' } })

    expect(onFilterChange).toHaveBeenCalledWith(
      expect.objectContaining({ categoryId: 'cat-1' }),
    )
  })

  it('triggers onSelectCategory on category click and deselects if clicked again', () => {
    const onSelectCategory = vi.fn()
    const { rerender } = render(
      <SalesCategoryBreakdownView
        categories={[
          {
            category_id: 'cat-1',
            category_name: 'Аккумуляторы',
            revenue: 300000,
            order_count: 50,
            revenue_share: 0.2,
          },
        ]}
        selectedCategoryId={undefined}
        onSelectCategory={onSelectCategory}
      />,
    )

    const item = screen.getByRole('button', { name: /Аккумуляторы/i })
    fireEvent.click(item)
    expect(onSelectCategory).toHaveBeenCalledWith('cat-1')

    // Rerender with selected state
    rerender(
      <SalesCategoryBreakdownView
        categories={[
          {
            category_id: 'cat-1',
            category_name: 'Аккумуляторы',
            revenue: 300000,
            order_count: 50,
            revenue_share: 0.2,
          },
        ]}
        selectedCategoryId="cat-1"
        onSelectCategory={onSelectCategory}
      />,
    )

    fireEvent.click(item)
    expect(onSelectCategory).toHaveBeenCalledWith(undefined)
  })

  it('triggers onSelectRegion on region click', () => {
    const onSelectRegion = vi.fn()
    render(
      <SalesRegionalBreakdownView
        regions={[
          {
            region_id: 'reg-1',
            region_name: 'Москва',
            region_code: 'MSK',
            revenue: 750000,
            order_count: 100,
            revenue_share: 0.5,
          },
        ]}
        onSelectRegion={onSelectRegion}
      />,
    )

    const item = screen.getByRole('button', { name: /Москва/i })
    fireEvent.click(item)
    expect(onSelectRegion).toHaveBeenCalledWith('reg-1')
  })

  it('renders the sales line chart and selects a date from a data point', () => {
    const onSelectDate = vi.fn()

    render(
      <SalesTrendChart
        trend={[
          { date: '2025-01-01', revenue: 100000, order_count: 10 },
          { date: '2025-01-02', revenue: 150000, order_count: 12 },
        ]}
        onSelectDate={onSelectDate}
      />,
    )

    expect(screen.getByText('Динамика продаж во времени')).toBeDefined()
    expect(screen.getByText(/Пик:/)).toHaveTextContent('150 000')

    fireEvent.click(screen.getByRole('button', { name: /2025-01-01/ }))

    expect(onSelectDate).toHaveBeenCalledWith('2025-01-01')
  })

  it('limits the sales trend to the selected period', () => {
    render(
      <SalesTrendChart
        trend={[
          { date: '2025-03-05', revenue: 100000, order_count: 10 },
          { date: '2025-03-25', revenue: 140000, order_count: 11 },
          { date: '2025-03-31', revenue: 180000, order_count: 12 },
        ]}
      />,
    )

    expect(screen.getByRole('button', { name: /2025-03-05/ })).toBeDefined()

    fireEvent.click(screen.getByRole('button', { name: 'Показать 7 дней' }))

    expect(screen.queryByRole('button', { name: /2025-03-05/ })).toBeNull()
    expect(screen.getByRole('button', { name: /2025-03-25/ })).toBeDefined()
    expect(screen.getByRole('button', { name: /2025-03-31/ })).toBeDefined()
    expect(screen.getByText(/Пик:/)).toHaveTextContent('180 000')
  })

  it('renders an empty state when the sales trend has no points', () => {
    render(<SalesTrendChart trend={[]} />)

    expect(screen.getByText('Нет данных за указанный период')).toBeDefined()
  })
})
