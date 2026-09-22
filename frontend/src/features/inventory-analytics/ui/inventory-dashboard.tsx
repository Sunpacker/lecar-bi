'use client'

import React, { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'next/navigation'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  inventoryGateway,
  type InventoryFilterOptionsResponse,
  type InventoryItemsResponse,
  type InventorySummaryResponse,
} from '../api/inventory-gateway'
import { InventoryKpiCards } from './inventory-kpi-cards'
import { InventoryHealthBreakdown } from './inventory-health-breakdown'
import { InventoryWarehouseBreakdown } from './inventory-warehouse-breakdown'
import { InventoryFiltersBar } from './inventory-filters-bar'
import { InventoryItemsTable } from './inventory-items-table'

interface InventoryDashboardProps {
  userId: string
  workspaceId: string
}

export function InventoryDashboard({ userId, workspaceId }: InventoryDashboardProps) {
  const searchParams = useSearchParams()

  const [filterOptions, setFilterOptions] =
    useState<InventoryFilterOptionsResponse | null>(null)

  // Filters state from URL or defaults
  const [warehouseId, setWarehouseId] = useState<string | null>(() => {
    return searchParams.get('warehouse_id') || null
  })

  const [stockHealth, setStockHealth] = useState<string | null>(() => {
    return searchParams.get('stock_health') || null
  })

  const [search, setSearch] = useState<string>(() => {
    return searchParams.get('search') || ''
  })

  const [page, setPage] = useState<number>(() => {
    const p = searchParams.get('page')
    return p ? Math.max(1, Number(p)) : 1
  })

  const [sortBy, setSortBy] = useState<string>(() => {
    return searchParams.get('sort_by') ?? 'quantity_available'
  })

  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>(() => {
    return searchParams.get('sort_direction') === 'desc' ? 'desc' : 'asc'
  })

  const [summary, setSummary] = useState<InventorySummaryResponse | null>(null)
  const [itemsData, setItemsData] = useState<InventoryItemsResponse | null>(null)
  const [loadingSummary, setLoadingSummary] = useState(true)
  const [loadingItems, setLoadingItems] = useState(true)
  const [error, setError] = useState<string | null>(null)

  // Sync state to URL
  const updateUrlParams = useCallback(
    (
      newWarehouseId: string | null,
      newStockHealth: string | null,
      newSearch: string,
      newPage: number,
      newSortBy: string,
      newSortDirection: 'asc' | 'desc',
    ) => {
      if (typeof window === 'undefined') return

      const params = new URLSearchParams(window.location.search)

      if (newWarehouseId) params.set('warehouse_id', newWarehouseId)
      else params.delete('warehouse_id')

      if (newStockHealth) params.set('stock_health', newStockHealth)
      else params.delete('stock_health')

      if (newSearch.trim()) params.set('search', newSearch.trim())
      else params.delete('search')

      if (newPage > 1) params.set('page', String(newPage))
      else params.delete('page')

      if (newSortBy !== 'quantity_available') params.set('sort_by', newSortBy)
      else params.delete('sort_by')

      if (newSortDirection !== 'asc') params.set('sort_direction', newSortDirection)
      else params.delete('sort_direction')

      const queryString = params.toString()
      const newUrl = `${window.location.pathname}${queryString ? `?${queryString}` : ''}`
      window.history.replaceState(null, '', newUrl)
    },
    [],
  )

  // Load filter options on mount
  useEffect(() => {
    let isCancelled = false

    async function loadOptions() {
      try {
        const options = await inventoryGateway.getFilters(userId, workspaceId)
        if (!isCancelled) {
          setFilterOptions(options)
        }
      } catch (err) {
        if (!isCancelled) {
          setError(
            err instanceof Error
              ? err.message
              : 'Не удалось загрузить параметры фильтрации',
          )
        }
      }
    }

    loadOptions()
    return () => {
      isCancelled = true
    }
  }, [userId, workspaceId])

  // Load summary
  const fetchSummary = useCallback(async () => {
    setLoadingSummary(true)
    setError(null)
    try {
      const data = await inventoryGateway.getSummary(userId, workspaceId, {
        warehouseId: warehouseId || undefined,
      })
      setSummary(data)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось загрузить сводку запасов')
    } finally {
      setLoadingSummary(false)
    }
  }, [userId, workspaceId, warehouseId])

  // Load items
  const fetchItems = useCallback(async () => {
    setLoadingItems(true)
    try {
      const data = await inventoryGateway.getItems(userId, workspaceId, {
        warehouseId: warehouseId || undefined,
        stockHealth: (stockHealth as any) || undefined,
        search: search.trim() || undefined,
        page,
        perPage: 20,
        sortBy: sortBy as any,
        sortDirection,
      })
      setItemsData(data)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось загрузить список товаров')
    } finally {
      setLoadingItems(false)
    }
  }, [userId, workspaceId, warehouseId, stockHealth, search, page, sortBy, sortDirection])

  useEffect(() => {
    let ignore = false
    void Promise.resolve().then(() => {
      if (!ignore) {
        void fetchSummary()
      }
    })
    return () => {
      ignore = true
    }
  }, [fetchSummary])

  useEffect(() => {
    let ignore = false
    void Promise.resolve().then(() => {
      if (!ignore) {
        void fetchItems()
      }
    })
    return () => {
      ignore = true
    }
  }, [fetchItems])

  const handleWarehouseChange = (newWarehouseId: string | null) => {
    setWarehouseId(newWarehouseId)
    setPage(1)
    updateUrlParams(newWarehouseId, stockHealth, search, 1, sortBy, sortDirection)
  }

  const handleStockHealthChange = (newStatus: string | null) => {
    setStockHealth(newStatus)
    setPage(1)
    updateUrlParams(warehouseId, newStatus, search, 1, sortBy, sortDirection)
  }

  const handleSearchChange = (newSearch: string) => {
    setSearch(newSearch)
    setPage(1)
    updateUrlParams(warehouseId, stockHealth, newSearch, 1, sortBy, sortDirection)
  }

  const handleSort = (columnKey: string) => {
    let newDirection: 'asc' | 'desc' = 'asc'
    if (sortBy === columnKey) {
      newDirection = sortDirection === 'asc' ? 'desc' : 'asc'
    } else {
      newDirection =
        columnKey === 'days_of_stock' || columnKey === 'product_name' ? 'asc' : 'desc'
    }
    setSortBy(columnKey)
    setSortDirection(newDirection)
    setPage(1)
    updateUrlParams(warehouseId, stockHealth, search, 1, columnKey, newDirection)
  }

  const handlePageChange = (newPage: number) => {
    setPage(newPage)
    updateUrlParams(warehouseId, stockHealth, search, newPage, sortBy, sortDirection)
  }

  const handleResetFilters = () => {
    setWarehouseId(null)
    setStockHealth(null)
    setSearch('')
    setPage(1)
    setSortBy('quantity_available')
    setSortDirection('asc')
    updateUrlParams(null, null, '', 1, 'quantity_available', 'asc')
  }

  // Initial loading state skeleton
  if (loadingSummary && !summary) {
    return (
      <div data-testid="inventory-loading-skeleton" className="space-y-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Skeleton className="h-28 rounded-xl" />
          <Skeleton className="h-28 rounded-xl" />
          <Skeleton className="h-28 rounded-xl" />
          <Skeleton className="h-28 rounded-xl" />
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <Skeleton className="h-44 rounded-xl" />
          <Skeleton className="h-44 rounded-xl" />
        </div>
        <Skeleton className="h-12 rounded-xl" />
        <Skeleton className="h-96 rounded-xl" />
      </div>
    )
  }

  if (error && !summary) {
    return (
      <Card className="border-destructive/40 bg-destructive/5 my-6">
        <CardHeader>
          <CardTitle className="text-destructive font-semibold text-base">
            Ошибка загрузки аналитики запасов
          </CardTitle>
          <CardDescription className="text-muted-foreground text-xs">
            {error}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              fetchSummary()
              fetchItems()
            }}
          >
            Повторить попытку
          </Button>
        </CardContent>
      </Card>
    )
  }

  if (!summary) {
    return null
  }

  return (
    <div className="space-y-6">
      {/* Date snapshot indicator */}
      <div className="flex items-center justify-between text-xs text-muted-foreground">
        <span>
          Аналитический срез на дату:{' '}
          <strong className="text-foreground font-semibold">{summary.as_of_date}</strong>
        </span>
        {summary.summary.average_days_of_stock !== null && (
          <span>
            Средняя обеспеченность (DOS):{' '}
            <strong className="text-foreground font-semibold">
              {summary.summary.average_days_of_stock} дн.
            </strong>
          </span>
        )}
      </div>

      {/* KPI Cards */}
      <InventoryKpiCards summary={summary.summary} />

      {/* Health Breakdown & Warehouse breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
        <InventoryHealthBreakdown
          items={summary.health_breakdown}
          selectedStatus={stockHealth}
          onSelectStatus={handleStockHealthChange}
        />
        <InventoryWarehouseBreakdown
          warehouses={summary.warehouses}
          selectedWarehouseId={warehouseId}
          onSelectWarehouse={handleWarehouseChange}
        />
      </div>

      {/* Filters Bar */}
      {filterOptions && (
        <InventoryFiltersBar
          filterOptions={filterOptions}
          warehouseId={warehouseId}
          stockHealth={stockHealth}
          search={search}
          onWarehouseChange={handleWarehouseChange}
          onStockHealthChange={handleStockHealthChange}
          onSearchChange={handleSearchChange}
          onReset={handleResetFilters}
        />
      )}

      {/* Items Table */}
      {itemsData && (
        <InventoryItemsTable
          items={itemsData.items}
          pagination={itemsData.pagination}
          sortBy={sortBy}
          sortDirection={sortDirection}
          loading={loadingItems}
          onSort={handleSort}
          onPageChange={handlePageChange}
        />
      )}
    </div>
  )
}
