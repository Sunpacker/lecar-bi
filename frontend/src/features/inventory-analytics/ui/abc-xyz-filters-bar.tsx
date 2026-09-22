'use client'

import React from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { RotateCcwIcon, SearchIcon } from 'lucide-react'
import type { InventoryFilterOptionsResponse } from '../api/inventory-gateway'

interface AbcXyzFiltersBarProps {
  filterOptions: InventoryFilterOptionsResponse
  periodDays: 30 | 90 | 180 | 365
  warehouseId: string | null
  categoryId: string | null
  supplierId: string | null
  selectedGroup: string | null
  search: string
  onPeriodChange: (period: 30 | 90 | 180 | 365) => void
  onWarehouseChange: (value: string | null) => void
  onCategoryChange: (value: string | null) => void
  onSupplierChange: (value: string | null) => void
  onGroupChange: (value: string | null) => void
  onSearchChange: (value: string) => void
  onReset: () => void
}

const PERIOD_OPTIONS: { value: 30 | 90 | 180 | 365; label: string }[] = [
  { value: 30, label: '30 дней' },
  { value: 90, label: '90 дней (кв.)' },
  { value: 180, label: '180 дней (полгода)' },
  { value: 365, label: '365 дней (год)' },
]

const MATRIX_GROUPS = ['AX', 'AY', 'AZ', 'BX', 'BY', 'BZ', 'CX', 'CY', 'CZ'] as const

export function AbcXyzFiltersBar({
  filterOptions,
  periodDays,
  warehouseId,
  categoryId,
  supplierId,
  selectedGroup,
  search,
  onPeriodChange,
  onWarehouseChange,
  onCategoryChange,
  onSupplierChange,
  onGroupChange,
  onSearchChange,
  onReset,
}: AbcXyzFiltersBarProps) {
  const hasActiveFilters =
    periodDays !== 90 ||
    Boolean(warehouseId) ||
    Boolean(categoryId) ||
    Boolean(supplierId) ||
    Boolean(selectedGroup) ||
    Boolean(search.trim())

  return (
    <div className="flex flex-col gap-3 p-3.5 bg-card/60 backdrop-blur-xs border border-border rounded-xl">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5 items-center">
        {/* Search */}
        <div className="relative sm:col-span-2">
          <SearchIcon className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Поиск по артикулу или названию..."
            value={search}
            onChange={(e) => onSearchChange(e.target.value)}
            className="pl-8 text-xs h-9 bg-background/80"
          />
        </div>

        {/* Period */}
        <div>
          <Select
            value={String(periodDays)}
            onValueChange={(val) => onPeriodChange(Number(val) as 30 | 90 | 180 | 365)}
          >
            <SelectTrigger className="text-xs h-9 bg-background/80">
              <SelectValue placeholder="Период" />
            </SelectTrigger>
            <SelectContent>
              {PERIOD_OPTIONS.map((p) => (
                <SelectItem key={p.value} value={String(p.value)}>
                  {p.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Warehouse Filter */}
        <div>
          <Select
            value={warehouseId ?? 'all'}
            onValueChange={(val) => onWarehouseChange(val === 'all' ? null : val)}
          >
            <SelectTrigger className="text-xs h-9 bg-background/80">
              <SelectValue placeholder="Все склады" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Все склады</SelectItem>
              {filterOptions.warehouses?.map((wh) => (
                <SelectItem key={wh.id} value={wh.id}>
                  {wh.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Category Filter */}
        <div>
          <Select
            value={categoryId ?? 'all'}
            onValueChange={(val) => onCategoryChange(val === 'all' ? null : val)}
          >
            <SelectTrigger className="text-xs h-9 bg-background/80">
              <SelectValue placeholder="Все категории" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Все категории</SelectItem>
              {filterOptions.categories?.map((cat) => (
                <SelectItem key={cat.id} value={cat.id}>
                  {cat.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Supplier Filter */}
        <div>
          <Select
            value={supplierId ?? 'all'}
            onValueChange={(val) => onSupplierChange(val === 'all' ? null : val)}
          >
            <SelectTrigger className="text-xs h-9 bg-background/80">
              <SelectValue placeholder="Все поставщики" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Все поставщики</SelectItem>
              {filterOptions.suppliers?.map((sup) => (
                <SelectItem key={sup.id} value={sup.id}>
                  {sup.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="flex items-center justify-between gap-2 pt-1">
        {/* Quick Group Select */}
        <div className="flex flex-wrap items-center gap-1 text-xs">
          <span className="text-muted-foreground mr-1 text-[11px]">Группа:</span>
          <Button
            variant={selectedGroup === null ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => onGroupChange(null)}
            className="h-7 text-[11px] px-2"
          >
            Все
          </Button>
          {MATRIX_GROUPS.map((grp) => (
            <Button
              key={grp}
              variant={selectedGroup === grp ? 'default' : 'outline'}
              size="sm"
              onClick={() => onGroupChange(selectedGroup === grp ? null : grp)}
              className="h-7 text-[11px] px-2 font-mono"
            >
              {grp}
            </Button>
          ))}
        </div>

        {/* Reset button */}
        {hasActiveFilters && (
          <Button
            variant="outline"
            size="sm"
            onClick={onReset}
            className="h-7 text-xs px-2.5 text-muted-foreground hover:text-foreground shrink-0"
          >
            <RotateCcwIcon className="h-3 w-3 mr-1" />
            Сбросить
          </Button>
        )}
      </div>
    </div>
  )
}
