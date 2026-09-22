import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import React from 'react'
import { SalesDetailTable } from './sales-detail-table'
import type { SalesRecordItem } from '../api/sales-gateway'

describe('SalesDetailTable', () => {
  const mockItems: SalesRecordItem[] = [
    {
      id: 'item-1',
      order_id: 'ord-1',
      order_number: 'ORD-2001',
      order_date: '2026-01-15',
      product_id: 'prod-1',
      product_name: 'Тормозной диск вентилируемый',
      product_sku: 'DSK-001',
      category_id: 'cat-1',
      category_name: 'Тормозная система',
      region_id: 'reg-1',
      region_name: 'Москва',
      brand_name: 'Brembo',
      quantity: 2,
      unit_price: 4500,
      total_price: 9000,
      gross_profit: 3200,
      status: 'completed',
    },
  ]

  const mockPagination = {
    page: 2,
    per_page: 10,
    total: 25,
    total_pages: 3,
  }

  it('renders sales detail records with formatted values', () => {
    render(
      <SalesDetailTable
        items={mockItems}
        pagination={mockPagination}
        sortBy="order_date"
        sortDirection="desc"
        onSortChange={vi.fn()}
        onPageChange={vi.fn()}
      />,
    )

    expect(screen.getByText('ORD-2001')).toBeDefined()
    expect(screen.getByText('Тормозной диск вентилируемый')).toBeDefined()
    expect(screen.getByText(/DSK-001/)).toBeDefined()
    expect(screen.getByText('Тормозная система')).toBeDefined()
    expect(screen.getByText('Москва')).toBeDefined()
    expect(screen.getByText(/9\s*000/)).toBeDefined()
  })

  it('toggles sort direction when clicking the active sort column header', () => {
    const onSortChange = vi.fn()
    render(
      <SalesDetailTable
        items={mockItems}
        pagination={mockPagination}
        sortBy="total_price"
        sortDirection="desc"
        onSortChange={onSortChange}
        onPageChange={vi.fn()}
      />,
    )

    const header = screen.getByRole('button', { name: /Сумма/i })
    fireEvent.click(header)

    expect(onSortChange).toHaveBeenCalledWith('total_price', 'asc')
  })

  it('changes sort column when clicking another sortable header', () => {
    const onSortChange = vi.fn()
    render(
      <SalesDetailTable
        items={mockItems}
        pagination={mockPagination}
        sortBy="order_date"
        sortDirection="desc"
        onSortChange={onSortChange}
        onPageChange={vi.fn()}
      />,
    )

    const header = screen.getByRole('button', { name: /Выручка|Сумма/i })
    fireEvent.click(header)

    expect(onSortChange).toHaveBeenCalledWith('total_price', 'desc')
  })

  it('triggers onPageChange when clicking next and previous buttons', () => {
    const onPageChange = vi.fn()
    render(
      <SalesDetailTable
        items={mockItems}
        pagination={mockPagination}
        sortBy="order_date"
        sortDirection="desc"
        onSortChange={vi.fn()}
        onPageChange={onPageChange}
      />,
    )

    const prevButton = screen.getByRole('button', { name: /Назад/i })
    const nextButton = screen.getByRole('button', { name: /Вперед/i })

    fireEvent.click(prevButton)
    expect(onPageChange).toHaveBeenCalledWith(1)

    fireEvent.click(nextButton)
    expect(onPageChange).toHaveBeenCalledWith(3)
  })
})
