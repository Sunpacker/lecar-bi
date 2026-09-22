'use client'

import React from 'react'
import type { SalesRegionBreakdown } from '../api/sales-gateway'

interface SalesRegionalBreakdownProps {
  regions: SalesRegionBreakdown[]
  selectedRegionId?: string
  onSelectRegion?: (regionId?: string) => void
}

export function SalesRegionalBreakdownView({
  regions,
  selectedRegionId,
  onSelectRegion,
}: SalesRegionalBreakdownProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  return (
    <div className="analytics-card" data-testid="regional-breakdown">
      <div className="analytics-card__header">
        <h3 className="analytics-card__title">Региональное распределение</h3>
        {selectedRegionId && (
          <button
            type="button"
            className="filter-pill-clear"
            onClick={() => onSelectRegion?.(undefined)}
            title="Сбросить выбор региона"
          >
            Сбросить фильтр
          </button>
        )}
      </div>
      <div className="breakdown-list">
        {regions.map((reg) => {
          const isSelected = selectedRegionId === reg.region_id
          const percent = (reg.revenue_share * 100).toFixed(1)
          return (
            <div
              key={reg.region_id}
              role="button"
              tabIndex={0}
              aria-pressed={isSelected}
              className={`breakdown-item breakdown-item--clickable ${isSelected ? 'breakdown-item--selected' : ''}`}
              onClick={() => onSelectRegion?.(isSelected ? undefined : reg.region_id)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault()
                  onSelectRegion?.(isSelected ? undefined : reg.region_id)
                }
              }}
            >
              <div className="breakdown-item__header">
                <div className="flex items-center gap-2">
                  <span className="region-badge">{reg.region_code}</span>
                  <span className="breakdown-item__name">
                    {isSelected && <span className="selection-indicator">● </span>}
                    {reg.region_name}
                  </span>
                </div>
                <span className="breakdown-item__value">
                  {currencyFormatter.format(reg.revenue)} ({percent}%)
                </span>
              </div>
              <div className="breakdown-progress">
                <div
                  className={`breakdown-progress__fill breakdown-progress__fill--accent ${isSelected ? 'breakdown-progress__fill--active' : ''}`}
                  style={{
                    width: `${Math.min(100, Math.max(0, reg.revenue_share * 100))}%`,
                  }}
                />
              </div>
              <span className="breakdown-item__sub">{reg.order_count} заказов</span>
            </div>
          )
        })}
      </div>
    </div>
  )
}
