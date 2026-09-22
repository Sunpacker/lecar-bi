'use client'

import React from 'react'
import type { SalesSummary } from '../api/sales-gateway'

interface SalesKpiCardsProps {
  summary: SalesSummary
}

export function SalesKpiCards({ summary }: SalesKpiCardsProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  return (
    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 sales-kpi-grid">
      <div className="kpi-card" data-testid="kpi-revenue">
        <span className="kpi-card__label">Выручка</span>
        <span className="kpi-card__value">
          {currencyFormatter.format(summary.total_revenue)}
        </span>
        <span className="kpi-card__subtext">Общий объём продаж</span>
      </div>

      <div className="kpi-card" data-testid="kpi-orders">
        <span className="kpi-card__label">Заказы</span>
        <span className="kpi-card__value">
          {numberFormatter.format(summary.order_count)}
        </span>
        <span className="kpi-card__subtext">Оформленных заказов</span>
      </div>

      <div className="kpi-card" data-testid="kpi-aov">
        <span className="kpi-card__label">Средний чек</span>
        <span className="kpi-card__value">
          {currencyFormatter.format(summary.average_order_value)}
        </span>
        <span className="kpi-card__subtext">Средняя сумма на заказ</span>
      </div>

      <div className="kpi-card" data-testid="kpi-margin">
        <span className="kpi-card__label">Маржинальность</span>
        <span className="kpi-card__value">
          {(summary.margin_rate * 100).toFixed(1)}%
        </span>
        <span className="kpi-card__subtext">
          Прибыль: {currencyFormatter.format(summary.gross_profit)}
        </span>
      </div>
    </div>
  )
}
