'use client'

import type { ReactNode } from 'react'
import {
  ArrowDownIcon,
  ArrowUpDownIcon,
  ArrowUpIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { cn } from '@/lib/utils'

export interface DataTableColumn<T> {
  key: string
  label: string
  render: (row: T) => ReactNode
  sortable?: boolean
  align?: 'left' | 'right'
}
export interface DataTableSort {
  key: string
  direction: 'asc' | 'desc'
}
export interface DataTablePagination {
  page: number
  pageSize: number
  total: number
  totalPages: number
}
export interface DataTableProps<T> {
  rows: readonly T[]
  columns: readonly DataTableColumn<T>[]
  getRowKey: (row: T) => string | number
  sort?: DataTableSort
  onSortChange?: (sort: DataTableSort) => void
  pagination?: DataTablePagination
  onPageChange?: (page: number) => void
  loading?: boolean
  emptyMessage?: string
  className?: string
}

export function DataTable<T>({
  rows,
  columns,
  getRowKey,
  sort,
  onSortChange,
  pagination,
  onPageChange,
  loading = false,
  emptyMessage = 'Нет данных',
  className,
}: DataTableProps<T>) {
  const start =
    pagination && pagination.total > 0
      ? (pagination.page - 1) * pagination.pageSize + 1
      : 0
  const end = pagination
    ? Math.min(pagination.page * pagination.pageSize, pagination.total)
    : 0

  function changeSort(key: string) {
    if (!onSortChange) return
    onSortChange({
      key,
      direction:
        sort?.key === key && sort.direction === 'asc'
          ? 'desc'
          : sort?.key === key
            ? 'asc'
            : 'desc',
    })
  }

  return (
    <div className={cn('min-w-0 space-y-4', className)}>
      <Table>
        <TableHeader>
          <TableRow className="border-border hover:bg-transparent">
            {columns.map((column) => {
              const active = sort?.key === column.key
              return (
                <TableHead
                  key={column.key}
                  aria-sort={
                    column.sortable
                      ? active
                        ? sort.direction === 'asc'
                          ? 'ascending'
                          : 'descending'
                        : 'none'
                      : undefined
                  }
                  className={cn(
                    'h-9 px-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground',
                    column.align === 'right' && 'text-right',
                  )}
                >
                  {column.sortable && onSortChange ? (
                    <Button
                      type="button"
                      variant="ghost"
                      size="xs"
                      disabled={loading}
                      onClick={() => changeSort(column.key)}
                      className={cn(
                        'h-7 gap-1 px-1.5 text-xs font-semibold uppercase tracking-wider',
                        active
                          ? 'text-emerald-700 dark:text-emerald-400'
                          : 'text-muted-foreground',
                      )}
                    >
                      {column.label}
                      {active ? (
                        sort.direction === 'asc' ? (
                          <ArrowUpIcon className="size-3" />
                        ) : (
                          <ArrowDownIcon className="size-3" />
                        )
                      ) : (
                        <ArrowUpDownIcon className="size-3 opacity-50" />
                      )}
                    </Button>
                  ) : (
                    column.label
                  )}
                </TableHead>
              )
            })}
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell
                colSpan={columns.length}
                className="py-8 text-center italic text-muted-foreground"
              >
                {emptyMessage}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((row) => (
              <TableRow
                key={getRowKey(row)}
                className="border-border text-xs hover:bg-muted/40"
              >
                {columns.map((column) => (
                  <TableCell
                    key={column.key}
                    className={cn('py-2.5', column.align === 'right' && 'text-right')}
                  >
                    {column.render(row)}
                  </TableCell>
                ))}
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
      {pagination && (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3 text-xs text-muted-foreground">
          <span>
            Записи {start} - {end} из {pagination.total}
          </span>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-7 gap-1 text-xs"
              disabled={loading || pagination.page <= 1 || !onPageChange}
              onClick={() => onPageChange?.(pagination.page - 1)}
            >
              <ChevronLeftIcon className="size-3.5" />
              Назад
            </Button>
            <span className="px-2 font-medium text-foreground">
              {pagination.page} / {pagination.totalPages}
            </span>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-7 gap-1 text-xs"
              disabled={
                loading || pagination.page >= pagination.totalPages || !onPageChange
              }
              onClick={() => onPageChange?.(pagination.page + 1)}
            >
              Вперед
              <ChevronRightIcon className="size-3.5" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
