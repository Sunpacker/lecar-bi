'use client'

import React from 'react'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  AlertTriangleIcon,
  CalendarIcon,
  PackageCheckIcon,
  TruckIcon,
  ShieldCheckIcon,
} from 'lucide-react'
import type { ForecastStockRisk } from '../api/inventory-gateway'

interface ForecastStockRiskCardProps {
  stockRisk?: ForecastStockRisk | null
  asOfDate: string
}

export function ForecastStockRiskCard({
  stockRisk,
  asOfDate,
}: ForecastStockRiskCardProps) {
  if (!stockRisk) {
    return (
      <Card className="border-border bg-card shadow-xs">
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-semibold text-foreground">
            Оценка рисков запасов
          </CardTitle>
          <CardDescription className="text-xs text-muted-foreground">
            Данные по остаткам недоступны для выбранного товара.
          </CardDescription>
        </CardHeader>
      </Card>
    )
  }

  const {
    current_quantity_available,
    current_safety_stock,
    current_reorder_point,
    estimated_depletion_date,
    estimated_reorder_threshold_date,
    estimated_order_placement_date,
    median_lead_time_days,
    lead_time_source,
  } = stockRisk

  const isDepleted = Boolean(
    estimated_depletion_date && estimated_depletion_date <= asOfDate,
  )
  const willDeplete = Boolean(estimated_depletion_date)
  const needOrderNow = Boolean(
    estimated_order_placement_date && estimated_order_placement_date <= asOfDate,
  )

  return (
    <Card className="border-border bg-card shadow-xs">
      <CardHeader className="pb-3 flex flex-row items-center justify-between gap-2">
        <div>
          <CardTitle className="text-sm font-semibold text-foreground flex items-center gap-2">
            <PackageCheckIcon className="h-4 w-4 text-primary" />
            <span>Оценка рисков запасов и сроков заказа</span>
          </CardTitle>
          <CardDescription className="text-xs text-muted-foreground mt-0.5">
            Моделирование убыли доступного остатка на основе прогноза продаж
          </CardDescription>
        </div>
        <div>
          {isDepleted ? (
            <Badge variant="destructive" className="flex items-center gap-1 text-xs">
              <AlertTriangleIcon className="h-3.5 w-3.5" />
              Дефицит (остаток ≤ 0)
            </Badge>
          ) : needOrderNow ? (
            <Badge className="bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30 flex items-center gap-1 text-xs">
              <AlertTriangleIcon className="h-3.5 w-3.5" />
              Заказать сейчас!
            </Badge>
          ) : willDeplete ? (
            <Badge variant="secondary" className="text-xs">
              Исчерпание в горизонте
            </Badge>
          ) : (
            <Badge
              variant="outline"
              className="text-xs text-emerald-600 dark:text-emerald-400 border-emerald-500/30"
            >
              <ShieldCheckIcon className="h-3.5 w-3.5 mr-1" />
              Запас стабилен
            </Badge>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Metric tiles */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div className="p-2.5 rounded-lg bg-muted/40 border border-border/50">
            <span className="text-[11px] text-muted-foreground block">
              Доступный остаток
            </span>
            <span className="text-base font-bold text-foreground">
              {current_quantity_available.toLocaleString('ru-RU')} шт
            </span>
          </div>

          <div className="p-2.5 rounded-lg bg-muted/40 border border-border/50">
            <span className="text-[11px] text-muted-foreground block">
              Точка заказа (ROP)
            </span>
            <span className="text-base font-bold text-foreground">
              {current_reorder_point.toLocaleString('ru-RU')} шт
            </span>
          </div>

          <div className="p-2.5 rounded-lg bg-muted/40 border border-border/50">
            <span className="text-[11px] text-muted-foreground block">
              Страховой запас
            </span>
            <span className="text-base font-bold text-foreground">
              {current_safety_stock.toLocaleString('ru-RU')} шт
            </span>
          </div>

          <div className="p-2.5 rounded-lg bg-muted/40 border border-border/50">
            <span className="text-[11px] text-muted-foreground block">
              Срок поставки (Lead Time)
            </span>
            <span className="text-base font-bold text-foreground flex items-center gap-1">
              <TruckIcon className="h-3.5 w-3.5 text-muted-foreground" />
              {median_lead_time_days !== null && median_lead_time_days !== undefined
                ? `${median_lead_time_days} дн`
                : 'Не определен'}
            </span>
            {lead_time_source && (
              <span className="text-[10px] text-muted-foreground truncate block">
                {lead_time_source}
              </span>
            )}
          </div>
        </div>

        {/* Timeline breakdown */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1 border-t border-border/60">
          <div className="flex items-start gap-2.5">
            <CalendarIcon className="h-4 w-4 text-muted-foreground mt-0.5" />
            <div>
              <span className="text-xs text-muted-foreground block">
                Дата достижения точки заказа:
              </span>
              <span className="text-xs font-semibold text-foreground">
                {estimated_reorder_threshold_date ?? 'Не достигается в горизонте'}
              </span>
            </div>
          </div>

          <div className="flex items-start gap-2.5">
            <CalendarIcon className="h-4 w-4 text-amber-500 mt-0.5" />
            <div>
              <span className="text-xs text-muted-foreground block">
                Рекомендуемый срок размещения заказа:
              </span>
              <span
                className={`text-xs font-semibold ${
                  needOrderNow
                    ? 'text-amber-600 dark:text-amber-400 underline decoration-amber-500'
                    : 'text-foreground'
                }`}
              >
                {needOrderNow
                  ? 'Заказать сейчас (срок наступил)'
                  : (estimated_order_placement_date ?? 'Срок не определен')}
              </span>
            </div>
          </div>

          <div className="flex items-start gap-2.5">
            <CalendarIcon className="h-4 w-4 text-destructive mt-0.5" />
            <div>
              <span className="text-xs text-muted-foreground block">
                Ориентировочная дата исчерпания:
              </span>
              <span
                className={`text-xs font-semibold ${
                  willDeplete ? 'text-destructive' : 'text-foreground'
                }`}
              >
                {estimated_depletion_date ?? 'Не исчерпается в горизонте'}
              </span>
            </div>
          </div>
        </div>

        {/* Mandatory business disclaimer */}
        <div className="p-2.5 rounded-md bg-muted/30 border border-border/40 text-[11px] text-muted-foreground">
          <strong className="font-medium text-foreground">Допущение расчета:</strong>{' '}
          Сценарий исчерпания рассчитан исключительно на основе текущего свободного
          остатка и прогнозного спроса.{' '}
          <span className="underline">
            Будущие неподтвержденные поступления не учитываются
          </span>
          . Рекомендация срока заказа носит информационный характер и не заменяет
          регламент закупок.
        </div>
      </CardContent>
    </Card>
  )
}
