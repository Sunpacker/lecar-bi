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
  ShieldCheck,
  ShieldAlert,
} from 'lucide-react'
import type { SupplierPerformanceItem } from '../api/supplier-gateway'

interface PaginationMeta {
  page: number
  per_page: number
  total: number
  total_pages: number
}

interface SupplierPerformanceTableProps {
  items: SupplierPerformanceItem[]
  pagination: PaginationMeta
  sortBy: string
  sortDirection: 'asc' | 'desc'
  search: string
  onSort: (col: string) => void
  onPageChange: (page: number) => void
  onSearchChange: (search: string) => void
}

export function SupplierPerformanceTable({
  items,
  pagination,
  sortBy,
  sortDirection,
  search,
  onSort,
  onPageChange,
  onSearchChange,
}: SupplierPerformanceTableProps) {
  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  const tierMeta: Record<string, { label: string; badgeClass: string }> = {
    excellent: {
      label: 'Высокая',
      badgeClass:
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
    },
    good: {
      label: 'Хорошая',
      badgeClass: 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-500/20',
    },
    acceptable: {
      label: 'Средняя',
      badgeClass:
        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
    },
    poor: {
      label: 'Низкая',
      badgeClass: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
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
      {/* Search Header */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div className="relative flex-1 max-w-sm">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
          <Input
            placeholder="Поиск по поставщику..."
            value={search}
            onChange={(e) => onSearchChange(e.target.value)}
            className="pl-8 text-xs h-9 bg-card border-border"
          />
        </div>
        <div className="text-xs text-muted-foreground self-center">
          Найдено: {pagination.total} поставщиков
        </div>
      </div>

      {/* Table */}
      <div className="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent border-border">
              <TableHead>{renderSortHeader('supplier_name', 'Поставщик')}</TableHead>
              <TableHead>{renderSortHeader('total_deliveries', 'Заказов')}</TableHead>
              <TableHead className="text-right">
                {renderSortHeader('total_spend', 'Сумма закупок')}
              </TableHead>
              <TableHead className="text-right">
                {renderSortHeader('on_time_rate', 'В срок (OTD)')}
              </TableHead>
              <TableHead className="text-right">
                {renderSortHeader('fulfillment_rate', 'Полнота')}
              </TableHead>
              <TableHead className="text-right">
                {renderSortHeader('defect_rate', 'Брак %')}
              </TableHead>
              <TableHead className="text-right">
                {renderSortHeader('avg_lead_time_days', 'Ср. срок')}
              </TableHead>
              <TableHead className="text-center">
                {renderSortHeader('reliability_score', 'Надежность')}
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.map((item) => {
              const tier = tierMeta[item.reliability_tier] ?? {
                label: item.reliability_tier,
                badgeClass: 'bg-muted text-muted-foreground',
              }

              return (
                <TableRow
                  key={item.supplier_id}
                  className="border-border hover:bg-muted/40 text-xs"
                >
                  <TableCell className="font-medium text-foreground py-3">
                    <div>{item.supplier_name}</div>
                    <div className="text-[10px] text-muted-foreground">
                      {item.on_time_deliveries} в срок / {item.delayed_deliveries} задерж.
                    </div>
                  </TableCell>
                  <TableCell className="font-mono py-3">
                    {numberFormatter.format(item.total_deliveries)}
                  </TableCell>
                  <TableCell className="text-right font-medium text-foreground py-3">
                    {currencyFormatter.format(item.total_spend)}
                  </TableCell>
                  <TableCell className="text-right py-3">
                    <span
                      className={`font-semibold ${
                        item.on_time_rate >= 90
                          ? 'text-emerald-600 dark:text-emerald-400'
                          : item.on_time_rate >= 80
                            ? 'text-teal-600 dark:text-teal-400'
                            : 'text-amber-600 dark:text-amber-400'
                      }`}
                    >
                      {item.on_time_rate}%
                    </span>
                  </TableCell>
                  <TableCell className="text-right font-medium text-foreground py-3">
                    {item.fulfillment_rate}%
                  </TableCell>
                  <TableCell className="text-right py-3">
                    <span
                      className={
                        item.defect_rate > 1
                          ? 'text-amber-600 font-semibold'
                          : 'text-muted-foreground'
                      }
                    >
                      {item.defect_rate}%
                    </span>
                  </TableCell>
                  <TableCell className="text-right py-3 text-muted-foreground">
                    {item.avg_lead_time_days} дн.
                  </TableCell>
                  <TableCell className="text-center py-3">
                    <div className="flex items-center justify-center gap-1.5">
                      <span className="font-mono text-xs">{item.reliability_score}</span>
                      <Badge
                        variant="outline"
                        className={`text-[10px] px-1.5 py-0 ${tier.badgeClass}`}
                      >
                        {tier.label}
                      </Badge>
                    </div>
                  </TableCell>
                </TableRow>
              )
            })}

            {items.length === 0 && (
              <TableRow>
                <TableCell
                  colSpan={8}
                  className="h-32 text-center text-xs text-muted-foreground"
                >
                  Поставщики не найдены
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
            Страница {pagination.page} из {pagination.total_pages}
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
