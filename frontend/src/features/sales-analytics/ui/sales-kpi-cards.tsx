'use client'

import { MetricCard } from '@/src/shared/ui'
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
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <MetricCard
        title="Выручка"
        value={currencyFormatter.format(summary.total_revenue)}
        description={
          <span className="font-medium text-emerald-400">Общий объём продаж</span>
        }
        data-testid="kpi-revenue"
      />
      <MetricCard
        title="Заказы"
        value={numberFormatter.format(summary.order_count)}
        description="Оформленных заказов"
        data-testid="kpi-orders"
      />
      <MetricCard
        title="Средний чек"
        value={currencyFormatter.format(summary.average_order_value)}
        description="Средняя сумма на заказ"
        data-testid="kpi-aov"
      />
      <MetricCard
        title="Маржинальность"
        value={`${(summary.margin_rate * 100).toFixed(1)}%`}
        description={
          <span className="font-medium text-emerald-400">
            Прибыль: {currencyFormatter.format(summary.gross_profit)}
          </span>
        }
        data-testid="kpi-margin"
      />
    </div>
  )
}
