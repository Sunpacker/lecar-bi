'use client'

import React, { useEffect, useState } from 'react'
import { Card } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RotateCcwIcon, CalendarIcon } from 'lucide-react'
import type { DashboardFilterValues } from '../api/dashboard-gateway'
import {
  salesGateway,
  type SalesFilterOptions,
} from '../../sales-analytics/api/sales-gateway'
import {
  inventoryGateway,
  type InventoryFilterOptionsResponse,
} from '../../inventory-analytics/api/inventory-gateway'
import { hasActiveFilters } from '../model/filter-resolver'

interface DashboardFilterBarProps {
  activeFilters: DashboardFilterValues
  onFilterChange: (filters: DashboardFilterValues) => void
  userId: string
  workspaceId: string
}

const DATE_PRESETS: {
  label: string
  value: DashboardFilterValues['date_range']
}[] = [
  { label: '30 дн', value: '30d' },
  { label: '90 дн', value: '90d' },
  { label: '180 дн', value: '180d' },
  { label: '365 дн', value: '365d' },
  { label: 'Всё время', value: 'all' },
  { label: 'Период', value: 'custom' },
]

export function DashboardFilterBar({
  activeFilters,
  onFilterChange,
  userId,
  workspaceId,
}: DashboardFilterBarProps) {
  const [salesOptions, setSalesOptions] = useState<SalesFilterOptions | null>(null)
  const [invOptions, setInvOptions] = useState<InventoryFilterOptionsResponse | null>(
    null,
  )

  useEffect(() => {
    let isCancelled = false

    Promise.all([
      salesGateway.getFilterOptions(userId, workspaceId).catch(() => null),
      inventoryGateway.getFilters(userId, workspaceId).catch(() => null),
    ]).then(([sData, iData]) => {
      if (!isCancelled) {
        if (sData) setSalesOptions(sData)
        if (iData) setInvOptions(iData)
      }
    })

    return () => {
      isCancelled = true
    }
  }, [userId, workspaceId])

  const setDatePreset = (preset: DashboardFilterValues['date_range']) => {
    if (preset === 'custom') {
      onFilterChange({
        ...activeFilters,
        date_range: 'custom',
        date_from: activeFilters.date_from || salesOptions?.min_date || '',
        date_to: activeFilters.date_to || salesOptions?.max_date || '',
      })
    } else {
      const next = { ...activeFilters, date_range: preset }
      delete next.date_from
      delete next.date_to
      onFilterChange(next)
    }
  }

  const handleCustomDateChange = (field: 'date_from' | 'date_to', val: string) => {
    onFilterChange({
      ...activeFilters,
      date_range: 'custom',
      [field]: val || undefined,
    })
  }

  const handleSelectChange = (
    field: 'category_id' | 'region_id' | 'warehouse_id' | 'stock_health',
    val: string,
  ) => {
    const next = { ...activeFilters }
    if (!val) {
      delete next[field]
    } else {
      // @ts-expect-error dynamic key assignment
      next[field] = val
    }
    onFilterChange(next)
  }

  const handleReset = () => {
    onFilterChange({})
  }

  const isCustom = activeFilters.date_range === 'custom'
  const isFiltered = hasActiveFilters(activeFilters)

  return (
    <Card
      className="p-3.5 border-border bg-card/60 backdrop-blur-xs shadow-xs space-y-3"
      data-testid="dashboard-filter-bar"
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        {/* Date presets toolbar */}
        <div className="flex items-center gap-1.5 flex-wrap">
          <span className="text-xs font-medium text-muted-foreground mr-1 flex items-center gap-1">
            <CalendarIcon className="size-3.5" />
            Период:
          </span>
          {DATE_PRESETS.map((p) => {
            const isSelected = activeFilters.date_range === p.value
            return (
              <Button
                key={p.value}
                type="button"
                size="sm"
                variant={isSelected ? 'default' : 'outline'}
                onClick={() => setDatePreset(p.value)}
                className={`h-7 px-2.5 text-xs font-normal ${
                  isSelected
                    ? 'bg-emerald-600 hover:bg-emerald-500 text-white'
                    : 'text-muted-foreground'
                }`}
              >
                {p.label}
              </Button>
            )
          })}
        </div>

        {/* Reset button */}
        {isFiltered && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={handleReset}
            className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground gap-1 ml-auto"
          >
            <RotateCcwIcon className="size-3" />
            <span>Сбросить фильтры</span>
          </Button>
        )}
      </div>

      {/* Custom dates row if custom period selected */}
      {isCustom && (
        <div className="flex flex-wrap items-center gap-3 pt-1 border-t border-border/40">
          <div className="flex items-center gap-2">
            <Label htmlFor="shared-date-from" className="text-xs text-muted-foreground">
              Дата с:
            </Label>
            <Input
              id="shared-date-from"
              aria-label="Дата с"
              type="date"
              className="h-7 text-xs w-36 bg-background/80"
              value={activeFilters.date_from ?? ''}
              min={salesOptions?.min_date}
              max={salesOptions?.max_date}
              onChange={(e) => handleCustomDateChange('date_from', e.target.value)}
            />
          </div>
          <div className="flex items-center gap-2">
            <Label htmlFor="shared-date-to" className="text-xs text-muted-foreground">
              Дата по:
            </Label>
            <Input
              id="shared-date-to"
              aria-label="Дата по"
              type="date"
              className="h-7 text-xs w-36 bg-background/80"
              value={activeFilters.date_to ?? ''}
              min={salesOptions?.min_date}
              max={salesOptions?.max_date}
              onChange={(e) => handleCustomDateChange('date_to', e.target.value)}
            />
          </div>
        </div>
      )}

      {/* Select dropdowns for Categories, Regions, Warehouses, Stock Health */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5 pt-1 border-t border-border/40">
        {/* Category */}
        <div className="relative">
          <select
            id="shared-category-filter"
            aria-label="Категория"
            className="h-8 w-full rounded-lg border border-input bg-background/80 px-2.5 pr-7 text-xs text-foreground outline-none transition-colors focus-visible:border-ring appearance-none"
            value={activeFilters.category_id ?? ''}
            onChange={(e) => handleSelectChange('category_id', e.target.value)}
          >
            <option value="">Все категории</option>
            {salesOptions?.categories.map((cat) => (
              <option key={cat.id} value={cat.id}>
                {cat.name}
              </option>
            ))}
          </select>
          <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
            ▼
          </span>
        </div>

        {/* Region */}
        <div className="relative">
          <select
            id="shared-region-filter"
            aria-label="Регион"
            className="h-8 w-full rounded-lg border border-input bg-background/80 px-2.5 pr-7 text-xs text-foreground outline-none transition-colors focus-visible:border-ring appearance-none"
            value={activeFilters.region_id ?? ''}
            onChange={(e) => handleSelectChange('region_id', e.target.value)}
          >
            <option value="">Все регионы</option>
            {salesOptions?.regions.map((reg) => (
              <option key={reg.id} value={reg.id}>
                {reg.name} ({reg.code})
              </option>
            ))}
          </select>
          <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
            ▼
          </span>
        </div>

        {/* Warehouse */}
        <div className="relative">
          <select
            id="shared-warehouse-filter"
            aria-label="Склад"
            className="h-8 w-full rounded-lg border border-input bg-background/80 px-2.5 pr-7 text-xs text-foreground outline-none transition-colors focus-visible:border-ring appearance-none"
            value={activeFilters.warehouse_id ?? ''}
            onChange={(e) => handleSelectChange('warehouse_id', e.target.value)}
          >
            <option value="">Все склады</option>
            {invOptions?.warehouses.map((wh) => (
              <option key={wh.id} value={wh.id}>
                {wh.name} ({wh.code})
              </option>
            ))}
          </select>
          <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
            ▼
          </span>
        </div>

        {/* Stock Health */}
        <div className="relative">
          <select
            id="shared-stock-health-filter"
            aria-label="Статус остатков"
            className="h-8 w-full rounded-lg border border-input bg-background/80 px-2.5 pr-7 text-xs text-foreground outline-none transition-colors focus-visible:border-ring appearance-none"
            value={activeFilters.stock_health ?? ''}
            onChange={(e) => handleSelectChange('stock_health', e.target.value)}
          >
            <option value="">Все статусы остатков</option>
            <option value="in_stock">В наличии (оптимально)</option>
            <option value="low_stock">Критический остаток</option>
            <option value="out_of_stock">Нет на складе</option>
            <option value="overstock">Избыток</option>
          </select>
          <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
            ▼
          </span>
        </div>
      </div>
    </Card>
  )
}
