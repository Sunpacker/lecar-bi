'use client'

import React from 'react'
import type { SalesTrendPoint } from '../api/sales-gateway'

interface SalesTrendChartProps {
  trend: SalesTrendPoint[]
  selectedDate?: string
  onSelectDate?: (date?: string) => void
}

export function SalesTrendChart({
  trend,
  selectedDate,
  onSelectDate,
}: SalesTrendChartProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  if (trend.length === 0) {
    return (
      <div className="analytics-card" data-testid="sales-trend-chart">
        <h3 className="analytics-card__title">Динамика продаж</h3>
        <p className="empty-text">Нет данных за указанный период</p>
      </div>
    )
  }

  // Downsample or slice points if there are too many for rendering bars/points
  const maxRevenue = Math.max(...trend.map((p) => p.revenue), 1)
  const chartHeight = 160
  const chartWidth = 600

  // Generate SVG polyline points
  const points = trend.map((point, index) => {
    const x = (index / Math.max(trend.length - 1, 1)) * chartWidth
    const y = chartHeight - (point.revenue / maxRevenue) * (chartHeight - 20)
    return { x, y, ...point }
  })

  const pointsString = points.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
  const areaString = `${pointsString} ${chartWidth},${chartHeight} 0,${chartHeight}`

  const startDate = trend[0]?.date ?? ''
  const endDate = trend[trend.length - 1]?.date ?? ''

  return (
    <div className="analytics-card" data-testid="sales-trend-chart">
      <div className="analytics-card__header">
        <h3 className="analytics-card__title">Динамика продаж во времени</h3>
        <div className="flex items-center gap-3">
          {selectedDate && (
            <button
              type="button"
              className="filter-pill-clear"
              onClick={() => onSelectDate?.(undefined)}
              title="Сбросить выбор даты"
            >
              Сбросить дату ({selectedDate})
            </button>
          )}
          <span className="trend-max-label">
            Пик: {currencyFormatter.format(maxRevenue)}
          </span>
        </div>
      </div>

      <div className="svg-chart-container">
        <svg
          viewBox={`0 0 ${chartWidth} ${chartHeight}`}
          className="trend-svg"
          preserveAspectRatio="none"
        >
          <defs>
            <linearGradient id="trendGradient" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" stopColor="#71d6bd" stopOpacity="0.4" />
              <stop offset="100%" stopColor="#71d6bd" stopOpacity="0.0" />
            </linearGradient>
          </defs>

          {/* Area fill */}
          <polygon points={areaString} fill="url(#trendGradient)" />

          {/* Line */}
          <polyline
            fill="none"
            stroke="#71d6bd"
            strokeWidth="2.5"
            strokeLinecap="round"
            strokeLinejoin="round"
            points={pointsString}
          />

          {/* Interactive points */}
          {points.map((p) => {
            const isSelected = selectedDate === p.date
            return (
              <circle
                key={p.date}
                cx={p.x}
                cy={p.y}
                r={isSelected ? 6 : 3.5}
                fill={isSelected ? '#f0f6fc' : '#71d6bd'}
                stroke={isSelected ? '#2ea043' : '#0d1117'}
                strokeWidth={isSelected ? 2.5 : 1}
                className="trend-point"
                style={{ cursor: 'pointer' }}
                onClick={() => onSelectDate?.(isSelected ? undefined : p.date)}
              >
                <title>
                  {p.date}: {currencyFormatter.format(p.revenue)} ({p.order_count} заказов)
                </title>
              </circle>
            )
          })}
        </svg>
      </div>

      <div className="chart-dates-axis">
        <span>{startDate}</span>
        <span>{endDate}</span>
      </div>
    </div>
  )
}
