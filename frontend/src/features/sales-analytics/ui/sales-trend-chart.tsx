'use client'

import React from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from '@/components/ui/chart'
import { XIcon } from 'lucide-react'
import {
  CartesianGrid,
  Line,
  LineChart,
  XAxis,
  YAxis,
  type DotItemDotProps,
} from 'recharts'
import type { SalesTrendPoint } from '../api/sales-gateway'

interface SalesTrendChartProps {
  trend: SalesTrendPoint[]
  selectedDate?: string
  onSelectDate?: (date?: string) => void
}

interface SalesTrendDotProps extends DotItemDotProps {
  payload: SalesTrendPoint
  selectedDate?: string
  onSelectDate?: (date?: string) => void
}

const CURRENCY_FORMATTER = new Intl.NumberFormat('ru-RU', {
  style: 'currency',
  currency: 'RUB',
  maximumFractionDigits: 0,
})

const COMPACT_NUMBER_FORMATTER = new Intl.NumberFormat('ru-RU', {
  notation: 'compact',
  maximumFractionDigits: 1,
})

const DATE_FORMATTER = new Intl.DateTimeFormat('ru-RU', {
  day: '2-digit',
  month: 'short',
})

const CHART_CONFIG = {
  revenue: {
    label: 'Выручка',
    color: 'var(--color-emerald-400)',
  },
} satisfies ChartConfig

export function SalesTrendChart({
  trend,
  selectedDate,
  onSelectDate,
}: SalesTrendChartProps) {
  if (trend.length === 0) return <EmptySalesTrendChart />

  const maxRevenue = Math.max(...trend.map((point) => point.revenue))

  function renderDot(dotProps: DotItemDotProps) {
    return (
      <SalesTrendDot
        {...dotProps}
        payload={dotProps.payload as SalesTrendPoint}
        selectedDate={selectedDate}
        onSelectDate={onSelectDate}
      />
    )
  }

  return (
    <Card className="border-border bg-card shadow-xs" data-testid="sales-trend-chart">
      <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
        <CardTitle className="text-base font-semibold text-foreground">
          Динамика продаж во времени
        </CardTitle>
        <div className="flex shrink-0 items-center gap-3">
          {selectedDate && (
            <Button
              type="button"
              variant="outline"
              size="xs"
              onClick={() => onSelectDate?.(undefined)}
              title="Сбросить выбор даты"
              className="gap-1 rounded-full border-emerald-700/40 bg-emerald-950/20 text-xs text-emerald-400 hover:bg-emerald-900/40 hover:text-emerald-300"
            >
              <XIcon className="size-3" />
              Сбросить дату ({selectedDate})
            </Button>
          )}
          <span className="text-xs font-medium text-emerald-400">
            Пик: {CURRENCY_FORMATTER.format(maxRevenue)}
          </span>
        </div>
      </CardHeader>

      <CardContent>
        <ChartContainer
          config={CHART_CONFIG}
          className="h-[260px] w-full min-w-0 aspect-auto"
        >
          <LineChart
            accessibilityLayer
            data={trend}
            margin={{ top: 12, right: 12, left: 0, bottom: 0 }}
          >
            <CartesianGrid vertical={false} strokeDasharray="3 3" />
            <XAxis
              dataKey="date"
              axisLine={false}
              tickLine={false}
              tickMargin={10}
              minTickGap={28}
              tickFormatter={formatChartDate}
            />
            <YAxis
              axisLine={false}
              tickLine={false}
              tickMargin={8}
              width={58}
              tickFormatter={formatCompactRevenue}
            />
            <ChartTooltip
              cursor={{ stroke: 'var(--border)', strokeDasharray: '4 4' }}
              content={
                <ChartTooltipContent
                  indicator="line"
                  labelFormatter={formatTooltipDate}
                  formatter={formatTooltipValue}
                />
              }
            />
            <Line
              dataKey="revenue"
              type="monotone"
              stroke="var(--color-revenue)"
              strokeWidth={2.5}
              dot={renderDot}
              activeDot={{ r: 6, fill: 'var(--color-revenue)', strokeWidth: 0 }}
            />
          </LineChart>
        </ChartContainer>
      </CardContent>
    </Card>
  )
}

function EmptySalesTrendChart() {
  return (
    <Card className="border-border bg-card" data-testid="sales-trend-chart">
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-semibold text-foreground">
          Динамика продаж
        </CardTitle>
      </CardHeader>
      <CardContent>
        <p className="text-sm text-muted-foreground">Нет данных за указанный период</p>
      </CardContent>
    </Card>
  )
}

function SalesTrendDot({
  cx = 0,
  cy = 0,
  payload,
  selectedDate,
  onSelectDate,
}: SalesTrendDotProps) {
  const isSelected = selectedDate === payload.date
  const radius = isSelected ? 6 : 4

  function selectDate() {
    onSelectDate?.(isSelected ? undefined : payload.date)
  }

  function handleKeyDown(event: React.KeyboardEvent<SVGCircleElement>) {
    if (event.key !== 'Enter' && event.key !== ' ') return

    event.preventDefault()
    selectDate()
  }

  return (
    <circle
      cx={cx}
      cy={cy}
      r={radius}
      role="button"
      tabIndex={0}
      aria-label={`${payload.date}: ${CURRENCY_FORMATTER.format(payload.revenue)}, ${payload.order_count} заказов`}
      aria-pressed={isSelected}
      fill={isSelected ? 'var(--background)' : 'var(--color-revenue)'}
      stroke="var(--color-revenue)"
      strokeWidth={isSelected ? 3 : 1.5}
      className="cursor-pointer transition-[r] focus:outline-hidden focus-visible:stroke-[3]"
      onClick={selectDate}
      onKeyDown={handleKeyDown}
    />
  )
}

function formatChartDate(date: string) {
  const parsedDate = new Date(`${date}T00:00:00`)
  if (Number.isNaN(parsedDate.getTime())) return date

  return DATE_FORMATTER.format(parsedDate)
}

function formatCompactRevenue(value: number) {
  return `${COMPACT_NUMBER_FORMATTER.format(value)} ₽`
}

function formatTooltipDate(_: React.ReactNode, payload: readonly unknown[]) {
  const point = getTooltipPoint(payload)
  return point ? formatChartDate(point.date) : ''
}

function formatTooltipValue(value: unknown, _name: unknown, item: { payload?: unknown }) {
  const point = isSalesTrendPoint(item.payload) ? item.payload : undefined
  const revenue = typeof value === 'number' ? value : Number(value)

  return (
    <div className="grid min-w-40 grid-cols-[1fr_auto] gap-x-4 gap-y-1">
      <span className="text-muted-foreground">Выручка</span>
      <span className="font-mono font-medium tabular-nums text-foreground">
        {CURRENCY_FORMATTER.format(revenue)}
      </span>
      {point && (
        <>
          <span className="text-muted-foreground">Заказы</span>
          <span className="font-mono font-medium tabular-nums text-foreground">
            {point.order_count}
          </span>
        </>
      )}
    </div>
  )
}

function getTooltipPoint(payload: readonly unknown[]) {
  const [item] = payload
  if (!item || typeof item !== 'object' || !('payload' in item)) return undefined

  return isSalesTrendPoint(item.payload) ? item.payload : undefined
}

function isSalesTrendPoint(value: unknown): value is SalesTrendPoint {
  if (!value || typeof value !== 'object') return false

  return (
    'date' in value &&
    typeof value.date === 'string' &&
    'revenue' in value &&
    typeof value.revenue === 'number' &&
    'order_count' in value &&
    typeof value.order_count === 'number'
  )
}
