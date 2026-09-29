'use client'

import { Badge } from '@/components/ui/badge'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { DataTable, type DataTableColumn } from '@/src/shared/ui'
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

function StatusBadge({ status }: { status: string }) {
  if (status === 'completed' || status === 'delivered')
    return (
      <Badge
        variant="outline"
        className="border-emerald-800/40 bg-emerald-950/20 text-[11px] capitalize text-emerald-400"
      >
        {status}
      </Badge>
    )
  if (status === 'pending' || status === 'processing')
    return (
      <Badge
        variant="outline"
        className="border-amber-800/40 bg-amber-950/20 text-[11px] capitalize text-amber-400"
      >
        {status}
      </Badge>
    )
  return (
    <Badge
      variant={status === 'cancelled' ? 'destructive' : 'secondary'}
      className="text-[11px] capitalize"
    >
      {status}
    </Badge>
  )
}

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
  const columns: DataTableColumn<SalesRecordItem>[] = [
    {
      key: 'order_date',
      label: 'Дата',
      sortable: true,
      render: (item) => (
        <span className="text-nowrap font-medium">{item.order_date}</span>
      ),
    },
    {
      key: 'order_number',
      label: '№ Заказа',
      sortable: true,
      render: (item) => <span className="font-mono">{item.order_number}</span>,
    },
    {
      key: 'product_name',
      label: 'Товар',
      sortable: true,
      render: (item) => (
        <div className="flex flex-col gap-0.5">
          <span className="font-medium text-foreground">{item.product_name}</span>
          <span className="text-[11px] text-muted-foreground">
            {item.brand_name} · {item.product_sku}
          </span>
        </div>
      ),
    },
    {
      key: 'category',
      label: 'Категория',
      render: (item) => (
        <Badge variant="secondary" className="text-[11px] font-normal">
          {item.category_name}
        </Badge>
      ),
    },
    {
      key: 'region',
      label: 'Регион',
      render: (item) => (
        <Badge variant="outline" className="text-[11px] font-normal">
          {item.region_name}
        </Badge>
      ),
    },
    {
      key: 'quantity',
      label: 'Кол-во',
      sortable: true,
      align: 'right',
      render: (item) => <span className="font-medium">{item.quantity}</span>,
    },
    {
      key: 'total_price',
      label: 'Сумма',
      sortable: true,
      align: 'right',
      render: (item) => (
        <span className="font-medium">{currencyFormatter.format(item.total_price)}</span>
      ),
    },
    {
      key: 'gross_profit',
      label: 'Прибыль',
      sortable: true,
      align: 'right',
      render: (item) => (
        <span className="font-medium text-emerald-400">
          {currencyFormatter.format(item.gross_profit)}
        </span>
      ),
    },
    {
      key: 'status',
      label: 'Статус',
      render: (item) => <StatusBadge status={item.status} />,
    },
  ]

  return (
    <Card className="border-border bg-card shadow-xs" data-testid="sales-detail-table">
      <CardHeader className="pb-3">
        <CardTitle className="text-base font-semibold text-foreground">
          Детализация продаж
        </CardTitle>
        <CardDescription className="mt-1 text-xs">
          Транзакционные записи по выбранным фильтрам ({pagination.total} позиций)
        </CardDescription>
      </CardHeader>
      <CardContent>
        <DataTable
          rows={items}
          columns={columns}
          getRowKey={(item) => item.id}
          sort={{ key: sortBy, direction: sortDirection }}
          onSortChange={(sort) => onSortChange(sort.key, sort.direction)}
          pagination={{
            page: pagination.page,
            pageSize: pagination.per_page,
            total: pagination.total,
            totalPages: pagination.total_pages,
          }}
          onPageChange={onPageChange}
          loading={loading}
          emptyMessage="Нет записей по текущим фильтрам"
        />
      </CardContent>
    </Card>
  )
}
