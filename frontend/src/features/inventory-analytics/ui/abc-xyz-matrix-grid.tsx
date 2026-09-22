'use client'

import React from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { CheckIcon } from 'lucide-react'
import type { AbcXyzMatrixCell } from '../api/inventory-gateway'

interface AbcXyzMatrixGridProps {
  matrix: AbcXyzMatrixCell[]
  selectedGroup: string | null
  onSelectGroup: (group: string | null) => void
}

const ABC_CLASSES = ['A', 'B', 'C'] as const
const XYZ_CLASSES = ['X', 'Y', 'Z'] as const

const GROUP_STYLES: Record<
  string,
  {
    border: string
    bg: string
    hoverBg: string
    selectedBg: string
    badgeClass: string
    tag: string
  }
> = {
  AX: {
    border: 'border-emerald-500/30',
    bg: 'bg-emerald-950/20',
    hoverBg: 'hover:bg-emerald-900/30 hover:border-emerald-500/50',
    selectedBg: 'ring-2 ring-emerald-400 bg-emerald-950/40 border-emerald-400',
    badgeClass: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
    tag: 'Ключевые стабильные',
  },
  AY: {
    border: 'border-teal-500/30',
    bg: 'bg-teal-950/20',
    hoverBg: 'hover:bg-teal-900/30 hover:border-teal-500/50',
    selectedBg: 'ring-2 ring-teal-400 bg-teal-950/40 border-teal-400',
    badgeClass: 'bg-teal-500/20 text-teal-300 border-teal-500/30',
    tag: 'Ключевые сезонные',
  },
  AZ: {
    border: 'border-amber-500/30',
    bg: 'bg-amber-950/20',
    hoverBg: 'hover:bg-amber-900/30 hover:border-amber-500/50',
    selectedBg: 'ring-2 ring-amber-400 bg-amber-950/40 border-amber-400',
    badgeClass: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
    tag: 'Ключевые редкие',
  },
  BX: {
    border: 'border-cyan-500/30',
    bg: 'bg-cyan-950/20',
    hoverBg: 'hover:bg-cyan-900/30 hover:border-cyan-500/50',
    selectedBg: 'ring-2 ring-cyan-400 bg-cyan-950/40 border-cyan-400',
    badgeClass: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30',
    tag: 'Средние стабильные',
  },
  BY: {
    border: 'border-blue-500/30',
    bg: 'bg-blue-950/20',
    hoverBg: 'hover:bg-blue-900/30 hover:border-blue-500/50',
    selectedBg: 'ring-2 ring-blue-400 bg-blue-950/40 border-blue-400',
    badgeClass: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
    tag: 'Средние сезонные',
  },
  BZ: {
    border: 'border-orange-500/30',
    bg: 'bg-orange-950/20',
    hoverBg: 'hover:bg-orange-900/30 hover:border-orange-500/50',
    selectedBg: 'ring-2 ring-orange-400 bg-orange-950/40 border-orange-400',
    badgeClass: 'bg-orange-500/20 text-orange-300 border-orange-500/30',
    tag: 'Средние нерегулярные',
  },
  CX: {
    border: 'border-slate-500/30',
    bg: 'bg-slate-900/20',
    hoverBg: 'hover:bg-slate-800/30 hover:border-slate-500/50',
    selectedBg: 'ring-2 ring-slate-400 bg-slate-800/40 border-slate-400',
    badgeClass: 'bg-slate-500/20 text-slate-300 border-slate-500/30',
    tag: 'Низкие стабильные',
  },
  CY: {
    border: 'border-zinc-500/30',
    bg: 'bg-zinc-900/20',
    hoverBg: 'hover:bg-zinc-800/30 hover:border-zinc-500/50',
    selectedBg: 'ring-2 ring-zinc-400 bg-zinc-800/40 border-zinc-400',
    badgeClass: 'bg-zinc-500/20 text-zinc-300 border-zinc-500/30',
    tag: 'Низкие сезонные',
  },
  CZ: {
    border: 'border-rose-500/30',
    bg: 'bg-rose-950/20',
    hoverBg: 'hover:bg-rose-900/30 hover:border-rose-500/50',
    selectedBg: 'ring-2 ring-rose-400 bg-rose-950/40 border-rose-400',
    badgeClass: 'bg-rose-500/20 text-rose-300 border-rose-500/30',
    tag: 'Низкие неликвиды',
  },
}

