'use client'

import React from 'react'
import { ResponsiveContainer, PieChart, Pie, Cell, Tooltip, Legend } from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { WidgetChartPoint } from '../../model/widget-data-loader'

interface WidgetDonutChartProps {
  title: string
  data: WidgetChartPoint[]
}

const COLORS = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ec4899', '#6366f1']

export function WidgetDonutChart({ title, data }: WidgetDonutChartProps) {
  return (
    <Card className="h-full flex flex-col border-border bg-card/60 shadow-xs">
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-foreground">{title}</CardTitle>
      </CardHeader>
      <CardContent className="flex-1 min-h-[180px] p-2">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Tooltip
              contentStyle={{
                backgroundColor: 'var(--popover)',
                borderColor: 'var(--border)',
                borderRadius: '0.5rem',
                color: 'var(--popover-foreground)',
              }}
            />
            <Legend wrapperStyle={{ fontSize: '11px' }} />
            <Pie
              data={data}
              innerRadius={45}
              outerRadius={70}
              paddingAngle={2}
              dataKey="value"
            >
              {data.map((_, index) => (
                <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
              ))}
            </Pie>
          </PieChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  )
}
