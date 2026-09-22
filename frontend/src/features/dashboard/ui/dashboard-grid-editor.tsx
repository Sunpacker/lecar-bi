'use client'

import React from 'react'
import { LayoutGrid, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { WidgetDetail } from '../api/dashboard-gateway'
import { WidgetEditorCard } from './widget-editor-card'

interface DashboardGridEditorProps {
  widgets: WidgetDetail[]
  userId: string
  workspaceId: string
  onMove: (id: string, direction: 'up' | 'down' | 'left' | 'right') => void
  onResize: (id: string, deltaW: number, deltaH: number) => void
  onReposition: (id: string, targetX: number, targetY: number) => void
  onEdit: (widget: WidgetDetail) => void
  onDelete: (id: string) => void
  onAddWidget: () => void
}

export function DashboardGridEditor({
  widgets,
  userId,
  workspaceId,
  onMove,
  onResize,
  onReposition,
  onEdit,
  onDelete,
  onAddWidget,
}: DashboardGridEditorProps) {
  const handleDragStart = (e: React.DragEvent, id: string) => {
    e.dataTransfer.setData('text/plain', id)
    e.dataTransfer.effectAllowed = 'move'
  }

  const handleDrop = (e: React.DragEvent, targetId: string) => {
    e.preventDefault()
    const draggedId = e.dataTransfer.getData('text/plain')
    if (!draggedId || draggedId === targetId) return

    const dragged = widgets.find((w) => w.id === draggedId)
    const target = widgets.find((w) => w.id === targetId)
    if (!dragged || !target) return

    // Swap positions with target widget
    onReposition(dragged.id, target.position.x, target.position.y)
    onReposition(target.id, dragged.position.x, dragged.position.y)
  }

  if (widgets.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-border/80 p-12 text-center bg-card/20">
        <div className="flex size-12 items-center justify-center rounded-full bg-muted/60 text-muted-foreground mb-4">
          <LayoutGrid className="size-6" />
        </div>
        <h3 className="text-base font-semibold text-foreground">Сетка пуста</h3>
        <p className="mt-1 text-sm text-muted-foreground max-w-sm">
          В этом дашборде пока нет виджетов. Добавьте первый виджет, чтобы настроить
          аналитическую панель.
        </p>
        <Button
          type="button"
          onClick={onAddWidget}
          className="mt-4 gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs"
        >
          <Plus className="size-3.5" />
          <span>Добавить первый виджет</span>
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-12 gap-4 auto-rows-[110px]">
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
              className="min-h-[160px]"
            >
              <WidgetEditorCard
                widget={widget}
                userId={userId}
                workspaceId={workspaceId}
                onMove={onMove}
                onResize={onResize}
                onEdit={onEdit}
                onDelete={onDelete}
                onDragStart={handleDragStart}
                onDrop={handleDrop}
              />
            </div>
          )
        })}
      </div>
    </div>
  )
}
