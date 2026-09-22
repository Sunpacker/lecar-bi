'use client'

import React from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
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
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card data-testid="kpi-revenue" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Выручка
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground">
            {currencyFormatter.format(summary.total_revenue)}
          </div>
          <CardDescription className="text-xs text-emerald-400 font-medium">
            Общий объём продаж
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-orders" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Заказы
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground">
            {numberFormatter.format(summary.order_count)}
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Оформленных заказов
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-aov" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Средний чек
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground">
            {currencyFormatter.format(summary.average_order_value)}
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Средняя сумма на заказ
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-margin" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Маржинальность
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground">
            {(summary.margin_rate * 100).toFixed(1)}%
          </div>
          <CardDescription className="text-xs text-emerald-400 font-medium">
            Прибыль: {currencyFormatter.format(summary.gross_profit)}
          </CardDescription>
        </CardContent>
      </Card>
    </div>
  )
}
