'use client'

import React, { useState, useEffect, useCallback } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  inventoryGateway,
  type ForecastResponse,
  type InventoryItem,
} from '../api/inventory-gateway'
import { ForecastQualityBadge } from './forecast-quality-badge'
import { ForecastStockRiskCard } from './forecast-stock-risk-card'
import { ForecastChart } from './forecast-chart'
import { ForecastMetadata } from './forecast-metadata'
import {
  AlertCircleIcon,
  ArrowLeftIcon,
  CalendarIcon,
  RefreshCwIcon,
  TrendingUpIcon,
} from 'lucide-react'

interface ProductForecastViewProps {
  userId: string
  workspaceId: string
  productId?: string
  warehouseId?: string
  onBack?: () => void
}

export function ProductForecastView({
  userId,
  workspaceId,
  productId: initialProductId,
  warehouseId: initialWarehouseId,
  onBack,
}: ProductForecastViewProps) {
  const [selectedProductId, setSelectedProductId] = useState<string>(
    initialProductId ?? '',
  )
  const [selectedWarehouseId, setSelectedWarehouseId] = useState<string>(
    initialWarehouseId ?? '',
  )
  const [horizonDays, setHorizonDays] = useState<7 | 14 | 28>(28)
  const [loading, setLoading] = useState<boolean>(true)
  const [error, setError] = useState<string | null>(null)
  const [forecast, setForecast] = useState<ForecastResponse | null>(null)

  // Fallback product list when none is provided
  const [availableItems, setAvailableItems] = useState<InventoryItem[]>([])

  // Fetch available products for selector if none selected
  useEffect(() => {
    let isCancelled = false

    async function fetchItems() {
      try {
        const res = await inventoryGateway.getItems(userId, workspaceId, { perPage: 25 })
        if (!isCancelled && res.items.length > 0) {
          setAvailableItems(res.items)
          if (!selectedProductId) {
            setSelectedProductId(res.items[0].product_id)
            setSelectedWarehouseId(res.items[0].warehouse_id)
          }
        }
      } catch {
        // ignore error in item listing fallback
      }
    }

    if (!selectedProductId || !selectedWarehouseId) {
      void fetchItems()
    }

    return () => {
      isCancelled = true
    }
  }, [userId, workspaceId, selectedProductId, selectedWarehouseId])

  useEffect(() => {
    if (!selectedProductId || !selectedWarehouseId) return

    let isCancelled = false

    async function fetchForecast() {
      try {
        const data = await inventoryGateway.getForecast(
          userId,
          workspaceId,
          selectedProductId,
          selectedWarehouseId,
          { horizonDays },
        )
        if (!isCancelled) {
          setForecast(data)
          setError(null)
          setLoading(false)
        }
      } catch (err: any) {
        if (!isCancelled) {
          setError(err?.message ?? 'Не удалось загрузить прогноз для выбранного товара.')
          setForecast(null)
          setLoading(false)
        }
      }
    }

    void fetchForecast()

    return () => {
      isCancelled = true
    }
  }, [userId, workspaceId, selectedProductId, selectedWarehouseId, horizonDays])

  const handleReload = useCallback(() => {
    if (!selectedProductId || !selectedWarehouseId) return
    setLoading(true)
    setError(null)
    inventoryGateway
      .getForecast(userId, workspaceId, selectedProductId, selectedWarehouseId, {
        horizonDays,
      })
      .then((data) => {
        setForecast(data)
      })
      .catch((err: any) => {
        setError(err?.message ?? 'Не удалось загрузить прогноз для выбранного товара.')
        setForecast(null)
      })
      .finally(() => setLoading(false))
  }, [userId, workspaceId, selectedProductId, selectedWarehouseId, horizonDays])

  return (
    <div className="space-y-6">
      {/* Top action / navigation bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-2.5">
          {onBack && (
            <Button
              variant="outline"
              size="sm"
              onClick={onBack}
              className="h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
            >
              <ArrowLeftIcon className="h-3.5 w-3.5" />
              Назад
            </Button>
          )}
          <div>
            <h2 className="text-lg font-bold tracking-tight text-foreground flex items-center gap-2">
              <TrendingUpIcon className="h-5 w-5 text-primary" />
              <span>Прогнозирование спроса и рисков</span>
            </h2>
            <p className="text-xs text-muted-foreground">
              Математическое моделирование продаж, вероятностные коридоры и расчет
              исчерпания остатков
            </p>
          </div>
        </div>

        {/* Horizon selector buttons */}
        <div className="flex items-center gap-2 self-start sm:self-auto">
          <div className="flex items-center rounded-lg border border-border bg-card p-1 shadow-xs">
            <span className="text-[11px] text-muted-foreground px-2 flex items-center gap-1">
              <CalendarIcon className="h-3 w-3" />
              Горизонт:
            </span>
            <Button
              variant={horizonDays === 7 ? 'default' : 'ghost'}
              size="sm"
              className="h-7 text-xs px-2.5"
              onClick={() => setHorizonDays(7)}
            >
              7 дней
            </Button>
            <Button
              variant={horizonDays === 14 ? 'default' : 'ghost'}
              size="sm"
              className="h-7 text-xs px-2.5"
              onClick={() => setHorizonDays(14)}
            >
              14 дней
            </Button>
            <Button
              variant={horizonDays === 28 ? 'default' : 'ghost'}
              size="sm"
              className="h-7 text-xs px-2.5"
              onClick={() => setHorizonDays(28)}
            >
              28 дней
            </Button>
          </div>

          <Button
            variant="outline"
            size="sm"
            onClick={handleReload}
            disabled={loading}
            className="h-9 px-3 text-xs"
            title="Обновить прогноз"
          >
            <RefreshCwIcon className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
          </Button>
        </div>
      </div>

      {/* Product selector if multiple items available */}
      {availableItems.length > 1 && (
        <Card className="border-border bg-card/60 shadow-xs">
          <CardContent className="p-3 flex flex-wrap items-center gap-2">
            <span className="text-xs text-muted-foreground font-medium">
              Товар для анализа:
            </span>
            <select
              aria-label="Товар для анализа"
              value={`${selectedProductId}:${selectedWarehouseId}`}
              onChange={(e) => {
                const [pid, wid] = e.target.value.split(':')
                setSelectedProductId(pid)
                setSelectedWarehouseId(wid)
              }}
              className="text-xs rounded-md border border-border bg-background px-2.5 py-1.5 text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
            >
              {availableItems.map((item) => (
                <option
                  key={`${item.product_id}:${item.warehouse_id}`}
                  value={`${item.product_id}:${item.warehouse_id}`}
                >
                  {item.product_name} ({item.product_sku}) — {item.warehouse_name}
                </option>
              ))}
            </select>
          </CardContent>
        </Card>
      )}

      {/* Content states */}
      {loading && !forecast ? (
        <div className="space-y-4">
          <Skeleton className="h-16 w-full rounded-lg" />
          <Skeleton className="h-44 w-full rounded-lg" />
          <Skeleton className="h-80 w-full rounded-lg" />
        </div>
      ) : error ? (
        <Card className="border-destructive/30 bg-destructive/5 shadow-xs">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-semibold text-destructive flex items-center gap-2">
              <AlertCircleIcon className="h-4 w-4" />
              <span>Прогноз не сформирован</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-muted-foreground space-y-2">
            <p>{error}</p>
            <p className="text-[11px]">
              Возможные причины: для данной пары товар-склад еще не запускался фоновый
              расчет прогноза, либо ряд продаж пуст.
            </p>
          </CardContent>
        </Card>
      ) : forecast ? (
        <div className="space-y-5">
          {/* Quality & Model Status Badge */}
          <ForecastQualityBadge
            status={forecast.status}
            statusReason={forecast.status_reason}
            modelMethod={forecast.model_method}
            modelVersion={forecast.model_version}
            qualityMetrics={forecast.quality_metrics}
          />

          {/* Stock Risk Modeling Card */}
          <ForecastStockRiskCard
            stockRisk={forecast.stock_risk}
            asOfDate={forecast.as_of_date}
          />

          {/* Interactive Forecast Chart */}
          <ForecastChart
            history={forecast.measured_history}
            forecast={forecast.points}
            asOfDate={forecast.as_of_date}
            productName={forecast.product_name}
            horizonDays={forecast.horizon_days}
          />

          {/* Metadata & Assumptions */}
          <ForecastMetadata
            dataFreshness={forecast.data_freshness}
            asOfDate={forecast.as_of_date}
            generatedAt={forecast.generated_at}
            horizonDays={forecast.horizon_days}
            assumptions={forecast.assumptions}
          />
        </div>
      ) : null}
    </div>
  )
}
