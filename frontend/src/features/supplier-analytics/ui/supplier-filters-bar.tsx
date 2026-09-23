'use client'

import React from 'react'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Calendar, RotateCcw, Building2, Warehouse } from 'lucide-react'
import type { SupplierFilterOptionsResponse } from '../api/supplier-gateway'

export interface SupplierFilterState {
  dateFrom?: string
  dateTo?: string
  supplierId?: string
  warehouseId?: string
}

interface SupplierFiltersBarProps {
  options: SupplierFilterOptionsResponse | null
  filters: SupplierFilterState
  onFilterChange: (filters: SupplierFilterState) => void
}

export function SupplierFiltersBar({
  options,
  filters,
  onFilterChange,
}: SupplierFiltersBarProps) {
  const handlePeriodPreset = (val: string | null) => {
    if (!val) return
    if (val === 'all') {
      onFilterChange({ ...filters, dateFrom: undefined, dateTo: undefined })
    } else if (val === '2025') {
      onFilterChange({
        ...filters,
        dateFrom: '2025-01-01',
        dateTo: '2025-12-31',
      })
    } else if (val === '90d') {
      onFilterChange({
        ...filters,
        dateFrom: '2025-10-01',
        dateTo: '2025-12-31',
      })
    } else if (val === '30d') {
      onFilterChange({
        ...filters,
        dateFrom: '2025-12-01',
        dateTo: '2025-12-31',
      })
    }
  }

  const handleSupplierChange = (val: string | null) => {
    onFilterChange({
      ...filters,
      supplierId: !val || val === 'all' ? undefined : val,
    })
  }

  const handleWarehouseChange = (val: string | null) => {
    onFilterChange({
      ...filters,
      warehouseId: !val || val === 'all' ? undefined : val,
    })
  }

  const handleReset = () => {
    onFilterChange({})
  }

  const isFiltered =
    Boolean(filters.dateFrom) ||
    Boolean(filters.dateTo) ||
    Boolean(filters.supplierId) ||
    Boolean(filters.warehouseId)

  return (
    <div className="flex flex-wrap items-center gap-3 p-3 bg-card border border-border rounded-xl shadow-xs">
      {/* Period Selector */}
      <div className="flex items-center gap-1.5">
        <Calendar className="h-4 w-4 text-muted-foreground shrink-0" />
        <Select
          defaultValue="2025"
          onValueChange={handlePeriodPreset}
        >
          <SelectTrigger className="w-[145px] h-9 text-xs">
            <SelectValue placeholder="Период" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="2025" className="text-xs">2025 год (Все)</SelectItem>
            <SelectItem value="90d" className="text-xs">Q4 2025 (90 дн.)</SelectItem>
            <SelectItem value="30d" className="text-xs">Дек 2025 (30 дн.)</SelectItem>
            <SelectItem value="all" className="text-xs">Весь диапазон</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Supplier Selector */}
      <div className="flex items-center gap-1.5">
        <Building2 className="h-4 w-4 text-muted-foreground shrink-0" />
        <Select
          value={filters.supplierId}
          onValueChange={handleSupplierChange}
        >
          <SelectTrigger className="w-[190px] h-9 text-xs">
            <SelectValue placeholder="Все поставщики" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all" className="text-xs">Все поставщики</SelectItem>
            {options?.suppliers?.map((s) => (
              <SelectItem key={s.id} value={s.id} className="text-xs">
                {s.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Warehouse Selector */}
      <div className="flex items-center gap-1.5">
        <Warehouse className="h-4 w-4 text-muted-foreground shrink-0" />
        <Select
          value={filters.warehouseId}
          onValueChange={handleWarehouseChange}
        >
          <SelectTrigger className="w-[190px] h-9 text-xs">
            <SelectValue placeholder="Все склады" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all" className="text-xs">Все склады</SelectItem>
            {options?.warehouses?.map((w) => (
              <SelectItem key={w.id} value={w.id} className="text-xs">
                {w.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Reset */}
      {isFiltered && (
        <Button
          variant="ghost"
          size="sm"
          onClick={handleReset}
          className="h-9 px-2.5 text-xs text-muted-foreground hover:text-foreground ml-auto"
        >
          <RotateCcw className="h-3.5 w-3.5 mr-1" />
          Сбросить
        </Button>
      )}
    </div>
  )
}
