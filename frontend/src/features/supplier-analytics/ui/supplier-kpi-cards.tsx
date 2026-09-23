'use client'

import React from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import type { SupplierSummary } from '../api/supplier-gateway'
import {
  DollarSign,
  Truck,
  CheckCircle2,
  PackageCheck,
  Clock,
  AlertTriangle,
} from 'lucide-react'

interface SupplierKpiCardsProps {
  summary: SupplierSummary
}

export function SupplierKpiCards({ summary }: SupplierKpiCardsProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
      <Card data-testid="kpi-total-spend" className="border-border bg-card shadow-xs">
        <CardHeader className="flex flex-row items-center justify-between pb-1 space-y-0">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Объем закупок
          </CardTitle>
          <DollarSign className="h-4 w-4 text-emerald-500" />
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-xl font-bold tracking-tight text-foreground truncate">
            {currencyFormatter.format(summary.total_spend)}
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            {numberFormatter.format(summary.total_received_quantity)} шт. принято
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-total-deliveries" className="border-border bg-card shadow-xs">
        <CardHeader className="flex flex-row items-center justify-between pb-1 space-y-0">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Всего поставок
          </CardTitle>
          <Truck className="h-4 w-4 text-blue-500" />
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-xl font-bold tracking-tight text-foreground">
            {numberFormatter.format(summary.total_deliveries)}
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Заказано: {numberFormatter.format(summary.total_ordered_quantity)} шт.
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-on-time-rate" className="border-border bg-card shadow-xs">
        <CardHeader className="flex flex-row items-center justify-between pb-1 space-y-0">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            В срок (OTD)
          </CardTitle>
          <CheckCircle2 className="h-4 w-4 text-emerald-500" />
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
            {summary.on_time_rate}%
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            {summary.on_time_deliveries} из {summary.total_deliveries} заказов
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-fulfillment-rate" className="border-border bg-card shadow-xs">
        <CardHeader className="flex flex-row items-center justify-between pb-1 space-y-0">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Полнота (Fill Rate)
          </CardTitle>
          <PackageCheck className="h-4 w-4 text-teal-500" />
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-xl font-bold tracking-tight text-teal-600 dark:text-teal-400">
            {summary.fulfillment_rate}%
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Частичных: {summary.partial_deliveries}
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-lead-time" className="border-border bg-card shadow-xs">
        <CardHeader className="flex flex-row items-center justify-between pb-1 space-y-0">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Средний срок
          </CardTitle>
          <Clock className="h-4 w-4 text-indigo-500" />
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-xl font-bold tracking-tight text-foreground">
            {summary.average_lead_time_days} дн.
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Ср. задержка: {summary.average_delay_days} дн.
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-defect-rate" className="border-border bg-card shadow-xs">
        <CardHeader className="flex flex-row items-center justify-between pb-1 space-y-0">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Брак и возвраты
          </CardTitle>
          <AlertTriangle className="h-4 w-4 text-amber-500" />
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-xl font-bold tracking-tight text-amber-600 dark:text-amber-400">
            {summary.defect_rate}%
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Брак: {numberFormatter.format(summary.total_defect_quantity)} шт.
          </CardDescription>
        </CardContent>
      </Card>
    </div>
  )
}
