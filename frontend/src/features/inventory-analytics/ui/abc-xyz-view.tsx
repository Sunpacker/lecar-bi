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
import { Badge } from '@/components/ui/badge'
import {
  inventoryGateway,
  type AbcXyzItemsResponse,
  type AbcXyzMatrixCell,
  type AbcXyzSummaryResponse,
  type InventoryFilterOptionsResponse,
} from '../api/inventory-gateway'
import { AbcXyzMatrixGrid } from './abc-xyz-matrix-grid'
import { AbcXyzMethodologyCard } from './abc-xyz-methodology-card'
import { AbcXyzFiltersBar } from './abc-xyz-filters-bar'
import { AbcXyzItemsTable } from './abc-xyz-items-table'

interface AbcXyzViewProps {
  userId: string
  workspaceId: string
}

export function AbcXyzView({ userId, workspaceId }: AbcXyzViewProps) {
  const searchParams = useSearchParams()

  const [filterOptions, setFilterOptions] =
    useState<InventoryFilterOptionsResponse | null>(null)

  // Filters state
  const [periodDays, setPeriodDays] = useState<30 | 90 | 180 | 365>(() => {
    const p = Number(searchParams.get('period_days'))
    if (p === 30 || p === 90 || p === 180 || p === 365) return p
    return 90
  })

  const [warehouseId, setWarehouseId] = useState<string | null>(() => {
    return searchParams.get('warehouse_id') || null
  })

  const [categoryId, setCategoryId] = useState<string | null>(() => {
    return searchParams.get('category_id') || null
  })

  const [supplierId, setSupplierId] = useState<string | null>(() => {
    return searchParams.get('supplier_id') || null
  })

  const [group, setGroup] = useState<string | null>(() => {
    return searchParams.get('group') || null
  })

  const [search, setSearch] = useState<string>(() => {
    return searchParams.get('search') || ''
  })

  const [page, setPage] = useState<number>(() => {
    const p = searchParams.get('page')
    return p ? Math.max(1, Number(p)) : 1
  })

  const [sortBy, setSortBy] = useState<string>(() => {
    return searchParams.get('sort_by') ?? 'total_revenue'
  })

  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>(() => {
    return searchParams.get('sort_direction') === 'asc' ? 'asc' : 'desc'
  })

  const [summaryData, setSummaryData] = useState<AbcXyzSummaryResponse | null>(null)
  const [itemsData, setItemsData] = useState<AbcXyzItemsResponse | null>(null)
  const [loadingSummary, setLoadingSummary] = useState(true)
  const [loadingItems, setLoadingItems] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const currencyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  })

  const numberFormatter = new Intl.NumberFormat('ru-RU')

  // Sync state to URL
  const updateUrlParams = useCallback(
    (
      newPeriod: 30 | 90 | 180 | 365,
      newWarehouseId: string | null,
      newCategoryId: string | null,
      newSupplierId: string | null,
      newGroup: string | null,
      newSearch: string,
      newPage: number,
      newSortBy: string,
      newSortDirection: 'asc' | 'desc',
    ) => {
      if (typeof window === 'undefined') return

      const params = new URLSearchParams(window.location.search)

      if (newPeriod !== 90) params.set('period_days', String(newPeriod))
      else params.delete('period_days')

      if (newWarehouseId) params.set('warehouse_id', newWarehouseId)
      else params.delete('warehouse_id')

      if (newCategoryId) params.set('category_id', newCategoryId)
      else params.delete('category_id')

      if (newSupplierId) params.set('supplier_id', newSupplierId)
      else params.delete('supplier_id')

      if (newGroup) params.set('group', newGroup)
      else params.delete('group')

      if (newSearch.trim()) params.set('search', newSearch.trim())
      else params.delete('search')

      if (newPage > 1) params.set('page', String(newPage))
      else params.delete('page')

      if (newSortBy !== 'total_revenue') params.set('sort_by', newSortBy)
      else params.delete('sort_by')

      if (newSortDirection !== 'desc') params.set('sort_direction', newSortDirection)
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

    void loadOptions()
    return () => {
      isCancelled = true
    }
  }, [userId, workspaceId])

  // Load summary
  const fetchSummary = useCallback(async () => {
    setLoadingSummary(true)
    setError(null)
    try {
      const data = await inventoryGateway.getAbcXyzSummary(userId, workspaceId, {
        periodDays,
        warehouseId: warehouseId || undefined,
        categoryId: categoryId || undefined,
        supplierId: supplierId || undefined,
      })
      setSummaryData(data)
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : 'Не удалось загрузить сводку ABC/XYZ анализа',
      )
    } finally {
      setLoadingSummary(false)
    }
  }, [userId, workspaceId, periodDays, warehouseId, categoryId, supplierId])

  // Load items
  const fetchItems = useCallback(async () => {
    setLoadingItems(true)
    try {
      const data = await inventoryGateway.getAbcXyzItems(userId, workspaceId, {
        periodDays,
        warehouseId: warehouseId || undefined,
        categoryId: categoryId || undefined,
        supplierId: supplierId || undefined,
        group: (group as any) || undefined,
        search: search.trim() || undefined,
        page,
        perPage: 20,
        sortBy: sortBy as any,
        sortDirection,
      })
      setItemsData(data)
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : 'Не удалось загрузить список товаров ABC/XYZ',
      )
    } finally {
      setLoadingItems(false)
    }
  }, [
    userId,
    workspaceId,
    periodDays,
    warehouseId,
    categoryId,
    supplierId,
    group,
    search,
    page,
    sortBy,
    sortDirection,
  ])

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

  const handlePeriodChange = (newPeriod: 30 | 90 | 180 | 365) => {
    setPeriodDays(newPeriod)
    setPage(1)
    updateUrlParams(
      newPeriod,
      warehouseId,
      categoryId,
      supplierId,
      group,
      search,
      1,
      sortBy,
      sortDirection,
    )
  }

  const handleWarehouseChange = (newWarehouseId: string | null) => {
    setWarehouseId(newWarehouseId)
    setPage(1)
    updateUrlParams(
      periodDays,
      newWarehouseId,
      categoryId,
      supplierId,
      group,
      search,
      1,
      sortBy,
      sortDirection,
    )
  }

  const handleCategoryChange = (newCatId: string | null) => {
    setCategoryId(newCatId)
    setPage(1)
    updateUrlParams(
      periodDays,
      warehouseId,
      newCatId,
      supplierId,
      group,
      search,
      1,
      sortBy,
      sortDirection,
    )
  }

  const handleSupplierChange = (newSupId: string | null) => {
    setSupplierId(newSupId)
    setPage(1)
    updateUrlParams(
      periodDays,
      warehouseId,
      categoryId,
      newSupId,
      group,
      search,
      1,
      sortBy,
      sortDirection,
    )
  }

  const handleGroupChange = (newGroup: string | null) => {
    setGroup(newGroup)
    setPage(1)
    updateUrlParams(
      periodDays,
      warehouseId,
      categoryId,
      supplierId,
      newGroup,
      search,
      1,
      sortBy,
      sortDirection,
    )
  }

  const handleSearchChange = (newSearch: string) => {
    setSearch(newSearch)
    setPage(1)
    updateUrlParams(
      periodDays,
      warehouseId,
      categoryId,
      supplierId,
      group,
      newSearch,
      1,
      sortBy,
      sortDirection,
    )
  }

  const handleSort = (columnKey: string) => {
    let newDirection: 'asc' | 'desc' = 'desc'
    if (sortBy === columnKey) {
      newDirection = sortDirection === 'desc' ? 'asc' : 'desc'
    } else {
      newDirection = columnKey === 'product_name' ? 'asc' : 'desc'
    }
    setSortBy(columnKey)
    setSortDirection(newDirection)
    setPage(1)
    updateUrlParams(
      periodDays,
      warehouseId,
      categoryId,
      supplierId,
      group,
      search,
      1,
      columnKey,
      newDirection,
    )
  }

  const handlePageChange = (newPage: number) => {
    setPage(newPage)
    updateUrlParams(
      periodDays,
      warehouseId,
      categoryId,
      supplierId,
      group,
      search,
      newPage,
      sortBy,
      sortDirection,
    )
  }

  const handleResetFilters = () => {
    setPeriodDays(90)
    setWarehouseId(null)
    setCategoryId(null)
    setSupplierId(null)
    setGroup(null)
    setSearch('')
    setPage(1)
    setSortBy('total_revenue')
    setSortDirection('desc')
    updateUrlParams(90, null, null, null, null, '', 1, 'total_revenue', 'desc')
  }

  if (loadingSummary && !summaryData) {
    return (
      <div data-testid="abc-xyz-loading-skeleton" className="space-y-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Skeleton className="h-28 rounded-xl" />
          <Skeleton className="h-28 rounded-xl" />
          <Skeleton className="h-28 rounded-xl" />
          <Skeleton className="h-28 rounded-xl" />
        </div>
        <Skeleton className="h-96 rounded-xl w-full" />
        <Skeleton className="h-64 rounded-xl w-full" />
      </div>
    )
  }

  const summary = summaryData?.data

  return (
    <div className="space-y-6">
      {error && (
        <Card className="border-destructive/50 bg-destructive/10">
          <CardContent className="p-4 flex items-center justify-between text-destructive text-sm">
            <span>{error}</span>
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                void fetchSummary()
                void fetchItems()
              }}
            >
              Повторить попытку
            </Button>
          </CardContent>
        </Card>
      )}

      {/* KPI Overview Cards */}
      {summary && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Card 1: Total items & Revenue */}
          <Card className="border-border/60 bg-card/60 backdrop-blur-xs">
            <CardHeader className="pb-2">
              <CardDescription className="text-xs">
                Всего позиций в анализе
              </CardDescription>
              <CardTitle className="text-2xl font-bold">
                {numberFormatter.format(summary.total_products)}
              </CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              Общая выручка:{' '}
              <span className="font-semibold text-foreground">
                {currencyFormatter.format(summary.total_revenue)}
              </span>
            </CardContent>
          </Card>

          {/* Card 2: ABC Class A share */}
          <Card className="border-border/60 bg-card/60 backdrop-blur-xs">
            <CardHeader className="pb-2">
              <div className="flex items-center justify-between">
                <CardDescription className="text-xs">Класс A (Ключевые)</CardDescription>
                <Badge
                  variant="outline"
                  className="bg-emerald-500/10 text-emerald-400 border-emerald-500/30"
                >
                  {((summary.abc_distribution[0]?.count_share ?? 0) * 100).toFixed(1)}%
                  товаров
                </Badge>
              </div>
              <CardTitle className="text-2xl font-bold text-emerald-400">
                {currencyFormatter.format(summary.abc_distribution[0]?.revenue ?? 0)}
              </CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              Доля в выручке:{' '}
              <span className="font-semibold text-foreground">
                {((summary.abc_distribution[0]?.revenue_share ?? 0) * 100).toFixed(1)}%
              </span>
            </CardContent>
          </Card>

          {/* Card 3: XYZ Class X share */}
          <Card className="border-border/60 bg-card/60 backdrop-blur-xs">
            <CardHeader className="pb-2">
              <div className="flex items-center justify-between">
                <CardDescription className="text-xs">
                  Класс X (Стабильный спрос)
                </CardDescription>
                <Badge
                  variant="outline"
                  className="bg-teal-500/10 text-teal-400 border-teal-500/30"
                >
                  CV &le; 15%
                </Badge>
              </div>
              <CardTitle className="text-2xl font-bold text-teal-400">
                {numberFormatter.format(summary.xyz_distribution[0]?.count ?? 0)}{' '}
                <span className="text-sm font-normal text-muted-foreground">
                  ({((summary.xyz_distribution[0]?.count_share ?? 0) * 100).toFixed(1)}
                  %)
                </span>
              </CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              Выручка стабильного спроса:{' '}
              <span className="font-semibold text-foreground">
                {currencyFormatter.format(summary.xyz_distribution[0]?.revenue ?? 0)}
              </span>
            </CardContent>
          </Card>

          {/* Card 4: Inventory capital at risk (CZ) */}
          <Card className="border-border/60 bg-card/60 backdrop-blur-xs">
            <CardHeader className="pb-2">
              <div className="flex items-center justify-between">
                <CardDescription className="text-xs">
                  Группа CZ (Зона риска)
                </CardDescription>
                <Badge
                  variant="outline"
                  className="bg-rose-500/10 text-rose-400 border-rose-500/30"
                >
                  Неликвиды
                </Badge>
              </div>
              <CardTitle className="text-2xl font-bold text-rose-400">
                {currencyFormatter.format(
                  summary.matrix.find((c: AbcXyzMatrixCell) => c.code === 'CZ')
                    ?.inventory_value ?? 0,
                )}
              </CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              Заморожено в остатках группы CZ
            </CardContent>
          </Card>
        </div>
      )}

      {/* 3x3 Segmentation Matrix */}
      {summary && (
        <AbcXyzMatrixGrid
          matrix={summary.matrix}
          selectedGroup={group}
          onSelectGroup={handleGroupChange}
        />
      )}

      {/* Methodology Explanation */}
      <AbcXyzMethodologyCard />

      {/* Filters Bar */}
      {filterOptions && (
        <AbcXyzFiltersBar
          filterOptions={filterOptions}
          periodDays={periodDays}
          warehouseId={warehouseId}
          categoryId={categoryId}
          supplierId={supplierId}
          selectedGroup={group}
          search={search}
          onPeriodChange={handlePeriodChange}
          onWarehouseChange={handleWarehouseChange}
          onCategoryChange={handleCategoryChange}
          onSupplierChange={handleSupplierChange}
          onGroupChange={handleGroupChange}
          onSearchChange={handleSearchChange}
          onReset={handleResetFilters}
        />
      )}

      {/* Items Table */}
      <AbcXyzItemsTable
        items={itemsData?.items ?? []}
        pagination={
          itemsData?.pagination ?? { page: 1, per_page: 20, total: 0, total_pages: 0 }
        }
        sortBy={sortBy}
        sortDirection={sortDirection}
        loading={loadingItems}
        onSort={handleSort}
        onPageChange={handlePageChange}
      />
    </div>
  )
}
