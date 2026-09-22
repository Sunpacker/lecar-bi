'use client'

import React from 'react'
import type { SalesRegionBreakdown } from '../api/sales-gateway'

interface SalesRegionalBreakdownProps {
  regions: SalesRegionBreakdown[]
}

export function SalesRegionalBreakdownView({
  regions,
}: SalesRegionalBreakdownProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  return (
    <div className="analytics-card" data-testid="regional-breakdown">
      <h3 className="analytics-card__title">Региональное распределение</h3>
      <div className="breakdown-list">
        {regions.map((reg) => {
          const percent = (reg.revenue_share * 100).toFixed(1)
          return (
            <div key={reg.region_id} className="breakdown-item">
              <div className="breakdown-item__header">
                <div className="flex items-center gap-2">
                  <span className="region-badge">{reg.region_code}</span>
                  <span className="breakdown-item__name">{reg.region_name}</span>
                </div>
                <span className="breakdown-item__value">
                  {currencyFormatter.format(reg.revenue)} ({percent}%)
                </span>
              </div>
              <div className="breakdown-progress">
                <div
                  className="breakdown-progress__fill breakdown-progress__fill--accent"
                  style={{ width: `${Math.min(100, Math.max(0, reg.revenue_share * 100))}%` }}
                />
              </div>
              <span className="breakdown-item__sub">
                {reg.order_count} заказов
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}
