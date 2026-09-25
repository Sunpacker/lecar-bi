'use client'

import React from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import Link from 'next/link'
import {
  ArrowDownIcon,
  ArrowUpDownIcon,
  ArrowUpIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  TrendingUpIcon,
} from 'lucide-react'
import type { InventoryItem } from '../api/inventory-gateway'

interface PaginationMetadata {
  page: number
  per_page: number
  total: number
  total_pages: number
}

interface InventoryItemsTableProps {
  items: InventoryItem[]
  pagination: PaginationMetadata
  sortBy?: string
  sortDirection?: 'asc' | 'desc'
  loading?: boolean
  onSort: (column: string) => void
  onPageChange: (page: number) => void
}

const SORTABLE_COLUMNS: { key: string; label: string; alignRight?: boolean }[] = [
  { key: 'product_name', label: 'Товар / Артикул' },
  { key: 'quantity_on_hand', label: 'Остаток', alignRight: true },
  { key: 'quantity_available', label: 'Доступно', alignRight: true },
  { key: 'days_of_stock', label: 'Дней запаса (DOS)', alignRight: true },
  { key: 'sales_velocity', label: 'Скорость (шт/день)', alignRight: true },
  { key: 'inventory_value', label: 'Сумма остатка', alignRight: true },
]

export function InventoryItemsTable({
  items,
  pagination,
  sortBy = 'quantity_available',
  sortDirection = 'asc',
  loading = false,
  onSort,
  onPageChange,
}: InventoryItemsTableProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  const getStatusBadge = (status: string, label: string) => {
    switch (status) {
      case 'optimal':
        return (
          <Badge
            variant="outline"
            className="border-emerald-500/40 text-emerald-400 bg-emerald-500/10 text-xs"
          >
            {label}
          </Badge>
        )
      case 'critical':
        return (
          <Badge variant="destructive" className="bg-rose-500/90 text-white text-xs">
            {label}
          </Badge>
        )
      case 'overstock':
        return (
          <Badge
            variant="outline"
            className="border-amber-500/40 text-amber-400 bg-amber-500/10 text-xs"
          >
            {label}
          </Badge>
        )
      case 'out_of_stock':
        return (
          <Badge variant="destructive" className="bg-red-700 text-white text-xs">
            {label}
          </Badge>
        )
      default:
        return <Badge variant="secondary">{label}</Badge>
    }
  }

  const renderSortIcon = (columnKey: string) => {
    if (sortBy !== columnKey) {
      return <ArrowUpDownIcon className="h-3.5 w-3.5 opacity-40 ml-1 inline-block" />
    }
    return sortDirection === 'asc' ? (
      <ArrowUpIcon className="h-3.5 w-3.5 text-primary ml-1 inline-block" />
    ) : (
      <ArrowDownIcon className="h-3.5 w-3.5 text-primary ml-1 inline-block" />
    )
  }

  return (
    <Card className="border-border bg-card shadow-xs">
      <CardHeader className="pb-3">
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
          <div>
            <CardTitle className="text-base font-semibold tracking-tight text-foreground">
              Детализация остатков по позициям
            </CardTitle>
            <CardDescription className="text-xs text-muted-foreground">
              Уровень складских запасов, расчётная скорость продаж и обеспеченность
            </CardDescription>
          </div>
          <div className="text-xs text-muted-foreground">
            Всего позиций:{' '}
            <span className="font-semibold text-foreground">
              {numberFormatter.format(pagination.total)}
            </span>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-0">
        <div className="relative overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow className="border-border hover:bg-transparent">
                {SORTABLE_COLUMNS.map((col) => (
                  <TableHead
                    key={col.key}
                    className={`text-xs font-semibold py-3 cursor-pointer select-none hover:text-foreground ${
                      col.alignRight ? 'text-right' : 'text-left'
                    }`}
                    onClick={() => onSort(col.key)}
                  >
                    <span className="inline-flex items-center">
                      {col.label}
                      {renderSortIcon(col.key)}
                    </span>
                  </TableHead>
                ))}
                <TableHead className="text-xs font-semibold text-left py-3">
                  Склад
                </TableHead>
                <TableHead className="text-xs font-semibold text-center py-3">
                  Статус
                </TableHead>
                <TableHead className="text-xs font-semibold text-center py-3">
                  Прогноз
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                Array.from({ length: 5 }).map((_, idx) => (
                  <TableRow key={idx} className="border-border">
                    <TableCell colSpan={9} className="py-4 text-center">
                      <div className="h-5 bg-muted/40 animate-pulse rounded-md mx-4" />
                    </TableCell>
                  </TableRow>
                ))
              ) : items.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={9}
                    className="py-10 text-center text-sm text-muted-foreground"
                  >
                    По выбранным критериям позиций не найдено
                  </TableCell>
                </TableRow>
              ) : (
                items.map((item) => (
                  <TableRow key={item.id} className="border-border/60 hover:bg-muted/30">
                    <TableCell className="py-2.5">
                      <div
                        className="font-medium text-xs text-foreground max-w-[260px] truncate"
                        title={item.product_name}
                      >
                        {item.product_name}
                      </div>
                      <div className="text-[11px] font-mono text-muted-foreground">
                        {item.product_sku} · {item.category_name}
                      </div>
                    </TableCell>
                    <TableCell className="text-right text-xs py-2.5 font-medium">
                      {numberFormatter.format(item.quantity_on_hand)} шт.
                    </TableCell>
                    <TableCell className="text-right text-xs py-2.5 font-semibold text-foreground">
                      {numberFormatter.format(item.quantity_available)} шт.
                    </TableCell>
                    <TableCell className="text-right text-xs py-2.5">
                      {item.days_of_stock !== null ? (
                        <span
                          className={
                            item.days_of_stock <= 7
                              ? 'text-rose-400 font-bold'
                              : item.days_of_stock > 60
                                ? 'text-amber-400 font-medium'
                                : 'text-foreground'
                          }
                        >
                          {item.days_of_stock.toFixed(1)} дн.
                        </span>
                      ) : (
                        <span className="text-muted-foreground italic">Бесконечно</span>
                      )}
                    </TableCell>
                    <TableCell className="text-right text-xs py-2.5 text-muted-foreground">
                      {item.sales_velocity.toFixed(2)}
                    </TableCell>
                    <TableCell className="text-right text-xs py-2.5 font-semibold text-foreground">
                      {currencyFormatter.format(item.inventory_value)}
                    </TableCell>
                    <TableCell className="py-2.5 text-xs">
                      <div className="truncate max-w-[140px]" title={item.warehouse_name}>
                        {item.warehouse_name}
                      </div>
                      <span className="text-[10px] text-muted-foreground font-mono">
                        {item.warehouse_code}
                      </span>
                    </TableCell>
                    <TableCell className="py-2.5 text-center">
                      {getStatusBadge(item.stock_health, item.stock_health_label)}
                    </TableCell>
                    <TableCell className="py-2.5 text-center">
                      <Link
                        href={`/inventory?tab=forecast&product_id=${item.product_id}&warehouse_id=${item.warehouse_id}`}
                        className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium text-primary hover:bg-primary/10 border border-primary/20 shadow-2xs transition-colors"
                        title="Прогноз спроса и рисков"
                      >
                        <TrendingUpIcon className="h-3 w-3" />
                        <span>Прогноз</span>
                      </Link>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        {/* Pagination controls */}
        {pagination.total_pages > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t border-border text-xs text-muted-foreground">
            <div>
              Страница{' '}
              <span className="font-semibold text-foreground">{pagination.page}</span> из{' '}
              <span className="font-semibold text-foreground">
                {pagination.total_pages}
              </span>
            </div>
            <div className="flex items-center gap-1.5">
              <Button
                variant="outline"
                size="sm"
                disabled={pagination.page <= 1 || loading}
                onClick={() => onPageChange(pagination.page - 1)}
                className="h-8 px-2.5 text-xs"
              >
                <ChevronLeftIcon className="h-3.5 w-3.5 mr-1" />
                Предыдущая
              </Button>
              <Button
                variant="outline"
                size="sm"
                disabled={pagination.page >= pagination.total_pages || loading}
                onClick={() => onPageChange(pagination.page + 1)}
                className="h-8 px-2.5 text-xs"
              >
                Следующая
                <ChevronRightIcon className="h-3.5 w-3.5 ml-1" />
              </Button>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
