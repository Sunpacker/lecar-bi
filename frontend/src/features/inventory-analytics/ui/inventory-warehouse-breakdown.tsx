'use client'

import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { WarehouseStockBreakdownItem } from '../api/inventory-gateway'

interface InventoryWarehouseBreakdownProps {
  warehouses: WarehouseStockBreakdownItem[]
  selectedWarehouseId: string | null
  onSelectWarehouse: (warehouseId: string | null) => void
}

export function InventoryWarehouseBreakdown({
  warehouses,
  selectedWarehouseId,
  onSelectWarehouse,
}: InventoryWarehouseBreakdownProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold tracking-wide text-foreground">
          Срезы по складам
        </h3>
        {selectedWarehouseId && (
          <button
            onClick={() => onSelectWarehouse(null)}
            className="text-xs text-muted-foreground hover:text-foreground underline cursor-pointer"
          >
            Сбросить фильтр склада
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {warehouses.map((wh) => {
          const isSelected = selectedWarehouseId === wh.warehouse_id

          return (
            <button
              key={wh.warehouse_id}
              type="button"
              onClick={() => onSelectWarehouse(isSelected ? null : wh.warehouse_id)}
              className={`p-3 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between ${
                isSelected
                  ? 'border-emerald-500 bg-emerald-500/10 ring-2 ring-emerald-500/30'
                  : 'border-border bg-card hover:border-border/80 hover:bg-accent/40 shadow-xs'
              }`}
            >
              <div>
                <div className="flex items-center justify-between gap-1 mb-1">
                  <span className="font-semibold text-xs text-foreground truncate">
                    {wh.warehouse_name}
                  </span>
                  <Badge
                    variant="outline"
                    className="text-[10px] uppercase font-mono px-1 py-0 shrink-0"
                  >
                    {wh.warehouse_code}
                  </Badge>
                </div>
                <div className="text-base font-bold text-foreground">
                  {numberFormatter.format(wh.total_quantity)} шт.
                </div>
                <div className="text-xs text-muted-foreground">
                  {currencyFormatter.format(wh.total_value)}
                </div>
              </div>

              <div className="mt-3 pt-2 border-t border-border/60 flex items-center justify-between text-[11px]">
                <span className="text-muted-foreground">{wh.items_count} позиций</span>
                <div className="flex items-center gap-1.5">
                  {wh.critical_count > 0 && (
                    <Badge variant="destructive" className="text-[10px] px-1 py-0 h-4">
                      {wh.critical_count} крит.
                    </Badge>
                  )}
                  {wh.overstock_count > 0 && (
                    <Badge
                      variant="secondary"
                      className="text-[10px] px-1 py-0 h-4 text-amber-400 bg-amber-400/10 border border-amber-400/20"
                    >
                      {wh.overstock_count} изб.
                    </Badge>
                  )}
                </div>
              </div>
            </button>
          )
        })}
      </div>
    </div>
  )
}
