'use client'

import React from 'react'
import type { DashboardFilterValues, WidgetDetail } from '../api/dashboard-gateway'
import { WidgetRenderer } from './widgets/widget-renderer'
import { LayoutGrid } from 'lucide-react'

interface DashboardGridProps {
  widgets: WidgetDetail[]
  userId: string
  workspaceId: string
  filters?: DashboardFilterValues | null
}

export function DashboardGrid({
  widgets,
  userId,
  workspaceId,
  filters,
}: DashboardGridProps) {
  if (widgets.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/80 p-12 text-center bg-card/20">
        <div className="flex size-12 items-center justify-center rounded-full bg-muted/60 text-muted-foreground mb-4">
          <LayoutGrid className="size-6" />
        </div>
        <h3 className="text-base font-semibold text-foreground">
          В этом дашборде пока нет виджетов
        </h3>
        <p className="mt-1 text-sm text-muted-foreground max-w-sm">
          Настройте конфигурацию виджетов или перейдите в режим редактирования для
          добавления метрик.
        </p>
      </div>
    )
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-12 gap-4 auto-rows-[90px]">
      {widgets.map((widget) => {
        const { x, y, w, h } = widget.position
        const colSpan = Math.min(12 - x, Math.max(1, w))
        const rowSpan = Math.max(1, h)

        return (
          <div
            key={widget.id}
            style={{
              gridColumn: `span ${colSpan} / span ${colSpan}`,
              gridRow: `span ${rowSpan} / span ${rowSpan}`,
            }}
            className="min-h-[140px]"
          >
            <div className="h-full w-full">
              <WidgetRenderer
                widget={widget}
                userId={userId}
                workspaceId={workspaceId}
                filters={filters}
              />
            </div>
          </div>
        )
      })}
    </div>
  )
}
