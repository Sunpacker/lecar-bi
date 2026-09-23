'use client'

import React from 'react'
import Link from 'next/link'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import type { Alert, AlertSeverity, AlertStatus } from '../api/alerts-gateway'
import {
  AlertCircle,
  AlertTriangle,
  Info,
  CheckCircle2,
  ExternalLink,
  Clock,
  Check,
} from 'lucide-react'

interface AlertTableProps {
  alerts: Alert[]
  isLoading?: boolean
  onAcknowledge: (alertId: string) => Promise<void>
  onOpenResolve: (alert: Alert) => void
  statusFilter: string
  onStatusChange: (status: string) => void
  severityFilter?: string
  onSeverityChange: (severity?: string) => void
  canManageAlerts?: boolean
}

export function AlertTable({
  alerts,
  isLoading,
  onAcknowledge,
  onOpenResolve,
  statusFilter,
  onStatusChange,
  severityFilter,
  onSeverityChange,
  canManageAlerts = true,
}: AlertTableProps) {
  const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })

  const getSeverityBadge = (severity: AlertSeverity) => {
    switch (severity) {
      case 'critical':
        return (
          <Badge variant="destructive" className="flex items-center gap-1 font-medium">
            <AlertCircle className="h-3 w-3" />
            Критический
          </Badge>
        )
      case 'warning':
        return (
          <Badge className="bg-amber-500/20 text-amber-500 border border-amber-500/30 flex items-center gap-1 font-medium">
            <AlertTriangle className="h-3 w-3" />
            Внимание
          </Badge>
        )
      case 'info':
        return (
          <Badge
            variant="outline"
            className="text-blue-400 border-blue-500/30 flex items-center gap-1 font-medium"
          >
            <Info className="h-3 w-3" />
            Инфо
          </Badge>
        )
      default:
        return <Badge variant="secondary">{severity}</Badge>
    }
  }

  const getStatusBadge = (status: AlertStatus) => {
    switch (status) {
      case 'open':
        return (
          <Badge
            variant="outline"
            className="border-rose-500/40 text-rose-500 flex items-center gap-1"
          >
            <Clock className="h-3 w-3" />
            Новый
          </Badge>
        )
      case 'acknowledged':
        return (
          <Badge
            variant="outline"
            className="border-blue-500/40 text-blue-500 flex items-center gap-1"
          >
            <Check className="h-3 w-3" />В работе
          </Badge>
        )
      case 'resolved':
        return (
          <Badge
            variant="outline"
            className="border-emerald-500/40 text-emerald-500 flex items-center gap-1"
          >
            <CheckCircle2 className="h-3 w-3" />
            Решён
          </Badge>
        )
      default:
        return <Badge variant="secondary">{status}</Badge>
    }
  }

  return (
    <div className="space-y-4">
      {/* Filters bar */}
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-border pb-3">
        <div className="flex flex-wrap items-center gap-1">
          {[
            { id: 'active', label: 'Активные' },
            { id: 'all', label: 'Все' },
            { id: 'open', label: 'Новые' },
            { id: 'acknowledged', label: 'В работе' },
            { id: 'resolved', label: 'Решённые' },
          ].map((tab) => (
            <Button
              key={tab.id}
              variant={statusFilter === tab.id ? 'secondary' : 'ghost'}
              size="sm"
              onClick={() => onStatusChange(tab.id)}
              className="text-xs"
            >
              {tab.label}
            </Button>
          ))}
        </div>

        <div className="flex items-center gap-2">
          <span className="text-xs text-muted-foreground">Важность:</span>
          <select
            value={severityFilter ?? ''}
            onChange={(e) => onSeverityChange(e.target.value || undefined)}
            className="rounded-md border border-input bg-transparent px-2.5 py-1 text-xs shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          >
            <option value="">Все уровни</option>
            <option value="critical">Критический</option>
            <option value="warning">Внимание</option>
            <option value="info">Инфо</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <div className="rounded-md border border-border bg-card overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[120px]">Важность</TableHead>
              <TableHead>Алерт и Товар</TableHead>
              <TableHead>Склад</TableHead>
              <TableHead className="text-right">Текущее / Порог</TableHead>
              <TableHead>Время</TableHead>
              <TableHead>Статус</TableHead>
              <TableHead className="text-right">Действия</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={7} className="h-32 text-center text-muted-foreground">
                  Загрузка алертов...
                </TableCell>
              </TableRow>
            ) : alerts.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="h-32 text-center text-muted-foreground">
                  Нет алертов по заданным критериям
                </TableCell>
              </TableRow>
            ) : (
              alerts.map((alert) => {
                const targetSku = alert.product_sku ?? ''
                const warehouseId = alert.warehouse_id ?? ''
                const inventoryLink = `/inventory?warehouse_id=${encodeURIComponent(warehouseId)}&search=${encodeURIComponent(targetSku)}`

                return (
                  <TableRow key={alert.id} data-testid={`alert-row-${alert.id}`}>
                    <TableCell>{getSeverityBadge(alert.severity)}</TableCell>
                    <TableCell className="space-y-0.5">
                      <div className="font-medium text-foreground text-sm">
                        {alert.product_name ?? alert.rule_name}
                      </div>
                      <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        {alert.product_sku && (
                          <span className="font-mono bg-muted/60 px-1 py-0.2 rounded">
                            {alert.product_sku}
                          </span>
                        )}
                        <span>{alert.rule_name}</span>
                      </div>
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground">
                      {alert.warehouse_name ?? alert.warehouse_id ?? 'Все склады'}
                    </TableCell>
                    <TableCell className="text-right text-xs">
                      <span className="font-semibold text-foreground">
                        {alert.current_value !== null ? alert.current_value : '—'}
                      </span>
                      {alert.threshold_value !== null && (
                        <span className="text-muted-foreground ml-1">
                          (порог: {alert.threshold_value})
                        </span>
                      )}
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground whitespace-nowrap">
                      {dateFormatter.format(new Date(alert.triggered_at))}
                    </TableCell>
                    <TableCell>{getStatusBadge(alert.status)}</TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <Link
                          href={inventoryLink}
                          title="Перейти к анализу остатков по товару"
                          className={buttonVariants({
                            variant: 'ghost',
                            size: 'sm',
                            className:
                              'h-8 px-2 text-xs text-muted-foreground hover:text-foreground',
                          })}
                        >
                          <ExternalLink className="h-3.5 w-3.5 mr-1" />
                          Запасы
                        </Link>

                        {/* Кнопка "В работу" */}
                        {canManageAlerts && alert.status === 'open' && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onAcknowledge(alert.id)}
                            className="h-8 px-2.5 text-xs border-blue-500/40 text-blue-500 hover:bg-blue-500/10"
                          >
                            В работу
                          </Button>
                        )}

                        {/* Кнопка "Закрыть" */}
                        {canManageAlerts && alert.status !== 'resolved' && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onOpenResolve(alert)}
                            className="h-8 px-2.5 text-xs border-emerald-500/40 text-emerald-500 hover:bg-emerald-500/10"
                          >
                            Закрыть
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                )
              })
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}
