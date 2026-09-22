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
import type { WidgetDetail } from '../api/dashboard-gateway'
import type { WidgetFormValues } from '../model/use-dashboard-builder'

interface WidgetConfigSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  editingWidget: WidgetDetail | null
  onSave: (values: WidgetFormValues) => void
}

const DATASET_METRICS: Record<
  'sales' | 'inventory',
  { value: WidgetFormValues['metric']; label: string }[]
> = {
  sales: [
    { value: 'revenue', label: 'Выручка (руб)' },
    { value: 'order_count', label: 'Количество заказов' },
    { value: 'average_order_value', label: 'Средний чек' },
    { value: 'gross_profit', label: 'Валовая прибыль' },
    { value: 'margin_rate', label: 'Рентабельность (%)' },
  ],
  inventory: [
    { value: 'stock_quantity', label: 'Остаток (шт)' },
    { value: 'stock_value', label: 'Стоимость запасов (руб)' },
    { value: 'out_of_stock_count', label: 'Позиции без остатка' },
    { value: 'overstock_count', label: 'Позиции в избытке' },
  ],
}

const DATASET_DIMENSIONS: Record<
  'sales' | 'inventory',
  { value: NonNullable<WidgetFormValues['dimension']>; label: string }[]
> = {
  sales: [
    { value: 'date', label: 'По датам (динамика)' },
    { value: 'category', label: 'По категориям' },
    { value: 'region', label: 'По регионам' },
  ],
  inventory: [
    { value: 'warehouse', label: 'По складам' },
    { value: 'abc_class', label: 'По ABC-классам' },
    { value: 'xyz_class', label: 'По XYZ-классам' },
    { value: 'supplier', label: 'По поставщикам' },
  ],
}

const DEFAULT_SIZES: Record<WidgetDetail['type'], { w: number; h: number }> = {
  kpi_card: { w: 3, h: 2 },
  line_chart: { w: 8, h: 4 },
  bar_chart: { w: 6, h: 4 },
  donut_chart: { w: 4, h: 4 },
  table: { w: 12, h: 5 },
}

interface WidgetConfigFormProps {
  editingWidget: WidgetDetail | null
  onSave: (values: WidgetFormValues) => void
  onCancel: () => void
}

