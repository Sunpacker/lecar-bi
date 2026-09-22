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
import {
  ArrowDownIcon,
  ArrowUpDownIcon,
  ArrowUpIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
} from 'lucide-react'
import type { AbcXyzProductItem } from '../api/inventory-gateway'

interface PaginationMetadata {
  page: number
  per_page: number
  total: number
  total_pages: number
}

interface AbcXyzItemsTableProps {
  items: AbcXyzProductItem[]
  pagination: PaginationMetadata
  sortBy?: string
  sortDirection?: 'asc' | 'desc'
  loading?: boolean
  onSort: (column: string) => void
  onPageChange: (page: number) => void
}

const GROUP_RECOMMENDATIONS: Record<string, string> = {
  AX: 'Just-in-Time, автоматические заказы, минимальный страховой запас',
  AY: 'Страховой запас для сглаживания сезонных пиков',
  AZ: 'Заказ под клиента или минимальный страховой буфер',
  BX: 'Заказ фиксированными партиями по расписанию',
  BY: 'Гибкие партии с учетом сезонности',
  BZ: 'Поставка по заявкам клиентов',
  CX: 'Поставки крупными партиями с редкой периодичностью',
  CY: 'Снижение неснижаемого остатка, заказ по мере истощения',
  CZ: 'Кандидаты на вывод из ассортимента или строго под заказ',
}

