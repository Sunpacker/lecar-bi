'use client'

import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { DatabaseIcon, FileTextIcon, HelpCircleIcon } from 'lucide-react'
import type { ForecastDataFreshness } from '../api/inventory-gateway'

interface ForecastMetadataProps {
  dataFreshness: ForecastDataFreshness
  asOfDate: string
  generatedAt: string
  horizonDays: number
  assumptions: string[]
}

export function ForecastMetadata({
  dataFreshness,
  asOfDate,
  generatedAt,
  horizonDays,
  assumptions,
}: ForecastMetadataProps) {
  const formattedGenTime = new Date(generatedAt).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
      {/* Freshness Card */}
      <Card className="border-border bg-card shadow-xs">
        <CardHeader className="pb-2">
          <CardTitle className="text-xs font-semibold text-foreground flex items-center gap-1.5 uppercase tracking-wider">
            <DatabaseIcon className="h-3.5 w-3.5 text-muted-foreground" />
            <span>Свежесть исходных данных (as-of)</span>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-xs">
          <div className="flex justify-between items-center py-1 border-b border-border/50">
            <span className="text-muted-foreground">Граница расчета (As of date):</span>
            <span className="font-semibold text-foreground">{asOfDate}</span>
          </div>
          <div className="flex justify-between items-center py-1 border-b border-border/50">
            <span className="text-muted-foreground">
              Последние продажи (Sales dataset):
            </span>
            <span className="font-medium text-foreground">
              {dataFreshness.sales_date ?? 'Не указано'}
            </span>
          </div>
          <div className="flex justify-between items-center py-1 border-b border-border/50">
            <span className="text-muted-foreground">
              Снимок остатков (Inventory dataset):
            </span>
            <span className="font-medium text-foreground">
              {dataFreshness.inventory_date ?? 'Не указано'}
            </span>
          </div>
          <div className="flex justify-between items-center py-1 border-b border-border/50">
            <span className="text-muted-foreground">
              Журнал поставок (Suppliers dataset):
            </span>
            <span className="font-medium text-foreground">
              {dataFreshness.supplier_date ?? 'Не указано'}
            </span>
          </div>
          <div className="flex justify-between items-center pt-1 text-[11px] text-muted-foreground">
            <span>Время генерации прогноза:</span>
            <span>{formattedGenTime}</span>
          </div>
        </CardContent>
      </Card>

      {/* Assumptions Card */}
      <Card className="border-border bg-card shadow-xs">
        <CardHeader className="pb-2">
          <CardTitle className="text-xs font-semibold text-foreground flex items-center gap-1.5 uppercase tracking-wider">
            <FileTextIcon className="h-3.5 w-3.5 text-muted-foreground" />
            <span>Методологические допущения</span>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-xs">
          <div className="text-muted-foreground mb-2 flex items-center gap-1 text-[11px]">
            <HelpCircleIcon className="h-3 w-3" />
            <span>Горизонт прогнозирования: {horizonDays} календарных дней</span>
          </div>
          <ul className="list-disc list-inside space-y-1 text-muted-foreground text-xs">
            {assumptions.map((assumption, index) => (
              <li key={index} className="leading-relaxed">
                <span className="text-foreground">{assumption}</span>
              </li>
            ))}
          </ul>
        </CardContent>
      </Card>
    </div>
  )
}
