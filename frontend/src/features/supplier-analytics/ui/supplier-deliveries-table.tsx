'use client'

import React from 'react'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  ArrowUpDown,
  Search,
  ChevronLeft,
  ChevronRight,
  CheckCircle2,
  Clock,
  AlertCircle,
} from 'lucide-react'
import type { SupplierDeliveryItem, DeliveryStatus } from '../api/supplier-gateway'

interface PaginationMeta {
  page: number
  per_page: number
  total: number
  total_pages: number
}

interface SupplierDeliveriesTableProps {
  items: SupplierDeliveryItem[]
  pagination: PaginationMeta
  sortBy: string
  sortDirection: 'asc' | 'desc'
  search: string
  statusFilter?: DeliveryStatus
  onSort: (col: string) => void
  onPageChange: (page: number) => void
  onSearchChange: (search: string) => void
  onStatusChange: (status?: DeliveryStatus) => void
}

export function SupplierDeliveriesTable({
  items,
  pagination,
  sortBy,
  sortDirection,
  search,
  statusFilter,
  onSort,
  onPageChange,
  onSearchChange,
  onStatusChange,
}: SupplierDeliveriesTableProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  const statusMeta: Record<
    string,
    {
      label: string
      badgeClass: string
      icon: React.ComponentType<{ className?: string }>
    }
  > = {
    on_time: {
      label: 'В срок',
      badgeClass:
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
      icon: CheckCircle2,
    },
    delayed: {
      label: 'С задержкой',
      badgeClass:
        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
      icon: Clock,
    },
    partial: {
      label: 'Частично',
      badgeClass:
        'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
      icon: AlertCircle,
    },
  }

  const renderSortHeader = (col: string, label: string) => {
    const isActive = sortBy === col
    return (
      <Button
        variant="ghost"
        size="sm"
        onClick={() => onSort(col)}
        className="h-8 -ml-3 px-3 text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-transparent"
      >
        <span>{label}</span>
        <ArrowUpDown
          className={`ml-1 h-3 w-3 ${isActive ? 'text-foreground' : 'text-muted-foreground/40'}`}
        />
      </Button>
    )
  }

  return (
    <div className="space-y-3">
      {/* Controls: Search and Status filter */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div className="relative flex-1 max-w-sm">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
          <Input
            placeholder="Поиск по товару, артикулу, поставщику..."
            value={search}
            onChange={(e) => onSearchChange(e.target.value)}
            className="pl-8 text-xs h-9 bg-card border-border"
          />
        </div>

        <div className="flex items-center gap-1.5 self-start sm:self-auto overflow-x-auto pb-1 sm:pb-0">
          <Button
            variant={statusFilter === undefined ? 'default' : 'outline'}
            size="sm"
            className="h-8 text-xs px-2.5"
            onClick={() => onStatusChange(undefined)}
          >
            Все
          </Button>
          <Button
            variant={statusFilter === 'on_time' ? 'default' : 'outline'}
            size="sm"
            className="h-8 text-xs px-2.5"
            onClick={() => onStatusChange('on_time')}
          >
            В срок
          </Button>
          <Button
            variant={statusFilter === 'delayed' ? 'default' : 'outline'}
            size="sm"
            className="h-8 text-xs px-2.5"
            onClick={() => onStatusChange('delayed')}
          >
            С задержкой
          </Button>
          <Button
            variant={statusFilter === 'partial' ? 'default' : 'outline'}
            size="sm"
            className="h-8 text-xs px-2.5"
            onClick={() => onStatusChange('partial')}
          >
            Частично
          </Button>
        </div>
      </div>

      {/* Table */}
      <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent border-border">
              <TableHead>{renderSortHeader('order_date', 'Дата заказа')}</TableHead>
              <TableHead>{renderSortHeader('expected_delivery_date', 'План / Факт')}</TableHead>
              <TableHead>Поставщик</TableHead>
              <TableHead>Товар / Артикул</TableHead>
              <TableHead>Склад</TableHead>
              <TableHead className="text-right">Кол-во (зак/факт)</TableHead>
              <TableHead className="text-right">{renderSortHeader('total_purchase_cost', 'Сумма')}</TableHead>
              <TableHead className="text-center">Статус</TableHead>
              <TableHead className="text-right">{renderSortHeader('lead_time_days', 'Срок / Задержка')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.map((del) => {
              const meta = statusMeta[del.delivery_status] ?? {
                label: del.delivery_status,
                badgeClass: 'bg-muted text-muted-foreground',
                icon: AlertCircle,
              }
              const Icon = meta.icon

              return (
                <TableRow key={del.id} className="border-border hover:bg-muted/40 text-xs">
                  <TableCell className="font-mono text-muted-foreground py-3">
                    {del.order_date}
                  </TableCell>
                  <TableCell className="py-3">
                    <div className="font-mono text-foreground">{del.expected_delivery_date}</div>
                    <div className="text-[10px] text-muted-foreground">
                      факт: {del.actual_delivery_date ?? '—'}
                    </div>
                  </TableCell>
                  <TableCell className="font-medium text-foreground py-3">
                    {del.supplier_name}
                  </TableCell>
                  <TableCell className="py-3">
                    <div className="font-medium text-foreground">{del.product_name}</div>
                    <div className="font-mono text-[10px] text-muted-foreground">
                      {del.product_sku}
                    </div>
                  </TableCell>
                  <TableCell className="text-muted-foreground py-3">
                    {del.warehouse_name}
                  </TableCell>
                  <TableCell className="text-right font-mono py-3">
                    <div>
                      {numberFormatter.format(del.received_quantity)} / {numberFormatter.format(del.ordered_quantity)}
                    </div>
                    {del.defect_quantity > 0 && (
                      <div className="text-[10px] text-amber-600">
                        брак: {del.defect_quantity} шт.
                      </div>
                    )}
                  </TableCell>
                  <TableCell className="text-right font-medium text-foreground py-3">
                    {currencyFormatter.format(del.total_purchase_cost)}
                  </TableCell>
                  <TableCell className="text-center py-3">
                    <Badge variant="outline" className={`inline-flex items-center gap-1 text-[10px] px-2 py-0.5 ${meta.badgeClass}`}>
                      <Icon className="h-3 w-3" />
                      {meta.label}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right py-3">
                    <span className="font-mono text-foreground">{del.lead_time_days} дн.</span>
                    {del.delay_days > 0 && (
                      <span className="text-[10px] text-amber-600 block">
                        +{del.delay_days} дн.
                      </span>
                    )}
                  </TableCell>
                </TableRow>
              )
            })}

            {items.length === 0 && (
              <TableRow>
                <TableCell colSpan={9} className="h-32 text-center text-xs text-muted-foreground">
                  Записи о поставках не найдены
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      {/* Pagination Controls */}
      {pagination.total_pages > 1 && (
        <div className="flex items-center justify-between text-xs text-muted-foreground pt-1">
          <div>
            Страница {pagination.page} из {pagination.total_pages} (всего {pagination.total} записей)
          </div>
          <div className="flex items-center gap-1">
            <Button
              variant="outline"
              size="sm"
              className="h-8 w-8 p-0"
              disabled={pagination.page <= 1}
              onClick={() => onPageChange(pagination.page - 1)}
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button
              variant="outline"
              size="sm"
              className="h-8 w-8 p-0"
              disabled={pagination.page >= pagination.total_pages}
              onClick={() => onPageChange(pagination.page + 1)}
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