export function AbcXyzItemsTable({
  items,
  pagination,
  sortBy = 'total_revenue',
  sortDirection = 'desc',
  loading = false,
  onSort,
  onPageChange,
}: AbcXyzItemsTableProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  const getAbcBadge = (abc: 'A' | 'B' | 'C') => {
    switch (abc) {
      case 'A':
        return (
          <Badge
            variant="outline"
            className="border-emerald-500/40 text-emerald-400 bg-emerald-500/10 font-bold"
          >
            A
          </Badge>
        )
      case 'B':
        return (
          <Badge
            variant="outline"
            className="border-blue-500/40 text-blue-400 bg-blue-500/10 font-bold"
          >
            B
          </Badge>
        )
      case 'C':
        return (
          <Badge
            variant="outline"
            className="border-slate-500/40 text-slate-400 bg-slate-500/10 font-bold"
          >
            C
          </Badge>
        )
    }
  }

  const getXyzBadge = (xyz: 'X' | 'Y' | 'Z') => {
    switch (xyz) {
      case 'X':
        return (
          <Badge
            variant="outline"
            className="border-teal-500/40 text-teal-400 bg-teal-500/10 font-bold"
          >
            X
          </Badge>
        )
      case 'Y':
        return (
          <Badge
            variant="outline"
            className="border-amber-500/40 text-amber-400 bg-amber-500/10 font-bold"
          >
            Y
          </Badge>
        )
      case 'Z':
        return (
          <Badge
            variant="outline"
            className="border-rose-500/40 text-rose-400 bg-rose-500/10 font-bold"
          >
            Z
          </Badge>
        )
    }
  }

  const getGroupBadge = (group: string) => {
    return (
      <Badge variant="secondary" className="font-mono text-xs px-2 py-0.5 font-bold">
        {group}
      </Badge>
    )
  }

  const renderSortIcon = (columnKey: string) => {
    if (sortBy !== columnKey) {
      return (
        <ArrowUpDownIcon className="ml-1.5 h-3.5 w-3.5 text-muted-foreground/50 opacity-0 group-hover:opacity-100 transition-opacity" />
      )
    }
    return sortDirection === 'asc' ? (
      <ArrowUpIcon className="ml-1.5 h-3.5 w-3.5 text-primary" />
    ) : (
      <ArrowDownIcon className="ml-1.5 h-3.5 w-3.5 text-primary" />
    )
  }

  return (
    <Card className="border-border/60 bg-card/60 backdrop-blur-xs">
      <CardHeader className="pb-3">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <CardTitle className="text-lg font-semibold">
              Детализация по номенклатуре
            </CardTitle>
            <CardDescription className="text-xs text-muted-foreground">
              Всего товаров в выборке:{' '}
              <span className="font-semibold text-foreground">
                {numberFormatter.format(pagination.total)}
              </span>
            </CardDescription>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-4">
        <div className="relative rounded-lg border border-border/50 overflow-hidden">
          {loading && (
            <div className="absolute inset-0 bg-background/50 backdrop-blur-xs flex items-center justify-center z-10">
              <span className="text-xs text-muted-foreground animate-pulse">
                Загрузка данных...
              </span>
            </div>
          )}

          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow className="bg-muted/40 hover:bg-muted/40">
                  <TableHead className="w-[40px] text-xs font-semibold">Группа</TableHead>
                  <TableHead
                    className="text-xs font-semibold cursor-pointer select-none group min-w-[220px]"
                    onClick={() => onSort('product_name')}
                  >
                    <div className="flex items-center">
                      Товар / Артикул
                      {renderSortIcon('product_name')}
                    </div>
                  </TableHead>
                  <TableHead className="text-xs font-semibold">
                    Категория / Поставщик
                  </TableHead>
                  <TableHead
                    className="text-xs font-semibold text-right cursor-pointer select-none group"
                    onClick={() => onSort('total_revenue')}
                  >
                    <div className="flex items-center justify-end">
                      Выручка
                      {renderSortIcon('total_revenue')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-xs font-semibold text-right cursor-pointer select-none group"
                    onClick={() => onSort('revenue_share')}
                  >
                    <div className="flex items-center justify-end">
                      Доля
                      {renderSortIcon('revenue_share')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-xs font-semibold text-right cursor-pointer select-none group"
                    onClick={() => onSort('cumulative_revenue_share')}
                  >
                    <div className="flex items-center justify-end">
                      Накопл.
                      {renderSortIcon('cumulative_revenue_share')}
                    </div>
                  </TableHead>
                  <TableHead className="text-xs font-semibold text-center w-[50px]">
                    ABC
                  </TableHead>
                  <TableHead
                    className="text-xs font-semibold text-right cursor-pointer select-none group"
                    onClick={() => onSort('coefficient_of_variation')}
                  >
                    <div className="flex items-center justify-end">
                      CV
                      {renderSortIcon('coefficient_of_variation')}
                    </div>
                  </TableHead>
                  <TableHead className="text-xs font-semibold text-center w-[50px]">
                    XYZ
                  </TableHead>
                  <TableHead
                    className="text-xs font-semibold text-right cursor-pointer select-none group"
                    onClick={() => onSort('current_stock')}
                  >
                    <div className="flex items-center justify-end">
                      Остаток
                      {renderSortIcon('current_stock')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-xs font-semibold text-right cursor-pointer select-none group"
                    onClick={() => onSort('inventory_value')}
                  >
                    <div className="flex items-center justify-end">
                      Сумма запаса
                      {renderSortIcon('inventory_value')}
                    </div>
                  </TableHead>
                  <TableHead className="text-xs font-semibold min-w-[200px]">
                    Стратегия
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={12}
                      className="text-center py-8 text-xs text-muted-foreground"
                    >
                      По заданным критериям фильтрации товары не найдены.
                    </TableCell>
                  </TableRow>
                ) : (
                  items.map((item) => (
                    <TableRow
                      key={item.id || item.product_id}
                      className="hover:bg-muted/30"
                    >
                      <TableCell className="font-mono text-center">
                        {getGroupBadge(item.abc_xyz_group)}
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col max-w-[240px]">
                          <span
                            className="font-medium text-xs text-foreground truncate"
                            title={item.product_name}
                          >
                            {item.product_name}
                          </span>
                          <span className="font-mono text-[11px] text-muted-foreground">
                            {item.product_sku}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col text-[11px] text-muted-foreground">
                          <span className="truncate max-w-[150px]">
                            {item.category_name || '—'}
                          </span>
                          <span className="truncate max-w-[150px] text-muted-foreground/70">
                            {item.supplier_name || '—'}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell className="text-right font-medium text-xs text-foreground">
                        {currencyFormatter.format(item.total_revenue)}
                      </TableCell>
                      <TableCell className="text-right text-xs text-muted-foreground">
                        {(item.revenue_share * 100).toFixed(1)}%
                      </TableCell>
                      <TableCell className="text-right text-xs text-muted-foreground">
                        {(item.cumulative_revenue_share * 100).toFixed(1)}%
                      </TableCell>
                      <TableCell className="text-center">
                        {getAbcBadge(item.abc_class)}
                      </TableCell>
                      <TableCell className="text-right text-xs text-muted-foreground">
                        {item.coefficient_of_variation != null
                          ? `${item.coefficient_of_variation.toFixed(1)}%`
                          : '—'}
                      </TableCell>
                      <TableCell className="text-center">
                        {getXyzBadge(item.xyz_class)}
                      </TableCell>
                      <TableCell className="text-right text-xs font-medium text-foreground">
                        {numberFormatter.format(item.current_stock)}
                      </TableCell>
                      <TableCell className="text-right text-xs text-muted-foreground">
                        {currencyFormatter.format(item.inventory_value)}
                      </TableCell>
                      <TableCell className="text-[11px] text-muted-foreground">
                        <span
                          className="line-clamp-2"
                          title={GROUP_RECOMMENDATIONS[item.abc_xyz_group] || ''}
                        >
                          {GROUP_RECOMMENDATIONS[item.abc_xyz_group] || ''}
                        </span>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>
        </div>

        {/* Pagination Controls */}
        {pagination.total_pages > 1 && (
          <div className="flex items-center justify-between pt-2">
            <div className="text-xs text-muted-foreground">
              Страница {pagination.page} из{' '}
              {pagination.page > 0 ? pagination.total_pages : 1}
            </div>
            <div className="flex items-center gap-1.5">
              <Button
                variant="outline"
                size="sm"
                disabled={pagination.page <= 1 || loading}
                onClick={() => onPageChange(pagination.page - 1)}
                className="h-8 text-xs"
              >
                <ChevronLeftIcon className="h-3.5 w-3.5 mr-1" />
                Назад
              </Button>
              <Button
                variant="outline"
                size="sm"
                disabled={pagination.page >= pagination.total_pages || loading}
                onClick={() => onPageChange(pagination.page + 1)}
                className="h-8 text-xs"
              >
                Вперед
                <ChevronRightIcon className="h-3.5 w-3.5 ml-1" />
              </Button>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
