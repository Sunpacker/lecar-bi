'use client'

import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { StockHealthBreakdownItem } from '../api/inventory-gateway'

interface InventoryHealthBreakdownProps {
  items: StockHealthBreakdownItem[]
  selectedStatus: string | null
  onSelectStatus: (status: string | null) => void
}

export function InventoryHealthBreakdown({
  items,
  selectedStatus,
  onSelectStatus,
}: InventoryHealthBreakdownProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const getStatusBadgeStyle = (status: string, isSelected: boolean) => {
    switch (status) {
      case 'optimal':
        return isSelected
          ? 'bg-emerald-600 text-white border-emerald-500 ring-2 ring-emerald-400'
          : 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/25'
      case 'critical':
        return isSelected
          ? 'bg-rose-600 text-white border-rose-500 ring-2 ring-rose-400'
          : 'bg-rose-500/15 text-rose-400 border-rose-500/30 hover:bg-rose-500/25'
      case 'overstock':
        return isSelected
          ? 'bg-amber-600 text-white border-amber-500 ring-2 ring-amber-400'
          : 'bg-amber-500/15 text-amber-400 border-amber-500/30 hover:bg-amber-500/25'
      case 'out_of_stock':
        return isSelected
          ? 'bg-red-700 text-white border-red-600 ring-2 ring-red-400'
          : 'bg-red-500/15 text-red-400 border-red-500/30 hover:bg-red-500/25'
      default:
        return 'bg-secondary text-secondary-foreground'
    }
  }

  const getProgressColor = (status: string) => {
    switch (status) {
      case 'optimal':
        return 'bg-emerald-500'
      case 'critical':
        return 'bg-rose-500'
      case 'overstock':
        return 'bg-amber-500'
      case 'out_of_stock':
        return 'bg-red-600'
      default:
        return 'bg-muted'
    }
  }

  return (
    <Card className="border-border bg-card shadow-xs">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="text-sm font-semibold tracking-wide text-foreground">
            Распределение здоровья запасов
          </CardTitle>
          {selectedStatus && (
            <button
              onClick={() => onSelectStatus(null)}
              className="text-xs text-muted-foreground hover:text-foreground underline cursor-pointer"
            >
              Сбросить фильтр статуса
            </button>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Visual segmented bar */}
        <div className="flex h-3 w-full overflow-hidden rounded-full bg-secondary">
          {items.map((item) => (
            <div
              key={item.status}
              style={{
                width: `${Math.max(item.share * 100, item.items_count > 0 ? 3 : 0)}%`,
              }}
              className={`${getProgressColor(item.status)} transition-all duration-300`}
              title={`${item.label}: ${item.items_count} поз. (${Math.round(item.share * 100)}%)`}
            />
          ))}
        </div>

        {/* Status badges */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1">
          {items.map((item) => {
            const isSelected = selectedStatus === item.status
            return (
              <button
                key={item.status}
                type="button"
                onClick={() => onSelectStatus(isSelected ? null : item.status)}
                className={`p-2.5 rounded-lg border text-left transition-all cursor-pointer ${getStatusBadgeStyle(
                  item.status,
                  isSelected,
                )}`}
              >
                <div className="flex items-center justify-between mb-1">
                  <span className="font-semibold text-xs">{item.label}</span>
                  <Badge
                    variant="outline"
                    className="text-[10px] px-1.5 py-0 border-current"
                  >
                    {Math.round(item.share * 100)}%
                  </Badge>
                </div>
                <div className="text-sm font-bold">
                  {item.label}: {item.items_count}
                </div>
                <div className="text-[11px] opacity-80">
                  {currencyFormatter.format(item.total_value)}
                </div>
              </button>
            )
          })}
        </div>
      </CardContent>
    </Card>
  )
}
