'use client'

import React from 'react'
import type { SalesCategoryBreakdown } from '../api/sales-gateway'

interface SalesCategoryBreakdownProps {
  categories: SalesCategoryBreakdown[]
  selectedCategoryId?: string
  onSelectCategory?: (categoryId?: string) => void
}

export function SalesCategoryBreakdownView({
  categories,
  selectedCategoryId,
  onSelectCategory,
}: SalesCategoryBreakdownProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  return (
    <div className="analytics-card" data-testid="category-breakdown">
      <div className="analytics-card__header">
        <h3 className="analytics-card__title">Продажи по категориям</h3>
        {selectedCategoryId && (
          <button
            type="button"
            className="filter-pill-clear"
            onClick={() => onSelectCategory?.(undefined)}
            title="Сбросить выбор категории"
          >
            Сбросить фильтр
          </button>
        )}
      </div>
      <div className="breakdown-list">
        {categories.map((cat) => {
          const isSelected = selectedCategoryId === cat.category_id
          const percent = (cat.revenue_share * 100).toFixed(1)
          return (
            <div
              key={cat.category_id}
              role="button"
              tabIndex={0}
              aria-pressed={isSelected}
              className={`breakdown-item breakdown-item--clickable ${isSelected ? 'breakdown-item--selected' : ''}`}
              onClick={() => onSelectCategory?.(isSelected ? undefined : cat.category_id)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault()
                  onSelectCategory?.(isSelected ? undefined : cat.category_id)
                }
              }}
            >
              <div className="breakdown-item__header">
                <span className="breakdown-item__name">
                  {isSelected && <span className="selection-indicator">● </span>}
                  {cat.category_name}
                </span>
                <span className="breakdown-item__value">
                  {currencyFormatter.format(cat.revenue)} ({percent}%)
                </span>
              </div>
              <div className="breakdown-progress">
                <div
                  className={`breakdown-progress__fill ${isSelected ? 'breakdown-progress__fill--active' : ''}`}
                  style={{
                    width: `${Math.min(100, Math.max(0, cat.revenue_share * 100))}%`,
                  }}
                />
              </div>
              <span className="breakdown-item__sub">{cat.order_count} заказов</span>
            </div>
          )
        })}
      </div>
    </div>
  )
}
