'use client'

import React, { useEffect, useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { AlertCircle } from 'lucide-react'
import type { WidgetDetail } from '../../api/dashboard-gateway'
import { loadWidgetData, type WidgetDataResult } from '../../model/widget-data-loader'
import { WidgetKpiCard } from './widget-kpi-card'
import { WidgetLineChart } from './widget-line-chart'
import { WidgetBarChart } from './widget-bar-chart'
import { WidgetDonutChart } from './widget-donut-chart'
import { WidgetTable } from './widget-table'

interface WidgetRendererProps {
  widget: WidgetDetail
  userId: string
  workspaceId: string
}

export function WidgetRenderer({ widget, userId, workspaceId }: WidgetRendererProps) {
  const [dataResult, setDataResult] = useState<WidgetDataResult>({ loading: true })

  useEffect(() => {
    let isCancelled = false
    setDataResult({ loading: true })

    loadWidgetData(widget, userId, workspaceId).then((res) => {
      if (!isCancelled) {
        setDataResult(res)
      }
    })

    return () => {
      isCancelled = true
    }
  }, [widget, userId, workspaceId])

  if (dataResult.loading) {
    return (
      <Card
        data-testid="widget-loading-skeleton"
        className="h-full flex flex-col justify-between border-border bg-card/40 p-4"
      >
        <CardHeader className="p-0 pb-2">
          <CardTitle className="text-xs text-muted-foreground">{widget.title}</CardTitle>
        </CardHeader>
        <CardContent className="p-0 flex-1 flex flex-col justify-center gap-2">
          <Skeleton className="h-8 w-2/3 rounded-md" />
          <Skeleton className="h-4 w-1/3 rounded-md" />
        </CardContent>
      </Card>
    )
  }

  if (dataResult.error) {
    return (
      <Card className="h-full flex flex-col items-center justify-center p-4 border-rose-500/30 bg-rose-500/5 text-center">
        <AlertCircle className="size-6 text-rose-500 mb-2" />
        <p className="text-xs font-medium text-rose-400">{widget.title}</p>
        <p className="text-[11px] text-muted-foreground mt-1">{dataResult.error}</p>
      </Card>
    )
  }

  switch (widget.type) {
    case 'kpi_card':
      return (
        <WidgetKpiCard
          title={widget.title}
          value={dataResult.kpi?.formatted ?? '0'}
          subtitle={dataResult.kpi?.subtitle}
        />
      )
    case 'line_chart':
      return <WidgetLineChart title={widget.title} data={dataResult.chartData ?? []} />
    case 'bar_chart':
      return <WidgetBarChart title={widget.title} data={dataResult.chartData ?? []} />
    case 'donut_chart':
      return <WidgetDonutChart title={widget.title} data={dataResult.chartData ?? []} />
    case 'table':
      return (
        <WidgetTable
          title={widget.title}
          data={dataResult.tableData ?? { columns: [], rows: [] }}
        />
      )
    default:
      return null
  }
}
