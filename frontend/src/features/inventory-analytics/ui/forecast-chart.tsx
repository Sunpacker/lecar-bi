'use client'

import React from 'react'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card'
import {
  ResponsiveContainer,
  ComposedChart,
  Line,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ReferenceLine,
} from 'recharts'
import type { ForecastPoint } from '../api/inventory-gateway'

interface ForecastChartProps {
  history: ForecastPoint[]
  forecast: ForecastPoint[]
  asOfDate: string
  productName: string
  horizonDays: number
}

interface ChartDataPoint {
  date: string
  historical_sales?: number | null
  forecast_estimate?: number | null
  lower_bound?: number | null
  upper_bound?: number | null
  interval_range?: [number, number] | null
  actual_sales?: number | null
  is_stockout?: boolean
}

export function ForecastChart({
  history,
  forecast,
  asOfDate,
  productName,
  horizonDays,
}: ForecastChartProps) {
  // Combine historical points and forecast points into a single timeline
  const chartData: ChartDataPoint[] = []

  // Add history points
  for (const point of history) {
    chartData.push({
      date: point.date,
      historical_sales: point.point_estimate,
      is_stockout: point.is_stockout_day,
    })
  }

  // Check if we need a bridge point at asOfDate for visual continuity
  const lastHistoryPoint = history[history.length - 1]
  const hasHistoryAsOf = history.some((p) => p.date === asOfDate)
  if (lastHistoryPoint && !hasHistoryAsOf && forecast.length > 0) {
    // Add bridge point at asOfDate
    chartData.push({
      date: asOfDate,
      historical_sales: lastHistoryPoint.point_estimate,
      forecast_estimate: lastHistoryPoint.point_estimate,
      lower_bound: lastHistoryPoint.point_estimate,
      upper_bound: lastHistoryPoint.point_estimate,
      interval_range: [lastHistoryPoint.point_estimate, lastHistoryPoint.point_estimate],
    })
  }

  // Add forecast points
  for (const point of forecast) {
    const hasInterval =
      point.lower_bound !== null &&
      point.lower_bound !== undefined &&
      point.upper_bound !== null &&
      point.upper_bound !== undefined

    chartData.push({
      date: point.date,
      forecast_estimate: point.point_estimate,
      lower_bound: point.lower_bound ?? null,
      upper_bound: point.upper_bound ?? null,
      interval_range: hasInterval ? [point.lower_bound!, point.upper_bound!] : null,
      actual_sales: point.actual_value ?? null,
      is_stockout: point.is_stockout_day,
    })
  }

  const hasIntervals = forecast.some(
    (p) => p.lower_bound !== null && p.lower_bound !== undefined,
  )
  const hasActualsInHorizon = forecast.some(
    (p) => p.actual_value !== null && p.actual_value !== undefined,
  )

  return (
    <Card className="border-border bg-card shadow-xs">
      <CardHeader className="pb-2">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <CardTitle className="text-sm font-semibold text-foreground">
              График динамики продаж и прогноз спроса: {productName}
            </CardTitle>
            <CardDescription className="text-xs text-muted-foreground mt-0.5">
              Сплошная линия — факт продаж; пунктир — прогноз на {horizonDays} дн.;
              заливка — доверительный интервал (80%)
            </CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        {chartData.length === 0 ? (
          <div className="h-72 flex items-center justify-center text-xs text-muted-foreground">
            Нет данных для построения графика прогноза
          </div>
        ) : (
          <div className="h-80 w-full pt-2">
            <ResponsiveContainer width="100%" height="100%">
              <ComposedChart
                data={chartData}
                margin={{ top: 15, right: 15, left: -20, bottom: 0 }}
              >
                <CartesianGrid strokeDasharray="3 3" className="stroke-border/40" />
                <XAxis
                  dataKey="date"
                  className="text-[11px] text-muted-foreground"
                  tickLine={false}
                  axisLine={false}
                  tickFormatter={(val: string) => {
                    const parts = val.split('-')
                    if (parts.length === 3) {
                      return `${parts[2]}.${parts[1]}`
                    }
                    return val
                  }}
                />
                <YAxis
                  className="text-[11px] text-muted-foreground"
                  tickLine={false}
                  axisLine={false}
                  tickFormatter={(val: number) => `${Math.round(val)}`}
                />
                <Tooltip
                  content={({ active, payload, label }) => {
                    if (!active || !payload || payload.length === 0) return null
                    const item = payload[0]?.payload as ChartDataPoint | undefined
                    return (
                      <div className="rounded-lg border border-border bg-popover p-3 text-xs shadow-md space-y-1.5 min-w-[200px]">
                        <div className="font-semibold text-foreground border-b border-border/50 pb-1 flex justify-between items-center">
                          <span>{label}</span>
                          {item?.is_stockout && (
                            <span className="text-[10px] text-amber-500 font-medium">
                              Дефицит товара
                            </span>
                          )}
                        </div>
                        {item?.historical_sales !== undefined &&
                          item?.historical_sales !== null && (
                            <div className="flex justify-between items-center text-blue-600 dark:text-blue-400">
                              <span>Факт продаж:</span>
                              <span className="font-semibold">
                                {item.historical_sales} шт
                              </span>
                            </div>
                          )}
                        {item?.forecast_estimate !== undefined &&
                          item?.forecast_estimate !== null && (
                            <div className="flex justify-between items-center text-emerald-600 dark:text-emerald-400">
                              <span>Прогноз спроса:</span>
                              <span className="font-semibold">
                                {item.forecast_estimate} шт
                              </span>
                            </div>
                          )}
                        {item?.lower_bound !== null &&
                          item?.upper_bound !== null &&
                          item?.lower_bound !== undefined &&
                          item?.upper_bound !== undefined && (
                            <div className="flex justify-between items-center text-muted-foreground text-[11px]">
                              <span>Интервал (80%):</span>
                              <span>
                                [{item.lower_bound} ... {item.upper_bound}]
                              </span>
                            </div>
                          )}
                        {item?.actual_sales !== undefined &&
                          item?.actual_sales !== null && (
                            <div className="flex justify-between items-center text-amber-600 dark:text-amber-400">
                              <span>Фактические продажи:</span>
                              <span className="font-semibold">
                                {item.actual_sales} шт
                              </span>
                            </div>
                          )}
                      </div>
                    )
                  }}
                />
                <Legend
                  wrapperStyle={{ paddingTop: '10px', fontSize: '11px' }}
                  iconSize={10}
                />

                <ReferenceLine
                  x={asOfDate}
                  stroke="#94a3b8"
                  strokeDasharray="4 4"
                  strokeWidth={1.5}
                  label={{
                    value: 'Граница данных (as-of)',
                    position: 'top',
                    fill: '#64748b',
                    fontSize: 10,
                  }}
                />

                {/* Shaded confidence interval band */}
                {hasIntervals && (
                  <Area
                    dataKey="interval_range"
                    name="Доверительный интервал (80%)"
                    fill="#10b981"
                    fillOpacity={0.15}
                    stroke="none"
                  />
                )}

                {/* Historical measured sales */}
                <Line
                  type="monotone"
                  dataKey="historical_sales"
                  name="Факт продаж"
                  stroke="#3b82f6"
                  strokeWidth={2}
                  dot={{ r: 2, fill: '#3b82f6' }}
                  activeDot={{ r: 4 }}
                  isAnimationActive={false}
                />

                {/* Forecast point estimate */}
                <Line
                  type="monotone"
                  dataKey="forecast_estimate"
                  name="Прогноз спроса"
                  stroke="#10b981"
                  strokeWidth={2}
                  strokeDasharray="5 5"
                  dot={{ r: 3, fill: '#10b981' }}
                  activeDot={{ r: 5 }}
                  isAnimationActive={false}
                />

                {/* Actual sales in forecast period if backtested */}
                {hasActualsInHorizon && (
                  <Line
                    type="monotone"
                    dataKey="actual_sales"
                    name="Фактический спрос (сравнение)"
                    stroke="#f59e0b"
                    strokeWidth={2}
                    dot={{ r: 3, fill: '#f59e0b' }}
                    activeDot={{ r: 5 }}
                    isAnimationActive={false}
                  />
                )}
              </ComposedChart>
            </ResponsiveContainer>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
