'use client'

import React from 'react'
import type { PaginationMetadata, SalesRecordItem } from '../api/sales-gateway'

export interface SalesDetailTableProps {
  items: SalesRecordItem[]
  pagination: PaginationMetadata
  sortBy?: string
  sortDirection?: 'asc' | 'desc'
  loading?: boolean
  onSortChange: (sortBy: string, sortDirection: 'asc' | 'desc') => void
  onPageChange: (page: number) => void
}

const SORTABLE_COLUMNS: { key: string; label: string }[] = [
  { key: 'order_date', label: 'Дата' },
  { key: 'order_number', label: '№ Заказа' },
  { key: 'product_name', label: 'Товар' },
  { key: 'quantity', label: 'Кол-во' },
  { key: 'total_price', label: 'Сумма' },
  { key: 'gross_profit', label: 'Прибыль' },
]

export function SalesDetailTable({
  items,
  pagination,
  sortBy = 'order_date',
  sortDirection = 'desc',
  loading = false,
  onSortChange,
  onPageChange,
}: SalesDetailTableProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const handleHeaderClick = (columnKey: string) => {
    if (sortBy === columnKey) {
      onSortChange(columnKey, sortDirection === 'asc' ? 'desc' : 'asc')
    } else {
      onSortChange(columnKey, 'desc')
    }
  }

  const { page, per_page, total, total_pages } = pagination
  const startRecord = total === 0 ? 0 : (page - 1) * per_page + 1
  const endRecord = Math.min(page * per_page, total)

  return (
    <div className="analytics-card detail-table-card" data-testid="sales-detail-table">
      <div className="analytics-card__header">
        <div>
          <h3 className="analytics-card__title">Детализация продаж</h3>
          <p className="detail-table-subtitle">
            Транзакционные записи по выбранным фильтрам ({total} позиций)
          </p>
        </div>
      </div>

      <div className="table-responsive">
        <table className="detail-table">
          <thead>
            <tr>
              {SORTABLE_COLUMNS.slice(0, 3).map((col) => {
                const isActive = sortBy === col.key
                return (
                  <th
                    key={col.key}
                    aria-sort={
                      isActive
                        ? sortDirection === 'asc'
                          ? 'ascending'
                          : 'descending'
                        : 'none'
                    }
                  >
                    <button
                      type="button"
                      className={`th-sort-button ${isActive ? 'th-sort-button--active' : ''}`}
                      onClick={() => handleHeaderClick(col.key)}
                    >
                      <span>{col.label}</span>
                      <span className="sort-icon">
                        {isActive ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'}
                      </span>
                    </button>
                  </th>
                )
              })}
              <th>Категория</th>
              <th>Регион</th>
              {SORTABLE_COLUMNS.slice(3).map((col) => {
                const isActive = sortBy === col.key
                return (
                  <th
                    key={col.key}
                    className="text-right"
                    aria-sort={
                      isActive
                        ? sortDirection === 'asc'
                          ? 'ascending'
                          : 'descending'
                        : 'none'
                    }
                  >
                    <button
                      type="button"
                      className={`th-sort-button th-sort-button--right ${isActive ? 'th-sort-button--active' : ''}`}
                      onClick={() => handleHeaderClick(col.key)}
                    >
                      <span>{col.label}</span>
                      <span className="sort-icon">
                        {isActive ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'}
                      </span>
                    </button>
                  </th>
                )
              })}
              <th>Статус</th>
            </tr>
          </thead>
          <tbody>
            {items.length === 0 ? (
              <tr>
                <td colSpan={9} className="table-empty-cell">
                  Нет записей по текущим фильтрам
                </td>
              </tr>
            ) : (
              items.map((item) => (
                <tr key={item.id} className="detail-table-row">
                  <td className="text-nowrap">{item.order_date}</td>
                  <td className="font-mono text-sm">{item.order_number}</td>
                  <td>
                    <div className="product-cell">
                      <span className="product-name">{item.product_name}</span>
                      <span className="product-sku">
                        {item.brand_name} · {item.product_sku}
                      </span>
                    </div>
                  </td>
                  <td>
                    <span className="category-tag">{item.category_name}</span>
                  </td>
                  <td>
                    <span className="region-tag">{item.region_name}</span>
                  </td>
                  <td className="text-right font-medium">{item.quantity}</td>
                  <td className="text-right font-medium">
                    {currencyFormatter.format(item.total_price)}
                  </td>
                  <td className="text-right text-profit font-medium">
                    {currencyFormatter.format(item.gross_profit)}
                  </td>
                  <td>
                    <span className={`status-badge status-badge--${item.status}`}>
                      {item.status}
                    </span>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <div className="pagination-toolbar">
        <span className="pagination-info">
          Записи {startRecord} - {endRecord} из {total}
        </span>
        <div className="pagination-actions">
          <button
            type="button"
            className="btn-pagination"
            disabled={page <= 1 || loading}
            onClick={() => onPageChange(page - 1)}
          >
            ← Назад
          </button>
          <span className="pagination-current">
            {page} / {total_pages}
          </span>
          <button
            type="button"
            className="btn-pagination"
            disabled={page >= total_pages || loading}
            onClick={() => onPageChange(page + 1)}
          >
            Вперед →
          </button>
        </div>
      </div>
    </div>
  )
}
