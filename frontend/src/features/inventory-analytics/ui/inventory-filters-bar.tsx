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

interface InventoryFiltersBarProps {
  filterOptions: InventoryFilterOptionsResponse
  warehouseId: string | null
  stockHealth: string | null
  search: string
  onWarehouseChange: (value: string | null) => void
  onStockHealthChange: (value: string | null) => void
  onSearchChange: (value: string) => void
  onReset: () => void
}

export function InventoryFiltersBar({
  filterOptions,
  warehouseId,
  stockHealth,
  search,
  onWarehouseChange,
  onStockHealthChange,
  onSearchChange,
  onReset,
}: InventoryFiltersBarProps) {
  const hasActiveFilters =
    Boolean(warehouseId) || Boolean(stockHealth) || Boolean(search.trim())

  return (
    <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 p-3.5 bg-card/60 backdrop-blur-xs border border-border rounded-xl">
      <div className="flex flex-1 flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        {/* Search */}
        <div className="relative flex-1 min-w-[200px]">
          <SearchIcon className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Поиск по артикулу (SKU) или названию..."
            value={search}
            onChange={(e) => onSearchChange(e.target.value)}
            className="pl-8 text-xs h-9 bg-background/80"
          />
        </div>

        {/* Warehouse Filter */}
        <div className="w-full sm:w-[220px]">
          <Select
            value={warehouseId ?? 'all'}
            onValueChange={(val) => onWarehouseChange(val === 'all' ? null : val)}
          >
            <SelectTrigger className="text-xs h-9 bg-background/80">
              <SelectValue placeholder="Все склады" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Все склады</SelectItem>
              {filterOptions.warehouses.map((wh) => (
                <SelectItem key={wh.id} value={wh.id}>
                  {wh.name} ({wh.code})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Status Filter */}
        <div className="w-full sm:w-[180px]">
          <Select
            value={stockHealth ?? 'all'}
            onValueChange={(val) => onStockHealthChange(val === 'all' ? null : val)}
          >
            <SelectTrigger className="text-xs h-9 bg-background/80">
              <SelectValue placeholder="Все статусы" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Все статусы</SelectItem>
              {filterOptions.statuses.map((st) => (
                <SelectItem key={st.value} value={st.value}>
                  {st.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Reset button */}
      {hasActiveFilters && (
        <Button
          variant="outline"
          size="sm"
          onClick={onReset}
          className="h-9 text-xs px-3 text-muted-foreground hover:text-foreground shrink-0"
        >
          <RotateCcwIcon className="h-3.5 w-3.5 mr-1.5" />
          Сбросить
        </Button>
      )}
    </div>
  )
}
