'use client'

import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { XIcon } from 'lucide-react'
import type { SalesCategoryBreakdown } from '../api/sales-gateway'

interface SalesCategoryBreakdownProps {
  categories: SalesCategoryBreakdown[]
  selectedCategoryId?: string
  onSelectCategory?: (categoryId?: string) => void
}

export function SalesCategoryBreakdownView({
  categories,
  selectedCategoryId,
  onSelectCategory,
}: SalesCategoryBreakdownProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  return (
    <Card className="border-border bg-card shadow-xs" data-testid="category-breakdown">
      <CardHeader className="flex flex-row items-center justify-between pb-3">
        <CardTitle className="text-base font-semibold text-foreground">
          Продажи по категориям
        </CardTitle>
        {selectedCategoryId && (
          <Button
            type="button"
            variant="outline"
            size="xs"
            onClick={() => onSelectCategory?.(undefined)}
            title="Сбросить выбор категории"
            className="border-emerald-700/40 bg-emerald-950/20 text-emerald-400 hover:bg-emerald-900/40 hover:text-emerald-300 gap-1 rounded-full text-xs"
          >
            <XIcon className="size-3" />
            Сбросить фильтр
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        {categories.map((cat) => {
          const isSelected = selectedCategoryId === cat.category_id
          const percent = (cat.revenue_share * 100).toFixed(1)
          const valuePercent = Math.min(100, Math.max(0, cat.revenue_share * 100))

          return (
            <div
              key={cat.category_id}
              role="button"
              tabIndex={0}
              aria-pressed={isSelected}
              className={`p-2.5 rounded-lg border transition-all cursor-pointer ${
                isSelected
                  ? 'border-emerald-500 bg-emerald-950/15 ring-1 ring-emerald-500/30'
                  : 'border-transparent hover:border-border hover:bg-muted/40'
              }`}
              onClick={() => onSelectCategory?.(isSelected ? undefined : cat.category_id)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault()
                  onSelectCategory?.(isSelected ? undefined : cat.category_id)
                }
              }}
            >
              <div className="flex justify-between items-center text-xs mb-1.5">
                <span className="font-medium text-foreground flex items-center gap-1.5">
                  {isSelected && <span className="text-emerald-400">●</span>}
                  {cat.category_name}
                </span>
                <span className="text-muted-foreground tabular-nums">
                  {currencyFormatter.format(cat.revenue)} ({percent}%)
                </span>
              </div>
              <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                <div
                  className={`h-full transition-all rounded-full ${
                    isSelected
                      ? 'bg-emerald-400 shadow-xs shadow-emerald-400/50'
                      : 'bg-emerald-500/80'
                  }`}
                  style={{ width: `${valuePercent}%` }}
                />
              </div>
              <div className="text-[11px] text-muted-foreground mt-1">
                {cat.order_count} заказов
              </div>
            </div>
          )
        })}
      </CardContent>
    </Card>
  )
}
