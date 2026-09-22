'use client'

import React from 'react'
import {
  ResponsiveContainer,
  LineChart,
  Line,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { WidgetChartPoint } from '../../model/widget-data-loader'

interface WidgetLineChartProps {
  title: string
  data: WidgetChartPoint[]
}

export function WidgetLineChart({ title, data }: WidgetLineChartProps) {
  return (
    <Card className="h-full flex flex-col border-border bg-card/60 shadow-xs">
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-foreground">{title}</CardTitle>
      </CardHeader>
      <CardContent className="flex-1 min-h-[180px] p-2 sm:p-4">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={data} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
            <CartesianGrid strokeDasharray="3 3" className="stroke-border/40" />
            <XAxis
              dataKey="name"
              stroke="currentColor"
              className="text-xs text-muted-foreground"
            />
            <YAxis stroke="currentColor" className="text-xs text-muted-foreground" />
            <Tooltip
              contentStyle={{
                backgroundColor: 'var(--popover)',
                borderColor: 'var(--border)',
                borderRadius: '0.5rem',
                color: 'var(--popover-foreground)',
              }}
            />
            <Line
              type="monotone"
              dataKey="value"
              stroke="#10b981"
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4 }}
            />
          </LineChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  )
}
