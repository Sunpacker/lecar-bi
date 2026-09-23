'use client'

import React from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import type { AlertSummaryResponse } from '../api/alerts-gateway'
import { AlertCircle, AlertTriangle, CheckCircle2, BellRing } from 'lucide-react'

interface AlertSummaryCardsProps {
  summary: AlertSummaryResponse | null
  isLoading?: boolean
}

export function AlertSummaryCards({ summary, isLoading }: AlertSummaryCardsProps) {
  const numberFormatter = new Intl.NumberFormat('ru-RU')

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card data-testid="kpi-total-active" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1 flex flex-row items-center justify-between space-y-0">
          <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
            Активные алерты
          </CardTitle>
          <BellRing className="h-4 w-4 text-primary" />
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-foreground">
            {isLoading ? '...' : numberFormatter.format(summary?.total_active ?? 0)}
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Требующие внимания команды
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-critical" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1 flex flex-row items-center justify-between space-y-0">
          <CardTitle className="text-xs uppercase text-rose-500 font-medium tracking-wider">
            Критический дефицит
          </CardTitle>
          <AlertCircle className="h-4 w-4 text-rose-500" />
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-rose-500">
            {isLoading ? '...' : numberFormatter.format(summary?.critical_count ?? 0)}
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Аут-оф-сток и DOS &lt; порога
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-warning" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1 flex flex-row items-center justify-between space-y-0">
          <CardTitle className="text-xs uppercase text-amber-500 font-medium tracking-wider">
            Предупреждения
          </CardTitle>
          <AlertTriangle className="h-4 w-4 text-amber-500" />
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-amber-500">
            {isLoading ? '...' : numberFormatter.format(summary?.warning_count ?? 0)}
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Избыточный запас и приближение к порогу
          </CardDescription>
        </CardContent>
      </Card>

      <Card data-testid="kpi-acknowledged" className="border-border bg-card shadow-xs">
        <CardHeader className="pb-1 flex flex-row items-center justify-between space-y-0">
          <CardTitle className="text-xs uppercase text-blue-500 font-medium tracking-wider">
            В работе
          </CardTitle>
          <CheckCircle2 className="h-4 w-4 text-blue-500" />
        </CardHeader>
        <CardContent className="space-y-1">
          <div className="text-2xl font-bold tracking-tight text-blue-500">
            {isLoading ? '...' : numberFormatter.format(summary?.acknowledged_count ?? 0)}
          </div>
          <CardDescription className="text-xs text-muted-foreground">
            Подтверждены ответственными
          </CardDescription>
        </CardContent>
      </Card>
    </div>
  )
}
