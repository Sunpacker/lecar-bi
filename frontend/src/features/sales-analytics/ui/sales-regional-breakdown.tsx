'use client'

import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { XIcon } from 'lucide-react'
import type { SalesRegionBreakdown } from '../api/sales-gateway'

interface SalesRegionalBreakdownProps {
  regions: SalesRegionBreakdown[]
  selectedRegionId?: string
  onSelectRegion?: (regionId?: string) => void
}

export function SalesRegionalBreakdownView({
  regions,
  selectedRegionId,
  onSelectRegion,
}: SalesRegionalBreakdownProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  return (
    <Card className="border-border bg-card shadow-xs" data-testid="regional-breakdown">
      <CardHeader className="flex flex-row items-center justify-between pb-3">
        <CardTitle className="text-base font-semibold text-foreground">
          Региональное распределение
        </CardTitle>
        {selectedRegionId && (
          <Button
            type="button"
            variant="outline"
            size="xs"
            onClick={() => onSelectRegion?.(undefined)}
            title="Сбросить выбор региона"
            className="border-emerald-700/40 bg-emerald-950/20 text-emerald-400 hover:bg-emerald-900/40 hover:text-emerald-300 gap-1 rounded-full text-xs"
          >
            <XIcon className="size-3" />
            Сбросить фильтр
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        {regions.map((reg) => {
          const isSelected = selectedRegionId === reg.region_id
          const percent = (reg.revenue_share * 100).toFixed(1)
          const valuePercent = Math.min(100, Math.max(0, reg.revenue_share * 100))

          return (
            <div
              key={reg.region_id}
              role="button"
              tabIndex={0}
              aria-pressed={isSelected}
              className={`p-2.5 rounded-lg border transition-all cursor-pointer ${
                isSelected
                  ? 'border-emerald-500 bg-emerald-950/15 ring-1 ring-emerald-500/30'
                  : 'border-transparent hover:border-border hover:bg-muted/40'
              }`}
              onClick={() => onSelectRegion?.(isSelected ? undefined : reg.region_id)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault()
                  onSelectRegion?.(isSelected ? undefined : reg.region_id)
                }
              }}
            >
              <div className="flex justify-between items-center text-xs mb-1.5">
                <div className="flex items-center gap-2">
                  <Badge
                    variant="secondary"
                    className="px-1.5 py-0 text-[10px] font-semibold tracking-wide bg-muted text-emerald-400 border border-emerald-900/30"
                  >
                    {reg.region_code}
                  </Badge>
                  <span className="font-medium text-foreground flex items-center gap-1.5">
                    {isSelected && <span className="text-emerald-400">●</span>}
                    {reg.region_name}
                  </span>
                </div>
                <span className="text-muted-foreground tabular-nums">
                  {currencyFormatter.format(reg.revenue)} ({percent}%)
                </span>
              </div>
              <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                <div
                  className={`h-full transition-all rounded-full ${
                    isSelected
                      ? 'bg-sky-400 shadow-xs shadow-sky-400/50'
                      : 'bg-sky-500/80'
                  }`}
                  style={{ width: `${valuePercent}%` }}
                />
              </div>
              <div className="text-[11px] text-muted-foreground mt-1">
                {reg.order_count} заказов
              </div>
            </div>
          )
        })}
      </CardContent>
    </Card>
  )
}
