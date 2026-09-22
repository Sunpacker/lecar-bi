'use client'

import React from 'react'
import { Card } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { RotateCcwIcon } from 'lucide-react'
import type { SalesFilterOptions, SalesFilterParams } from '../api/sales-gateway'

interface SalesFiltersBarProps {
  filterOptions: SalesFilterOptions
  activeFilters: SalesFilterParams
  onFilterChange: (filters: SalesFilterParams) => void
}

export function SalesFiltersBar({
  filterOptions,
  activeFilters,
  onFilterChange,
}: SalesFiltersBarProps) {
  const handleDateFromChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    onFilterChange({
      ...activeFilters,
      dateFrom: e.target.value || undefined,
    })
  }

  const handleDateToChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    onFilterChange({
      ...activeFilters,
      dateTo: e.target.value || undefined,
    })
  }

  const handleCategoryChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    onFilterChange({
      ...activeFilters,
      categoryId: e.target.value || undefined,
    })
  }

  const handleRegionChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    onFilterChange({
      ...activeFilters,
      regionId: e.target.value || undefined,
    })
  }

  const handleReset = () => {
    onFilterChange({})
  }

  return (
    <Card className="p-4 border-border bg-card shadow-xs" data-testid="sales-filters-bar">
      <div className="flex flex-wrap items-end gap-3.5">
        <div className="flex flex-col gap-1.5 min-w-[140px]">
          <Label
            htmlFor="filter-date-from"
            className="text-xs text-muted-foreground font-medium"
          >
            Дата с
          </Label>
          <Input
            id="filter-date-from"
            aria-label="Дата с"
            type="date"
            className="h-8 text-xs bg-muted/20 border-input"
            value={activeFilters.dateFrom ?? ''}
            min={filterOptions.min_date}
            max={filterOptions.max_date}
            onChange={handleDateFromChange}
          />
        </div>

        <div className="flex flex-col gap-1.5 min-w-[140px]">
          <Label
            htmlFor="filter-date-to"
            className="text-xs text-muted-foreground font-medium"
          >
            Дата по
          </Label>
          <Input
            id="filter-date-to"
            aria-label="Дата по"
            type="date"
            className="h-8 text-xs bg-muted/20 border-input"
            value={activeFilters.dateTo ?? ''}
            min={filterOptions.min_date}
            max={filterOptions.max_date}
            onChange={handleDateToChange}
          />
        </div>

        <div className="flex flex-col gap-1.5 min-w-[160px]">
          <Label
            htmlFor="filter-category"
            className="text-xs text-muted-foreground font-medium"
          >
            Категория
          </Label>
          <div className="relative">
            <select
              id="filter-category"
              aria-label="Категория"
              className="h-8 w-full rounded-lg border border-input bg-muted/20 px-3 pr-8 text-xs font-medium text-foreground outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 appearance-none dark:bg-input/30"
              value={activeFilters.categoryId ?? ''}
              onChange={handleCategoryChange}
            >
              <option value="" className="bg-popover text-popover-foreground">
                Все категории
              </option>
              {filterOptions.categories.map((cat) => (
                <option
                  key={cat.id}
                  value={cat.id}
                  className="bg-popover text-popover-foreground"
                >
                  {cat.name}
                </option>
              ))}
            </select>
            <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
              ▼
            </span>
          </div>
        </div>

        <div className="flex flex-col gap-1.5 min-w-[160px]">
          <Label
            htmlFor="filter-region"
            className="text-xs text-muted-foreground font-medium"
          >
            Регион
          </Label>
          <div className="relative">
            <select
              id="filter-region"
              aria-label="Регион"
              className="h-8 w-full rounded-lg border border-input bg-muted/20 px-3 pr-8 text-xs font-medium text-foreground outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 appearance-none dark:bg-input/30"
              value={activeFilters.regionId ?? ''}
              onChange={handleRegionChange}
            >
              <option value="" className="bg-popover text-popover-foreground">
                Все регионы
              </option>
              {filterOptions.regions.map((reg) => (
                <option
                  key={reg.id}
                  value={reg.id}
                  className="bg-popover text-popover-foreground"
                >
                  {reg.name} ({reg.code})
                </option>
              ))}
            </select>
            <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
              ▼
            </span>
          </div>
        </div>

        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground border-border ml-auto"
          onClick={handleReset}
        >
          <RotateCcwIcon className="size-3.5" />
          Сбросить
        </Button>
      </div>
    </Card>
  )
}
