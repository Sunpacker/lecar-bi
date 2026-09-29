'use client'

import React, { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'next/navigation'
import { Skeleton } from '@/components/ui/skeleton'
import { EmptyState, ErrorState, LoadingState } from '@/src/shared/ui'
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
    let ignore = false
    void Promise.resolve().then(() => {
      if (!ignore) {
        void loadData()
      }
    })
    return () => {
      ignore = true
    }
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
        <LoadingState
          description="Загрузка аналитики продаж"
          data-testid="sales-dashboard-loading"
          skeleton={
            <>
              <Skeleton className="h-5 w-44 rounded-md" />
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: 4 }, (_, index) => (
                  <Skeleton key={index} className="h-28 rounded-xl" />
                ))}
              </div>
              <Skeleton className="h-56 w-full rounded-xl" />
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Skeleton className="h-64 rounded-xl" />
                <Skeleton className="h-64 rounded-xl" />
              </div>
            </>
          }
        />
      )}

      {error && !loading && (
        <ErrorState
          title="Ошибка загрузки данных"
          description={error}
          actionLabel="Повторить попытку"
          onAction={loadData}
          data-testid="dashboard-error"
        />
      )}

      {!loading && !error && overview && (
        <>
          {overview.summary.order_count === 0 ? (
            <EmptyState
              title="Нет данных о продажах за выбранный период"
              description="Попробуйте изменить период или сбросить установленные фильтры."
              data-testid="dashboard-empty"
            />
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
