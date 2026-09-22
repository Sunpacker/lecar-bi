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
  salesGateway,
  type SalesFilterOptions,
  type SalesFilterParams,
  type SalesOverview,
  type SalesRecordsResponse,
} from '../api/sales-gateway'
import { SalesCategoryBreakdownView } from './sales-category-breakdown'
import { SalesFiltersBar } from './sales-filters-bar'
import { SalesKpiCards } from './sales-kpi-cards'
import { SalesRegionalBreakdownView } from './sales-regional-breakdown'
import { SalesTrendChart } from './sales-trend-chart'
import { SalesDetailTable } from './sales-detail-table'

interface SalesDashboardProps {
  userId: string
  workspaceId: string
}

export function SalesDashboard({ userId, workspaceId }: SalesDashboardProps) {
  const searchParams = useSearchParams()

  const [filterOptions, setFilterOptions] = useState<SalesFilterOptions | null>(null)

  // Initialize filters and pagination from URL search params
  const [activeFilters, setActiveFilters] = useState<SalesFilterParams>(() => ({
    dateFrom: searchParams.get('date_from') ?? undefined,
    dateTo: searchParams.get('date_to') ?? undefined,
    categoryId: searchParams.get('category_id') ?? undefined,
    regionId: searchParams.get('region_id') ?? undefined,
  }))

  const [page, setPage] = useState<number>(() => {
    const p = searchParams.get('page')
    return p ? Math.max(1, Number(p)) : 1
  })

  const [sortBy, setSortBy] = useState<string>(() => {
    return searchParams.get('sort_by') ?? 'order_date'
  })

  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>(() => {
    return searchParams.get('sort_direction') === 'asc' ? 'asc' : 'desc'
  })

  const [overview, setOverview] = useState<SalesOverview | null>(null)
  const [records, setRecords] = useState<SalesRecordsResponse | null>(null)
  const [loading, setLoading] = useState<boolean>(true)
  const [error, setError] = useState<string | null>(null)

  // Sync state to URL without full page reload
  const syncUrl = useCallback(
    (filters: SalesFilterParams, p: number, sort: string, sortDir: 'asc' | 'desc') => {
      if (typeof window === 'undefined') return
      const params = new URLSearchParams()
      if (filters.dateFrom) params.set('date_from', filters.dateFrom)
      if (filters.dateTo) params.set('date_to', filters.dateTo)
      if (filters.categoryId) params.set('category_id', filters.categoryId)
      if (filters.regionId) params.set('region_id', filters.regionId)
      if (p > 1) params.set('page', String(p))
      if (sort !== 'order_date') params.set('sort_by', sort)
      if (sortDir !== 'desc') params.set('sort_direction', sortDir)

      const queryString = params.toString()
      const newUrl = queryString
        ? `${window.location.pathname}?${queryString}`
        : window.location.pathname
      window.history.replaceState(null, '', newUrl)
    },
    [],
  )

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      const [optionsRes, overviewRes, recordsRes] = await Promise.all([
        salesGateway.getFilterOptions(userId, workspaceId),
        salesGateway.getOverview(userId, workspaceId, activeFilters),
        salesGateway.getRecords(userId, workspaceId, {
          ...activeFilters,
          page,
          perPage: 10,
          sortBy: sortBy as
            | 'order_date'
            | 'order_number'
            | 'product_name'
            | 'total_price'
            | 'quantity'
            | 'gross_profit',
          sortDirection,
        }),
      ])

      setFilterOptions(optionsRes)
      setOverview(overviewRes)
      setRecords(recordsRes)
      syncUrl(activeFilters, page, sortBy, sortDirection)
    } catch (err: unknown) {
      const message =
        err instanceof Error ? err.message : 'Не удалось загрузить данные аналитики'
      setError(message)
    } finally {
      setLoading(false)
    }
  }, [userId, workspaceId, activeFilters, page, sortBy, sortDirection, syncUrl])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleFilterChange = (newFilters: SalesFilterParams) => {
    setActiveFilters(newFilters)
    setPage(1)
  }

  const handleSelectCategory = (catId?: string) => {
    setActiveFilters((prev) => ({
      ...prev,
      categoryId: catId,
    }))
    setPage(1)
  }

  const handleSelectRegion = (regId?: string) => {
    setActiveFilters((prev) => ({
      ...prev,
      regionId: regId,
    }))
    setPage(1)
  }

  const handleSelectDate = (date?: string) => {
    setActiveFilters((prev) => ({
      ...prev,
      dateFrom: date,
      dateTo: date,
    }))
    setPage(1)
  }

  const handleSortChange = (newSortBy: string, newSortDirection: 'asc' | 'desc') => {
    setSortBy(newSortBy)
    setSortDirection(newSortDirection)
    setPage(1)
  }

  const handlePageChange = (newPage: number) => {
    setPage(newPage)
  }

  const selectedDateValue =
    activeFilters.dateFrom && activeFilters.dateFrom === activeFilters.dateTo
      ? activeFilters.dateFrom
      : undefined

  return (
    <section className="space-y-6" data-testid="sales-dashboard">
      <div className="flex justify-between items-end">
        <div>
          <span className="text-xs font-semibold text-emerald-400 tracking-wider uppercase">
            ОБЗОР / ПРОДАЖИ
          </span>
          <h2 className="text-2xl font-bold tracking-tight text-foreground mt-1">
            Аналитика продаж
          </h2>
        </div>
      </div>

      {filterOptions && (
        <SalesFiltersBar
          filterOptions={filterOptions}
          activeFilters={activeFilters}
          onFilterChange={handleFilterChange}
        />
      )}

      {loading && (
        <div className="space-y-4 py-2" data-testid="sales-dashboard-loading">
          <Skeleton className="h-5 w-44 rounded-md" />
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Skeleton className="h-28 rounded-xl" />
            <Skeleton className="h-28 rounded-xl" />
            <Skeleton className="h-28 rounded-xl" />
            <Skeleton className="h-28 rounded-xl" />
          </div>
          <Skeleton className="h-56 rounded-xl w-full" />
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Skeleton className="h-64 rounded-xl" />
            <Skeleton className="h-64 rounded-xl" />
          </div>
        </div>
      )}

      {error && !loading && (
        <Card
          className="border-destructive/40 bg-destructive/5 text-center p-8 shadow-xs"
          data-testid="dashboard-error"
        >
          <CardHeader className="pb-2">
            <CardTitle className="text-base text-destructive font-semibold">
              Ошибка загрузки данных
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <p className="text-sm text-destructive/90">{error}</p>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="border-destructive/40 text-destructive hover:bg-destructive/10"
              onClick={loadData}
            >
              Повторить попытку
            </Button>
          </CardContent>
        </Card>
      )}

      {!loading && !error && overview && (
        <>
          {overview.summary.order_count === 0 ? (
            <Card
              className="border-dashed border-border bg-card/50 text-center p-12 shadow-xs"
              data-testid="dashboard-empty"
            >
              <CardHeader className="pb-2">
                <CardTitle className="text-lg font-semibold text-foreground">
                  Нет данных о продажах за выбранный период
                </CardTitle>
              </CardHeader>
              <CardContent>
                <CardDescription className="text-sm text-muted-foreground">
                  Попробуйте изменить период или сбросить установленные фильтры.
                </CardDescription>
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-6">
              <SalesKpiCards summary={overview.summary} />

              <SalesTrendChart
                trend={overview.trend}
                selectedDate={selectedDateValue}
                onSelectDate={handleSelectDate}
              />

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <SalesCategoryBreakdownView
                  categories={overview.categories}
                  selectedCategoryId={activeFilters.categoryId}
                  onSelectCategory={handleSelectCategory}
                />
                <SalesRegionalBreakdownView
                  regions={overview.regions}
                  selectedRegionId={activeFilters.regionId}
                  onSelectRegion={handleSelectRegion}
                />
              </div>

              {records && (
                <SalesDetailTable
                  items={records.items}
                  pagination={records.pagination}
                  sortBy={sortBy}
                  sortDirection={sortDirection}
                  loading={loading}
                  onSortChange={handleSortChange}
                  onPageChange={handlePageChange}
                />
              )}
            </div>
          )}
        </>
      )}
    </section>
  )
}
