'use client'

import React, { useState } from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
  ResponsiveContainer,
  ComposedChart,
  Bar,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
} from 'recharts'
import type { SupplierTrendPoint } from '../api/supplier-gateway'

interface SupplierTrendsChartProps {
  trends: SupplierTrendPoint[]
}

export function SupplierTrendsChart({ trends }: SupplierTrendsChartProps) {
  const [metricMode, setMetricMode] = useState<'rates' | 'spend'>('rates')

  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
    notation: 'compact',
  })

  return (
    <Card className="border-border bg-card shadow-xs">
      <CardHeader className="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-2 gap-2">
        <div>
          <CardTitle className="text-base font-semibold text-foreground">
            Динамика поставок и надежности
          </CardTitle>
          <CardDescription className="text-xs text-muted-foreground">
            Ежемесячный объем заказов, процент соблюдения сроков и полнота поставок
          </CardDescription>
        </div>
        <div className="flex items-center gap-1.5 self-start sm:self-auto">
          <Button
            variant={metricMode === 'rates' ? 'default' : 'outline'}
            size="sm"
            className="h-7 text-xs px-2.5"
            onClick={() => setMetricMode('rates')}
          >
            OTD & Fill Rate
          </Button>
          <Button
            variant={metricMode === 'spend' ? 'default' : 'outline'}
            size="sm"
            className="h-7 text-xs px-2.5"
            onClick={() => setMetricMode('spend')}
          >
            Сумма закупок
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        {trends.length === 0 ? (
          <div className="h-64 flex items-center justify-center text-xs text-muted-foreground">
            Нет данных за выбранный период
          </div>
        ) : (
          <div className="h-72 w-full pt-2">
            <ResponsiveContainer width="100%" height="100%">
              <ComposedChart
                data={trends}
                margin={{ top: 10, right: 10, left: -20, bottom: 0 }}
              >
                <CartesianGrid strokeDasharray="3 3" className="stroke-border/40" />
                <XAxis
                  dataKey="period"
                  className="text-[11px] text-muted-foreground"
                  tickLine={false}
                  axisLine={false}
                />
                <YAxis
                  yAxisId="left"
                  className="text-[11px] text-muted-foreground"
                  tickLine={false}
                  axisLine={false}
                  tickFormatter={(val) =>
                    metricMode === 'spend' ? currencyFormatter.format(val) : `${val}`
                  }
                />
                {metricMode === 'rates' && (
                  <YAxis
                    yAxisId="right"
                    orientation="right"
                    domain={[0, 100]}
                    className="text-[11px] text-muted-foreground"
                    tickLine={false}
                    axisLine={false}
                    tickFormatter={(val) => `${val}%`}
                  />
                )}
                <Tooltip
                  content={({ active, payload, label }) => {
                    if (!active || !payload?.length) return null
                    return (
                      <div className="rounded-lg border border-border bg-popover p-2.5 shadow-md text-xs space-y-1.5">
                        <div className="font-semibold text-foreground">{label}</div>
                        {payload.map((item) => (
                          <div
                            key={item.name}
                            className="flex items-center justify-between gap-4"
                          >
                            <span className="text-muted-foreground flex items-center gap-1.5">
                              <span
                                className="h-2 w-2 rounded-full"
                                style={{ backgroundColor: item.color }}
                              />
                              {item.name}:
                            </span>
                            <span className="font-medium text-foreground">
                              {item.name === 'Сумма закупок'
                                ? new Intl.NumberFormat('ru-RU', {
                                    style: 'currency',
                                    currency: 'RUB',
                                    maximumFractionDigits: 0,
                                  }).format(Number(item.value))
                                : typeof item.value === 'number' &&
                                    typeof item.name === 'string' &&
                                    item.name.includes('%')
                                  ? `${item.value}%`
                                  : item.value}
                            </span>
                          </div>
                        ))}
                      </div>
                    )
                  }}
                />
                <Legend
                  wrapperStyle={{ paddingTop: 8, fontSize: 12 }}
                  formatter={(val) => (
                    <span className="text-muted-foreground">{val}</span>
                  )}
                />
                <Bar
                  yAxisId="left"
                  dataKey="deliveries_count"
                  name="Всего поставок"
                  fill="#3b82f6"
                  radius={[4, 4, 0, 0]}
                  barSize={24}
                />
                {metricMode === 'rates' ? (
                  <>
                    <Line
                      yAxisId="right"
                      type="monotone"
                      dataKey="on_time_rate"
                      name="В срок (OTD %)"
                      stroke="#10b981"
                      strokeWidth={2.5}
                      dot={{ r: 3, fill: '#10b981' }}
                    />
                    <Line
                      yAxisId="right"
                      type="monotone"
                      dataKey="fulfillment_rate"
                      name="Полнота (Fill %)"
                      stroke="#06b6d4"
                      strokeWidth={2}
                      dot={{ r: 3, fill: '#06b6d4' }}
                    />
                  </>
                ) : (
                  <Line
                    yAxisId="left"
                    type="monotone"
                    dataKey="total_spend"
                    name="Сумма закупок"
                    stroke="#10b981"
                    strokeWidth={2.5}
                    dot={{ r: 3, fill: '#10b981' }}
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
