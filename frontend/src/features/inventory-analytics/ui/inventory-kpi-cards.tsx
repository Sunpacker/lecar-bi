'use client'

import React from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import type { InventorySummary } from '../api/inventory-gateway'

interface InventoryKpiCardsProps {
  summary: InventorySummary
}

export function InventoryKpiCards({ summary }: InventoryKpiCardsProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card data-testid="kpi-stock-value" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Стоимость остатков
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground">
            {currencyFormatter.format(summary.total_inventory_value)}
          </div>
          <CardDescription className="text-xs text-muted-foreground font-medium">
            Общая себестоимость склада
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-units-available" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Доступно шт.
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground">
            {numberFormatter.format(summary.total_quantity_available)}
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Физический остаток: {numberFormatter.format(summary.total_quantity_on_hand)}{' '}
            шт.
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-critical" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1">
          <CardTitle className="text-xs uppercase text-rose-400 font-medium tracking-wider">
            Критические запасы
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-rose-500">
            {numberFormatter.format(summary.critical_count)} поз.
          </div>
          <CardDescription className="text-xs text-rose-400/80">
            Дефицит: {summary.out_of_stock_count} | DOS &le; 7 дн.
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-overstock" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1">
          <CardTitle className="text-xs uppercase text-amber-400 font-medium tracking-wider">
            Избыточный запас
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-amber-500">
            {numberFormatter.format(summary.overstock_count)} поз.
          </div>
          <CardDescription className="text-xs text-amber-400/80">
            DOS &gt; 60 дн. или замороженный капитал
          </CardDescription>
        </CardContent>
      </Card>
    </div>
  )
}