function WidgetConfigForm({ editingWidget, onSave, onCancel }: WidgetConfigFormProps) {
  const [title, setTitle] = useState(editingWidget?.title ?? '')
  const [type, setType] = useState<WidgetDetail['type']>(
    editingWidget?.type ?? 'kpi_card',
  )
  const [dataset, setDataset] = useState<'sales' | 'inventory'>(
    (editingWidget?.query_config.dataset as 'sales' | 'inventory') ?? 'sales',
  )
  const [metric, setMetric] = useState<WidgetFormValues['metric']>(
    editingWidget?.query_config.metric ?? 'revenue',
  )
  const [dimension, setDimension] = useState<string>(
    editingWidget?.query_config.dimension ?? 'none',
  )
  const [dateRange, setDateRange] = useState<string>(
    editingWidget?.query_config.date_range ?? '30d',
  )
  const [width, setWidth] = useState<number>(
    editingWidget?.position.w ?? DEFAULT_SIZES[editingWidget?.type ?? 'kpi_card'].w,
  )
  const [height, setHeight] = useState<number>(
    editingWidget?.position.h ?? DEFAULT_SIZES[editingWidget?.type ?? 'kpi_card'].h,
  )
  const [validationError, setValidationError] = useState<string | null>(null)

  const handleDatasetChange = (newDataset: 'sales' | 'inventory') => {
    setDataset(newDataset)
    const firstMetric = DATASET_METRICS[newDataset][0].value
    setMetric(firstMetric)
    if (type === 'line_chart') {
      setDimension(newDataset === 'sales' ? 'date' : 'warehouse')
    } else if (dimension !== 'none') {
      setDimension(DATASET_DIMENSIONS[newDataset][0].value)
    }
  }

  const handleTypeChange = (newType: WidgetDetail['type']) => {
    setType(newType)
    const defaults = DEFAULT_SIZES[newType]
    setWidth(defaults.w)
    setHeight(defaults.h)

    if (newType === 'kpi_card') {
      setDimension('none')
    } else if (newType === 'line_chart') {
      setDimension('date')
    } else if (dimension === 'none') {
      setDimension(DATASET_DIMENSIONS[dataset][0].value)
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!title.trim()) {
      setValidationError('Укажите название виджета')
      return
    }

    onSave({
      title: title.trim(),
      type,
      dataset,
      metric,
      dimension:
        dimension === 'none' ? null : (dimension as WidgetFormValues['dimension']),
      date_range: (dateRange === 'all'
        ? null
        : dateRange) as WidgetFormValues['date_range'],
      w: width,
      h: height,
    })
  }

  return (
    <form onSubmit={handleSubmit} className="mt-6 space-y-4">
      {validationError && (
        <div className="p-3 text-xs rounded-md bg-rose-500/10 border border-rose-500/20 text-rose-400">
          {validationError}
        </div>
      )}

      <div className="space-y-1.5">
        <Label htmlFor="widget-title">Название виджета</Label>
        <Input
          id="widget-title"
          placeholder="Например, Динамика выручки"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          className="h-9 text-sm"
          autoFocus
        />
      </div>

      <div className="space-y-1.5">
        <Label>Тип визуализации</Label>
        <Select
          value={type}
          onValueChange={(val) => {
            if (val) handleTypeChange(val as WidgetDetail['type'])
          }}
        >
          <SelectTrigger className="w-full h-9 text-xs">
            <SelectValue placeholder="Выберите тип" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="kpi_card">KPI карточка (показатель)</SelectItem>
            <SelectItem value="line_chart">Линейный график</SelectItem>
            <SelectItem value="bar_chart">Столбчатая диаграмма</SelectItem>
            <SelectItem value="donut_chart">Круговая диаграмма (donut)</SelectItem>
            <SelectItem value="table">Таблица среза данных</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label>Набор данных</Label>
          <Select
            value={dataset}
            onValueChange={(val) => {
              if (val) handleDatasetChange(val as 'sales' | 'inventory')
            }}
          >
            <SelectTrigger className="w-full h-9 text-xs">
              <SelectValue placeholder="Dataset" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="sales">Продажи (Sales)</SelectItem>
              <SelectItem value="inventory">Склад (Inventory)</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label>Период дат</Label>
          <Select
            value={dateRange}
            onValueChange={(val) => {
              if (val) setDateRange(val)
            }}
          >
            <SelectTrigger className="w-full h-9 text-xs">
              <SelectValue placeholder="Период" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="30d">Последние 30 дней</SelectItem>
              <SelectItem value="90d">Последние 90 дней</SelectItem>
              <SelectItem value="180d">Последние 180 дней</SelectItem>
              <SelectItem value="365d">Последний год</SelectItem>
              <SelectItem value="all">Все время</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="space-y-1.5">
        <Label>Метрика</Label>
        <Select
          value={metric}
          onValueChange={(val) => {
            if (val) setMetric(val as WidgetFormValues['metric'])
          }}
        >
          <SelectTrigger className="w-full h-9 text-xs">
            <SelectValue placeholder="Выберите метрику" />
          </SelectTrigger>
          <SelectContent>
            {DATASET_METRICS[dataset].map((m) => (
              <SelectItem key={m.value} value={m.value}>
                {m.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {type !== 'kpi_card' && (
        <div className="space-y-1.5">
          <Label>Разрез / Измерение (Dimension)</Label>
          <Select
            value={dimension}
            onValueChange={(val) => {
              if (val) setDimension(val)
            }}
          >
            <SelectTrigger className="w-full h-9 text-xs">
              <SelectValue placeholder="Измерение" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">Без детализации</SelectItem>
              {DATASET_DIMENSIONS[dataset].map((d) => (
                <SelectItem key={d.value} value={d.value}>
                  {d.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}

      <div className="grid grid-cols-2 gap-3 pt-2 border-t border-border/50">
        <div className="space-y-1.5">
          <Label htmlFor="widget-w">Ширина (колонок: 1-12)</Label>
          <Input
            id="widget-w"
            type="number"
            min={1}
            max={12}
            value={width}
            onChange={(e) =>
              setWidth(Math.max(1, Math.min(12, Number(e.target.value) || 1)))
            }
            className="h-9 text-xs"
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="widget-h">Высота (строк: 1-12)</Label>
          <Input
            id="widget-h"
            type="number"
            min={1}
            max={12}
            value={height}
            onChange={(e) =>
              setHeight(Math.max(1, Math.min(12, Number(e.target.value) || 1)))
            }
            className="h-9 text-xs"
          />
        </div>
      </div>

      <div className="flex items-center justify-end gap-2 pt-4">
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onCancel}
          className="text-xs"
        >
          Отмена
        </Button>
        <Button
          type="submit"
          size="sm"
          data-testid="submit-widget-btn"
          className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs"
        >
          {editingWidget ? 'Сохранить виджет' : 'Добавить виджет'}
        </Button>
      </div>
    </form>
  )
}

export function WidgetConfigSheet({
  open,
  onOpenChange,
  editingWidget,
  onSave,
}: WidgetConfigSheetProps) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="p-6 overflow-y-auto sm:max-w-md">
        <SheetHeader>
          <SheetTitle>
            {editingWidget ? 'Настройка виджета' : 'Добавить виджет'}
          </SheetTitle>
          <SheetDescription>
            Выберите тип визуализации, источник данных и аналитические параметры.
          </SheetDescription>
        </SheetHeader>

        {open && (
          <WidgetConfigForm
            key={editingWidget?.id ?? 'create-widget'}
            editingWidget={editingWidget}
            onSave={onSave}
            onCancel={() => onOpenChange(false)}
          />
        )}
      </SheetContent>
    </Sheet>
  )
}
