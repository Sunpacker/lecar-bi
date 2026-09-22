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
import type { PaginationMetadata, SalesRecordItem } from '../api/sales-gateway'

export interface SalesDetailTableProps {
  items: SalesRecordItem[]
  pagination: PaginationMetadata
  sortBy?: string
  sortDirection?: 'asc' | 'desc'
  loading?: boolean
  onSortChange: (sortBy: string, sortDirection: 'asc' | 'desc') => void
  onPageChange: (page: number) => void
}

const SORTABLE_COLUMNS: { key: string; label: string; alignRight?: boolean }[] = [
  { key: 'order_date', label: 'Дата' },
  { key: 'order_number', label: '№ Заказа' },
  { key: 'product_name', label: 'Товар' },
  { key: 'quantity', label: 'Кол-во', alignRight: true },
  { key: 'total_price', label: 'Сумма', alignRight: true },
  { key: 'gross_profit', label: 'Прибыль', alignRight: true },
]

export function SalesDetailTable({
  items,
  pagination,
  sortBy = 'order_date',
  sortDirection = 'desc',
  loading = false,
  onSortChange,
  onPageChange,
}: SalesDetailTableProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const handleHeaderClick = (columnKey: string) => {
    if (sortBy === columnKey) {
      onSortChange(columnKey, sortDirection === 'asc' ? 'desc' : 'asc')
    } else {
      onSortChange(columnKey, 'desc')
    }
  }

  const { page, per_page, total, total_pages } = pagination
  const startRecord = total === 0 ? 0 : (page - 1) * per_page + 1
  const endRecord = Math.min(page * per_page, total)

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'completed':
      case 'delivered':
        return (
          <Badge
            variant="outline"
            className="border-emerald-800/40 bg-emerald-950/20 text-emerald-400 capitalize text-[11px]"
          >
            {status}
          </Badge>
        )
      case 'pending':
      case 'processing':
        return (
          <Badge
            variant="outline"
            className="border-amber-800/40 bg-amber-950/20 text-amber-400 capitalize text-[11px]"
          >
            {status}
          </Badge>
        )
      case 'cancelled':
        return (
          <Badge variant="destructive" className="capitalize text-[11px]">
            {status}
          </Badge>
        )
      default:
        return (
          <Badge variant="secondary" className="capitalize text-[11px]">
            {status}
          </Badge>
        )
    }
  }

  return (
    <Card className="border-border bg-card shadow-xs" data-testid="sales-detail-table">
      <CardHeader className="pb-3">
        <div>
          <CardTitle className="text-base font-semibold text-foreground">
            Детализация продаж
          </CardTitle>
          <CardDescription className="text-xs text-muted-foreground mt-1">
            Транзакционные записи по выбранным фильтрам ({total} позиций)
          </CardDescription>
        </div>
      </CardHeader>

      <CardContent className="space-y-4">
        <Table>
          <TableHeader>
            <TableRow className="border-border hover:bg-transparent">
              {SORTABLE_COLUMNS.slice(0, 3).map((col) => {
                const isActive = sortBy === col.key
                return (
                  <TableHead
                    key={col.key}
                    aria-sort={
                      isActive
                        ? sortDirection === 'asc'
                          ? 'ascending'
                          : 'descending'
                        : 'none'
                    }
                    className="h-9 px-2"
                  >
                    <Button
                      type="button"
                      variant="ghost"
                      size="xs"
                      onClick={() => handleHeaderClick(col.key)}
                      className={`h-7 px-1.5 gap-1 text-xs font-semibold uppercase tracking-wider ${
                        isActive ? 'text-emerald-400' : 'text-muted-foreground'
                      }`}
                    >
                      <span>{col.label}</span>
                      {isActive ? (
                        sortDirection === 'asc' ? (
                          <ArrowUpIcon className="size-3 text-emerald-400" />
                        ) : (
                          <ArrowDownIcon className="size-3 text-emerald-400" />
                        )
                      ) : (
                        <ArrowUpDownIcon className="size-3 opacity-50" />
                      )}
                    </Button>
                  </TableHead>
                )
              })}
              <TableHead className="h-9 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                Категория
              </TableHead>
              <TableHead className="h-9 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                Регион
              </TableHead>
              {SORTABLE_COLUMNS.slice(3).map((col) => {
                const isActive = sortBy === col.key
                return (
                  <TableHead
                    key={col.key}
                    aria-sort={
                      isActive
                        ? sortDirection === 'asc'
                          ? 'ascending'
                          : 'descending'
                        : 'none'
                    }
                    className="h-9 px-2 text-right"
                  >
                    <div className="flex justify-end">
                      <Button
                        type="button"
                        variant="ghost"
                        size="xs"
                        onClick={() => handleHeaderClick(col.key)}
                        className={`h-7 px-1.5 gap-1 text-xs font-semibold uppercase tracking-wider ${
                          isActive ? 'text-emerald-400' : 'text-muted-foreground'
                        }`}
                      >
                        <span>{col.label}</span>
                        {isActive ? (
                          sortDirection === 'asc' ? (
                            <ArrowUpIcon className="size-3 text-emerald-400" />
                          ) : (
                            <ArrowDownIcon className="size-3 text-emerald-400" />
                          )
                        ) : (
                          <ArrowUpDownIcon className="size-3 opacity-50" />
                        )}
                      </Button>
                    </div>
                  </TableHead>
                )
              })}
              <TableHead className="h-9 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                Статус
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.length === 0 ? (
              <TableRow>
                <TableCell
                  colSpan={9}
                  className="text-center py-8 text-muted-foreground italic"
                >
                  Нет записей по текущим фильтрам
                </TableCell>
              </TableRow>
            ) : (
              items.map((item) => (
                <TableRow
                  key={item.id}
                  className="border-border hover:bg-muted/40 text-xs"
                >
                  <TableCell className="text-nowrap py-2.5 font-medium">
                    {item.order_date}
                  </TableCell>
                  <TableCell className="font-mono text-xs py-2.5">
                    {item.order_number}
                  </TableCell>
                  <TableCell className="py-2.5">
                    <div className="flex flex-col gap-0.5">
                      <span className="font-medium text-foreground">
                        {item.product_name}
                      </span>
                      <span className="text-[11px] text-muted-foreground">
                        {item.brand_name} · {item.product_sku}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell className="py-2.5">
                    <Badge variant="secondary" className="text-[11px] font-normal">
                      {item.category_name}
                    </Badge>
                  </TableCell>
                  <TableCell className="py-2.5">
                    <Badge variant="outline" className="text-[11px] font-normal">
                      {item.region_name}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right font-medium py-2.5">
                    {item.quantity}
                  </TableCell>
                  <TableCell className="text-right font-medium py-2.5">
                    {currencyFormatter.format(item.total_price)}
                  </TableCell>
                  <TableCell className="text-right font-medium text-emerald-400 py-2.5">
                    {currencyFormatter.format(item.gross_profit)}
                  </TableCell>
                  <TableCell className="py-2.5">{getStatusBadge(item.status)}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>

        <div className="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-border text-xs text-muted-foreground">
          <span>
            Записи {startRecord} - {endRecord} из {total}
          </span>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-7 text-xs gap-1"
              disabled={page <= 1 || loading}
              onClick={() => onPageChange(page - 1)}
            >
              <ChevronLeftIcon className="size-3.5" />
              Назад
            </Button>
            <span className="font-medium text-foreground px-2">
              {page} / {total_pages}
            </span>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-7 text-xs gap-1"
              disabled={page >= total_pages || loading}
              onClick={() => onPageChange(page + 1)}
            >
              Вперед
              <ChevronRightIcon className="size-3.5" />
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