export function AbcXyzMatrixGrid({
  matrix,
  selectedGroup,
  onSelectGroup,
}: AbcXyzMatrixGridProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  const matrixMap = React.useMemo(() => {
    const map = new Map<string, AbcXyzMatrixCell>()
    for (const cell of matrix) {
      map.set(cell.code, cell)
    }
    return map
  }, [matrix])

  return (
    <Card className="border-border/60 bg-card/60 backdrop-blur-xs">
      <CardHeader className="pb-3">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <CardTitle className="text-lg font-semibold flex items-center gap-2">
              Матрица сегментации ассортимента (ABC / XYZ)
            </CardTitle>
            <CardDescription className="text-xs text-muted-foreground mt-1">
              Нажмите на ячейку матрицы для быстрой фильтрации каталога товаров по
              соответствующей группе
            </CardDescription>
          </div>
          {selectedGroup && (
            <Badge
              variant="outline"
              className="cursor-pointer self-start sm:self-auto border-primary text-primary hover:bg-primary/10"
              onClick={() => onSelectGroup(null)}
            >
              Сбросить выбор ({selectedGroup}) &times;
            </Badge>
          )}
        </div>
      </CardHeader>
      <CardContent>
        <div className="overflow-x-auto">
          <div className="min-w-[720px]">
            {/* Header X columns */}
            <div className="grid grid-cols-[140px_1fr_1fr_1fr] gap-2.5 mb-2.5 text-center text-xs font-semibold text-muted-foreground">
              <div className="flex items-center justify-center p-2 text-[11px] text-muted-foreground/70 uppercase tracking-wider">
                Выручка \ Спрос
              </div>
              <div className="p-2 bg-muted/40 rounded-lg border border-border/40">
                <span className="text-foreground font-medium">Класс X</span>
                <span className="block text-[11px] text-muted-foreground font-normal">
                  CV &le; 15% &bull; Высокая точность
                </span>
              </div>
              <div className="p-2 bg-muted/40 rounded-lg border border-border/40">
                <span className="text-foreground font-medium">Класс Y</span>
                <span className="block text-[11px] text-muted-foreground font-normal">
                  15% &lt; CV &le; 35% &bull; Колебания спроса
                </span>
              </div>
              <div className="p-2 bg-muted/40 rounded-lg border border-border/40">
                <span className="text-foreground font-medium">Класс Z</span>
                <span className="block text-[11px] text-muted-foreground font-normal">
                  CV &gt; 35% &bull; Нерегулярный спрос
                </span>
              </div>
            </div>

            {/* Matrix Rows */}
            {ABC_CLASSES.map((abc) => (
              <div
                key={abc}
                className="grid grid-cols-[140px_1fr_1fr_1fr] gap-2.5 mb-2.5 items-stretch"
              >
                {/* Row Header */}
                <div className="p-3 bg-muted/40 rounded-lg border border-border/40 flex flex-col justify-center">
                  <span className="font-semibold text-sm text-foreground">
                    Класс {abc}
                  </span>
                  <span className="text-[11px] text-muted-foreground mt-0.5">
                    {abc === 'A' && 'Высокая (до 80% выручки)'}
                    {abc === 'B' && 'Средняя (следующие 15%)'}
                    {abc === 'C' && 'Низкая (последние 5%)'}
                  </span>
                </div>

                {/* Matrix Cells */}
                {XYZ_CLASSES.map((xyz) => {
                  const groupKey = `${abc}${xyz}`
                  const cell = matrixMap.get(groupKey)
                  const isSelected = selectedGroup === groupKey
                  const style = GROUP_STYLES[groupKey] || {
                    border: 'border-border',
                    bg: 'bg-card',
                    hoverBg: 'hover:bg-accent',
                    selectedBg: 'ring-2 ring-primary',
                    badgeClass: 'bg-muted text-foreground',
                    tag: groupKey,
                  }

                  const count = cell?.count ?? 0
                  const countSharePct = (cell?.count_share ?? 0) * 100
                  const revenue = cell?.revenue ?? 0
                  const revenueSharePct = (cell?.revenue_share ?? 0) * 100
                  const inventoryValue = cell?.inventory_value ?? 0

                  return (
                    <button
                      key={groupKey}
                      type="button"
                      onClick={() => onSelectGroup(isSelected ? null : groupKey)}
                      aria-label={`Сегмент ${groupKey}: ${count} товаров`}
                      className={`relative flex flex-col justify-between p-3.5 rounded-xl border text-left transition-all duration-150 cursor-pointer ${
                        style.border
                      } ${style.bg} ${style.hoverBg} ${
                        isSelected ? style.selectedBg : ''
                      }`}
                    >
                      {/* Top row in cell */}
                      <div className="flex items-center justify-between mb-2">
                        <Badge
                          variant="outline"
                          className={`font-mono text-xs px-2 py-0.5 font-bold ${style.badgeClass}`}
                        >
                          {groupKey}
                        </Badge>
                        <span className="text-[11px] text-muted-foreground font-medium">
                          {style.tag}
                        </span>
                        {isSelected && (
                          <div className="absolute top-2 right-2 bg-primary text-primary-foreground rounded-full p-0.5">
                            <CheckIcon className="h-3 w-3" />
                          </div>
                        )}
                      </div>

                      {/* Numbers */}
                      <div className="space-y-1 my-1">
                        <div className="flex items-baseline justify-between text-xs">
                          <span className="text-muted-foreground">Товары:</span>
                          <span className="font-semibold text-foreground">
                            {numberFormatter.format(count)}{' '}
                            <span className="text-muted-foreground/80 font-normal">
                              ({countSharePct.toFixed(1)}%)
                            </span>
                          </span>
                        </div>
                        <div className="flex items-baseline justify-between text-xs">
                          <span className="text-muted-foreground">Выручка:</span>
                          <span className="font-semibold text-foreground">
                            {currencyFormatter.format(revenue)}{' '}
                            <span className="text-muted-foreground/80 font-normal">
                              ({revenueSharePct.toFixed(1)}%)
                            </span>
                          </span>
                        </div>
                        <div className="flex items-baseline justify-between text-[11px]">
                          <span className="text-muted-foreground">Остатки:</span>
                          <span className="text-muted-foreground font-medium">
                            {currencyFormatter.format(inventoryValue)}
                          </span>
                        </div>
                      </div>

                      {/* Cell recommendation preview */}
                      <div className="mt-2 pt-2 border-t border-border/30 text-[10px] text-muted-foreground line-clamp-1 italic">
                        {cell?.recommendation || ''}
                      </div>
                    </button>
                  )
                })}
              </div>
            ))}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
