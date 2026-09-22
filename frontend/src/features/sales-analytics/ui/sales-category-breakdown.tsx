'use client'

import React from 'react'
import type { SalesCategoryBreakdown } from '../api/sales-gateway'

interface SalesCategoryBreakdownProps {
  categories: SalesCategoryBreakdown[]
}

export function SalesCategoryBreakdownView({
  categories,
}: SalesCategoryBreakdownProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  return (
    <div className="analytics-card" data-testid="category-breakdown">
      <h3 className="analytics-card__title">Продажи по категориям</h3>
      <div className="breakdown-list">
        {categories.map((cat) => {
          const percent = (cat.revenue_share * 100).toFixed(1)
          return (
            <div key={cat.category_id} className="breakdown-item">
              <div className="breakdown-item__header">
                <span className="breakdown-item__name">{cat.category_name}</span>
                <span className="breakdown-item__value">
                  {currencyFormatter.format(cat.revenue)} ({percent}%)
                </span>
              </div>
              <div className="breakdown-progress">
                <div
                  className="breakdown-progress__fill"
                  style={{ width: `${Math.min(100, Math.max(0, cat.revenue_share * 100))}%` }}
                />
              </div>
              <span className="breakdown-item__sub">
                {cat.order_count} заказов
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}
