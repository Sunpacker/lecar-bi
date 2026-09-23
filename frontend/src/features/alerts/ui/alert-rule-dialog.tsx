'use client'

import React, { useState } from 'react'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { CreateAlertRuleRequest } from '../api/alerts-gateway'
import { PlusCircle } from 'lucide-react'

interface AlertRuleDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSave: (payload: CreateAlertRuleRequest) => Promise<void>
  isSubmitting?: boolean
}

export function AlertRuleDialog({
  open,
  onOpenChange,
  onSave,
  isSubmitting,
}: AlertRuleDialogProps) {
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [ruleType, setRuleType] =
    useState<CreateAlertRuleRequest['rule_type']>('critical_stock')
  const [severity, setSeverity] = useState<CreateAlertRuleRequest['severity']>('critical')
  const [metric, setMetric] = useState<CreateAlertRuleRequest['metric']>('days_of_stock')
  const [comparator, setComparator] = useState<CreateAlertRuleRequest['comparator']>('lt')
  const [thresholdValue, setThresholdValue] = useState<string>('7')
  const [warehouseId, setWarehouseId] = useState<string>('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    const parsedThreshold = parseFloat(thresholdValue)
    if (isNaN(parsedThreshold)) return

    await onSave({
      name: name.trim(),
      description: description.trim() || undefined,
      rule_type: ruleType,
      severity,
      metric,
      comparator,
      threshold_value: parsedThreshold,
      warehouse_id: warehouseId.trim() || undefined,
      is_enabled: true,
    })

    // Reset form
    setName('')
    setDescription('')
    setThresholdValue('7')
    setWarehouseId('')
    onOpenChange(false)
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-md p-6 overflow-y-auto">
        <SheetHeader className="mb-4">
          <div className="flex items-center gap-2 text-primary">
            <PlusCircle className="h-5 w-5" />
            <SheetTitle className="text-lg font-semibold">
              Новое правило алертов
            </SheetTitle>
          </div>
          <SheetDescription className="text-xs text-muted-foreground">
            Автоматическое отслеживание аномалий запасов по складам и категориям.
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="rule-name" className="text-sm font-medium">
              Название правила *
            </Label>
            <Input
              id="rule-name"
              required
              placeholder="Например: Дефицит тормозных колодок"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="rule-desc" className="text-sm font-medium">
              Описание
            </Label>
            <Input
              id="rule-desc"
              placeholder="Краткое пояснение для команды"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label className="text-sm font-medium">Тип правила</Label>
              <Select
                value={ruleType}
                onValueChange={(val) =>
                  setRuleType(val as CreateAlertRuleRequest['rule_type'])
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="critical_stock">Критический дефицит</SelectItem>
                  <SelectItem value="out_of_stock">Аут-оф-сток (0)</SelectItem>
                  <SelectItem value="overstock">Залежалый товар</SelectItem>
                  <SelectItem value="reorder_point">Точка перезаказа</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label className="text-sm font-medium">Уровень критичности</Label>
              <Select
                value={severity}
                onValueChange={(val) =>
                  setSeverity(val as CreateAlertRuleRequest['severity'])
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="critical">Критический (Critical)</SelectItem>
                  <SelectItem value="warning">Предупреждение (Warning)</SelectItem>
                  <SelectItem value="info">Инфо (Info)</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label className="text-sm font-medium">Метрика</Label>
              <Select
                value={metric}
                onValueChange={(val) =>
                  setMetric(val as CreateAlertRuleRequest['metric'])
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="days_of_stock">Обеспеченность (дни)</SelectItem>
                  <SelectItem value="quantity_available">
                    Остаток доступно (шт)
                  </SelectItem>
                  <SelectItem value="inventory_value">Стоимость запаса (руб)</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label className="text-sm font-medium">Условие</Label>
              <Select
                value={comparator}
                onValueChange={(val) =>
                  setComparator(val as CreateAlertRuleRequest['comparator'])
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="lt">Меньше (&lt;)</SelectItem>
                  <SelectItem value="lte">Меньше или равно (&le;)</SelectItem>
                  <SelectItem value="gt">Больше (&gt;)</SelectItem>
                  <SelectItem value="gte">Больше или равно (&ge;)</SelectItem>
                  <SelectItem value="eq">Равно (=)</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="threshold-val" className="text-sm font-medium">
              Пороговое значение *
            </Label>
            <Input
              id="threshold-val"
              type="number"
              step="any"
              required
              value={thresholdValue}
              onChange={(e) => setThresholdValue(e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="warehouse-scope" className="text-sm font-medium">
              ID склада (опционально)
            </Label>
            <Input
              id="warehouse-scope"
              placeholder="Оставьте пустым для всех складов"
              value={warehouseId}
              onChange={(e) => setWarehouseId(e.target.value)}
            />
          </div>

          <div className="flex justify-end gap-2 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={isSubmitting}
            >
              Отмена
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? 'Сохранение...' : 'Создать правило'}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  )
}
