'use client'

import React from 'react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  GripVertical,
  Pencil,
  Trash2,
  ArrowLeft,
  ArrowRight,
  ArrowUp,
  ArrowDown,
  Plus,
  Minus,
} from 'lucide-react'
import type { WidgetDetail } from '../api/dashboard-gateway'
import { WidgetRenderer } from './widgets/widget-renderer'

interface WidgetEditorCardProps {
  widget: WidgetDetail
  userId: string
  workspaceId: string
  onMove: (id: string, direction: 'up' | 'down' | 'left' | 'right') => void
  onResize: (id: string, deltaW: number, deltaH: number) => void
  onEdit: (widget: WidgetDetail) => void
  onDelete: (id: string) => void
  onDragStart: (e: React.DragEvent, id: string) => void
  onDrop: (e: React.DragEvent, targetId: string) => void
}

export function WidgetEditorCard({
  widget,
  userId,
  workspaceId,
  onMove,
  onResize,
  onEdit,
  onDelete,
  onDragStart,
  onDrop,
}: WidgetEditorCardProps) {
  const { x, y, w, h } = widget.position
  const isAtLeftEdge = x === 0
  const isAtRightEdge = x + w >= 12
  const isAtTopEdge = y === 0
  const isAtMaxWidth = x + w >= 12
  const isAtMinWidth = w <= 1
  const isAtMinHeight = h <= 1

  return (
    <div
      draggable
      onDragStart={(e) => onDragStart(e, widget.id)}
      onDragOver={(e) => e.preventDefault()}
      onDrop={(e) => onDrop(e, widget.id)}
      className="group relative flex flex-col h-full w-full rounded-xl border-2 border-dashed border-emerald-500/40 bg-card/60 shadow-sm transition-all hover:border-emerald-500 overflow-hidden"
    >
      {/* Editor Header Toolbar */}
      <div className="flex items-center justify-between gap-1 px-3 py-1.5 bg-muted/40 border-b border-border/60 text-xs">
        <div className="flex items-center gap-1.5 min-w-0">
          <div className="cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground">
            <GripVertical className="size-4" />
          </div>
          <span
            className="font-medium truncate max-w-[120px] sm:max-w-[200px]"
            title={widget.title}
          >
            {widget.title}
          </span>
          <Badge variant="outline" className="text-[10px] px-1 py-0 h-4 uppercase">
            {widget.type.replace('_', ' ')}
          </Badge>
        </div>

        <div className="flex items-center gap-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            aria-label="Настроить виджет"
            onClick={() => onEdit(widget)}
            className="h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
          >
            <Pencil className="size-3" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            aria-label="Удалить виджет"
            onClick={() => onDelete(widget.id)}
            className="h-6 w-6 p-0 text-rose-400 hover:text-rose-500 hover:bg-rose-500/10"
          >
            <Trash2 className="size-3" />
          </Button>
        </div>
      </div>

      {/* Embedded Live Renderer (pointer-events-none prevents interaction interference while in edit mode) */}
      <div className="flex-1 p-2 pointer-events-none select-none opacity-90 overflow-hidden">
        <WidgetRenderer widget={widget} userId={userId} workspaceId={workspaceId} />
      </div>

      {/* Editor Controls Footer: Move & Resize */}
      <div className="flex items-center justify-between gap-2 px-3 py-1 bg-muted/30 border-t border-border/40 text-[11px] text-muted-foreground">
        <div className="flex items-center gap-0.5">
          <span className="text-[10px] mr-1">Позиция:</span>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={isAtLeftEdge}
            aria-label="Сдвинуть влево"
            onClick={() => onMove(widget.id, 'left')}
            className="h-5 w-5 p-0"
          >
            <ArrowLeft className="size-2.5" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={isAtRightEdge}
            aria-label="Сдвинуть вправо"
            onClick={() => onMove(widget.id, 'right')}
            className="h-5 w-5 p-0"
          >
            <ArrowRight className="size-2.5" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={isAtTopEdge}
            aria-label="Сдвинуть вверх"
            onClick={() => onMove(widget.id, 'up')}
            className="h-5 w-5 p-0"
          >
            <ArrowUp className="size-2.5" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            aria-label="Сдвинуть вниз"
            onClick={() => onMove(widget.id, 'down')}
            className="h-5 w-5 p-0"
          >
            <ArrowDown className="size-2.5" />
          </Button>
        </div>

        <div className="flex items-center gap-1.5">
          <div className="flex items-center gap-0.5">
            <span className="text-[10px]">Ш:</span>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={isAtMinWidth}
              aria-label="Уменьшить ширину"
              onClick={() => onResize(widget.id, -1, 0)}
              className="h-5 w-5 p-0"
            >
              <Minus className="size-2.5" />
            </Button>
            <span className="text-[10px] font-mono">{w}</span>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={isAtMaxWidth}
              aria-label="Увеличить ширину"
              onClick={() => onResize(widget.id, 1, 0)}
              className="h-5 w-5 p-0"
            >
              <Plus className="size-2.5" />
            </Button>
          </div>

          <div className="flex items-center gap-0.5">
            <span className="text-[10px]">В:</span>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={isAtMinHeight}
              aria-label="Уменьшить высоту"
              onClick={() => onResize(widget.id, 0, -1)}
              className="h-5 w-5 p-0"
            >
              <Minus className="size-2.5" />
            </Button>
            <span className="text-[10px] font-mono">{h}</span>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              aria-label="Увеличить высоту"
              onClick={() => onResize(widget.id, 0, 1)}
              className="h-5 w-5 p-0"
            >
              <Plus className="size-2.5" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
