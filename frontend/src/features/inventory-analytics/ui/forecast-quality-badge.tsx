'use client'

import React from 'react'
import { Badge } from '@/components/ui/badge'
import {
  AlertCircleIcon,
  CheckCircle2Icon,
  ClockIcon,
  InfoIcon,
  ShieldAlertIcon,
} from 'lucide-react'
import type { ForecastQualityMetric, ForecastStatus } from '../api/inventory-gateway'

interface ForecastQualityBadgeProps {
  status: ForecastStatus
  statusReason?: string | null
  modelMethod: string
  modelVersion: string
  qualityMetrics?: ForecastQualityMetric[]
}

const METHOD_LABELS: Record<string, string> = {
  seasonal_naive_dow: 'Сезонный наивный (день недели)',
  moving_average_28d: 'Скользящее среднее (28 дней)',
}

const STATUS_CONFIG: Record<
  ForecastStatus,
  {
    label: string
    variant: 'default' | 'secondary' | 'outline' | 'destructive'
    className: string
    icon: React.ComponentType<{ className?: string }>
    description: string
  }
> = {
  ready: {
    label: 'Готов к использованию',
    variant: 'default',
    className:
      'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30',
    icon: CheckCircle2Icon,
    description: 'Ряд данных полон, модель прошла валидацию на истории (backtest).',
  },
  stale: {
    label: 'Устарел',
    variant: 'secondary',
    className: 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30',
    icon: ClockIcon,
    description:
      'Поступили новые данные продаж или остатков; требуется плановый пересчет.',
  },
  insufficient_data: {
    label: 'Недостаточно данных',
    variant: 'outline',
    className: 'bg-muted text-muted-foreground border-border',
    icon: InfoIcon,
    description:
      'История наблюдений менее минимально необходимой длины для надежного прогноза.',
  },
  limited_by_stockouts: {
    label: 'Искажен дефицитом',
    variant: 'secondary',
    className:
      'bg-orange-500/15 text-orange-700 dark:text-orange-400 border-orange-500/30',
    icon: ShieldAlertIcon,
    description:
      'Более 50% дней товар отсутствовал на складе; фактический спрос цензурирован.',
  },
  failed: {
    label: 'Ошибка расчета',
    variant: 'destructive',
    className: 'bg-destructive/15 text-destructive border-destructive/30',
    icon: AlertCircleIcon,
    description: 'Произошла ошибка при построении модели или валидации.',
  },
}

export function ForecastQualityBadge({
  status,
  statusReason,
  modelMethod,
  modelVersion,
  qualityMetrics = [],
}: ForecastQualityBadgeProps) {
  const config = STATUS_CONFIG[status] ?? STATUS_CONFIG.insufficient_data
  const Icon = config.icon

  const methodName = METHOD_LABELS[modelMethod] ?? modelMethod

  const wapeMetric = qualityMetrics.find((m) => m.metric_name.toLowerCase() === 'wape')
  const maeMetric = qualityMetrics.find((m) => m.metric_name.toLowerCase() === 'mae')
  const coverageMetric = qualityMetrics.find(
    (m) => m.metric_name.toLowerCase() === 'coverage',
  )
  const biasMetric = qualityMetrics.find(
    (m) => m.metric_name.toLowerCase() === 'signed_bias',
  )

  return (
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-lg border border-border bg-card shadow-xs">
      <div className="flex items-start sm:items-center gap-3">
        <Badge
          variant={config.variant}
          className={`flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium border ${config.className}`}
        >
          <Icon className="h-3.5 w-3.5" />
          <span>{config.label}</span>
        </Badge>
        <div>
          <div className="text-xs font-medium text-foreground flex items-center gap-1.5">
            <span>Модель: {methodName}</span>
            <span className="text-muted-foreground font-normal">(v{modelVersion})</span>
          </div>
          <p className="text-[11px] text-muted-foreground mt-0.5">
            {statusReason || config.description}
          </p>
        </div>
      </div>

      {qualityMetrics.length > 0 && status === 'ready' && (
        <div className="flex items-center gap-3 border-t sm:border-t-0 sm:border-l border-border/60 pt-2 sm:pt-0 sm:pl-4 text-xs">
          {wapeMetric && (
            <div className="flex flex-col">
              <span className="text-[10px] uppercase text-muted-foreground tracking-wider">
                WAPE (ошибка)
              </span>
              <span className="font-semibold text-foreground">
                {(wapeMetric.metric_value * 100).toFixed(1)}%
              </span>
            </div>
          )}
          {maeMetric && (
            <div className="flex flex-col">
              <span className="text-[10px] uppercase text-muted-foreground tracking-wider">
                MAE
              </span>
              <span className="font-semibold text-foreground">
                {maeMetric.metric_value.toFixed(1)} шт
              </span>
            </div>
          )}
          {biasMetric && (
            <div className="flex flex-col">
              <span className="text-[10px] uppercase text-muted-foreground tracking-wider">
                Смещение
              </span>
              <span
                className={`font-semibold ${
                  biasMetric.metric_value > 0
                    ? 'text-amber-600 dark:text-amber-400'
                    : 'text-foreground'
                }`}
              >
                {biasMetric.metric_value > 0 ? '+' : ''}
                {biasMetric.metric_value.toFixed(1)}
              </span>
            </div>
          )}
          {coverageMetric && (
            <div className="flex flex-col">
              <span className="text-[10px] uppercase text-muted-foreground tracking-wider">
                Покрытие
              </span>
              <span className="font-semibold text-foreground">
                {(coverageMetric.metric_value * 100).toFixed(0)}%
              </span>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
