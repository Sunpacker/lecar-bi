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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { AlertRule } from '../api/alerts-gateway'
import { AlertCircle, AlertTriangle, Info, Play, Trash2, Power } from 'lucide-react'

interface AlertRuleListProps {
  rules: AlertRule[]
  isLoading?: boolean
  isEvaluating?: boolean
  onToggleRule: (ruleId: string) => Promise<void>
  onDeleteRule: (ruleId: string) => Promise<void>
  onEvaluate: () => Promise<void>
  onOpenCreate: () => void
  canManageRules?: boolean
}

export function AlertRuleList({
  rules,
  isLoading,
  isEvaluating,
  onToggleRule,
  onDeleteRule,
  onEvaluate,
  onOpenCreate,
  canManageRules = true,
}: AlertRuleListProps) {
  const getRuleTypeLabel = (type: string) => {
    switch (type) {
      case 'critical_stock':
        return 'Критический дефицит'
      case 'out_of_stock':
        return 'Аут-оф-сток'
      case 'overstock':
        return 'Залежалый товар'
      case 'reorder_point':
        return 'Точка заказа'
      default:
        return type
    }
  }

  const getMetricLabel = (metric: string) => {
    switch (metric) {
      case 'days_of_stock':
        return 'Обеспеченность (дней)'
      case 'quantity_available':
        return 'Остаток доступно (шт)'
      case 'inventory_value':
        return 'Стоимость (руб)'
      default:
        return metric
    }
  }

  const getComparatorSymbol = (comparator: string) => {
    switch (comparator) {
      case 'lt':
        return '<'
      case 'lte':
        return '<='
      case 'gt':
        return '>'
      case 'gte':
        return '>='
      case 'eq':
        return '='
      default:
        return comparator
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-base font-semibold text-foreground">Правила мониторинга</h3>
          <p className="text-xs text-muted-foreground">
            Условия автоматического триггера инцидентов по складским остаткам
          </p>
        </div>

        {canManageRules && (
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={onEvaluate}
              disabled={isEvaluating}
              className="text-xs"
            >
              <Play
                className={`h-3.5 w-3.5 mr-1.5 ${isEvaluating ? 'animate-spin' : ''}`}
              />
              {isEvaluating ? 'Оценка...' : 'Запустить оценку'}
            </Button>

            <Button size="sm" onClick={onOpenCreate} className="text-xs">
              + Создать правило
            </Button>
          </div>
        )}
      </div>

      <div className="rounded-md border border-border bg-card overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Правило</TableHead>
              <TableHead>Тип</TableHead>
              <TableHead>Условие</TableHead>
              <TableHead>Важность</TableHead>
              <TableHead>Склад</TableHead>
              <TableHead>Статус</TableHead>
              <TableHead className="text-right">Действия</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={7} className="h-32 text-center text-muted-foreground">
                  Загрузка правил...
                </TableCell>
              </TableRow>
            ) : rules.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="h-32 text-center text-muted-foreground">
                  Нет настроенных правил мониторинга
                </TableCell>
              </TableRow>
            ) : (
              rules.map((rule) => (
                <TableRow key={rule.id} data-testid={`rule-row-${rule.id}`}>
                  <TableCell className="space-y-0.5">
                    <div className="font-medium text-foreground text-sm">{rule.name}</div>
                    {rule.description && (
                      <div className="text-xs text-muted-foreground line-clamp-1">
                        {rule.description}
                      </div>
                    )}
                  </TableCell>
                  <TableCell className="text-xs">
                    <Badge variant="outline">{getRuleTypeLabel(rule.rule_type)}</Badge>
                  </TableCell>
                  <TableCell className="text-xs font-mono">
                    {getMetricLabel(rule.metric)}{' '}
                    <span className="font-bold text-foreground">
                      {getComparatorSymbol(rule.comparator)} {rule.threshold_value}
                    </span>
                  </TableCell>
                  <TableCell>
                    {rule.severity === 'critical' ? (
                      <Badge
                        variant="destructive"
                        className="flex items-center gap-1 w-fit"
                      >
                        <AlertCircle className="h-3 w-3" />
                        Критический
                      </Badge>
                    ) : rule.severity === 'warning' ? (
                      <Badge className="bg-amber-500/20 text-amber-500 border border-amber-500/30 flex items-center gap-1 w-fit">
                        <AlertTriangle className="h-3 w-3" />
                        Внимание
                      </Badge>
                    ) : (
                      <Badge
                        variant="outline"
                        className="text-blue-400 border-blue-500/30 flex items-center gap-1 w-fit"
                      >
                        <Info className="h-3 w-3" />
                        Инфо
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-xs text-muted-foreground">
                    {rule.warehouse_id ?? 'Все склады'}
                  </TableCell>
                  <TableCell>
                    {rule.is_enabled ? (
                      <Badge
                        variant="outline"
                        className="border-emerald-500/40 text-emerald-500"
                      >
                        Активно
                      </Badge>
                    ) : (
                      <Badge variant="secondary" className="text-muted-foreground">
                        Отключено
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right">
                    {canManageRules ? (
                      <div className="flex items-center justify-end gap-1.5">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => onToggleRule(rule.id)}
                          title={
                            rule.is_enabled ? 'Отключить правило' : 'Включить правило'
                          }
                          className={`h-8 px-2 text-xs ${
                            rule.is_enabled
                              ? 'text-emerald-500 hover:text-emerald-600'
                              : 'text-muted-foreground'
                          }`}
                        >
                          <Power className="h-3.5 w-3.5 mr-1" />
                          {rule.is_enabled ? 'Вкл' : 'Выкл'}
                        </Button>

                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => onDeleteRule(rule.id)}
                          title="Удалить правило"
                          className="h-8 px-2 text-xs text-rose-500 hover:text-rose-600 hover:bg-rose-500/10"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    ) : (
                      <span className="text-muted-foreground text-xs">—</span>
                    )}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}
