'use client'

import React from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import type { DeliveryStatusBreakdownItem } from '../api/supplier-gateway'
import { CheckCircle2, Clock, AlertCircle } from 'lucide-react'

interface SupplierStatusBreakdownProps {
  breakdown: DeliveryStatusBreakdownItem[]
  totalDeliveries: number
}

export function SupplierStatusBreakdown({
  breakdown,
  totalDeliveries,
}: SupplierStatusBreakdownProps) {
  const statusMeta: Record<
    string,
    {
      label: string
      color: string
      badgeClass: string
      progressClass: string
      icon: React.ComponentType<{ className?: string }>
    }
  > = {
    on_time: {
      label: 'В срок',
      color: 'text-emerald-500',
      badgeClass:
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
      progressClass: 'bg-emerald-500',
      icon: CheckCircle2,
    },
    delayed: {
      label: 'С задержкой',
      color: 'text-amber-500',
      badgeClass:
        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
      progressClass: 'bg-amber-500',
      icon: Clock,
    },
    partial: {
      label: 'Частично',
      color: 'text-blue-500',
      badgeClass:
        'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
      progressClass: 'bg-blue-500',
      icon: AlertCircle,
    },
  }

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  return (
    <Card className="border-border bg-card shadow-xs">
      <CardHeader className="pb-3">
        <CardTitle className="text-base font-semibold text-foreground">
          Распределение по статусам поставок
        </CardTitle>
        <CardDescription className="text-xs text-muted-foreground">
          Анализ дисциплины поставок по категориям выполнения
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {breakdown.map((item) => {
          const meta = statusMeta[item.status] ?? {
            label: item.status,
            color: 'text-muted-foreground',
            badgeClass: 'bg-muted text-muted-foreground',
            progressClass: 'bg-muted-foreground',
            icon: AlertCircle,
          }
          const Icon = meta.icon

          return (
            <div key={item.status} className="space-y-1.5">
              <div className="flex items-center justify-between text-xs">
                <div className="flex items-center gap-1.5">
                  <Icon className={`h-3.5 w-3.5 ${meta.color}`} />
                  <span className="font-medium text-foreground">
                    {meta.label}
                  </span>
                  <Badge variant="outline" className={`text-[10px] px-1.5 py-0 ${meta.badgeClass}`}>
                    {item.count} заказов ({item.share_percentage}%)
                  </Badge>
                </div>
                <span className="text-muted-foreground font-mono">
                  {numberFormatter.format(item.quantity)} шт.
                </span>
              </div>
              <Progress
                value={item.share_percentage}
                className="h-2 bg-muted"
              />
            </div>
          )
        })}

        {breakdown.length === 0 && (
          <div className="py-6 text-center text-xs text-muted-foreground">
            Нет данных о статусах поставок
          </div>
        )}
      </CardContent>
    </Card>
  )
}
