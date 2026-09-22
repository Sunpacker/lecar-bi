'use client'

import React, { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'next/navigation'
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
    <section className="sales-dashboard-section" data-testid="sales-dashboard">
      <div className="dashboard-header">
        <div>
          <span className="eyebrow">ОБЗОР / ПРОДАЖИ</span>
          <h2 className="dashboard-title">Аналитика продаж</h2>
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
        <div className="dashboard-loading-skeleton" data-testid="sales-dashboard-loading">
          <div className="skeleton-line" style={{ width: '40%' }} />
          <div className="skeleton-grid">
            <div className="skeleton-card" />
            <div className="skeleton-card" />
            <div className="skeleton-card" />
            <div className="skeleton-card" />
          </div>
          <div className="skeleton-card skeleton-card--large" />
        </div>
      )}

      {error && !loading && (
        <div className="dashboard-error-card" data-testid="dashboard-error">
          <p className="error-text">{error}</p>
          <button type="button" className="btn-retry" onClick={loadData}>
            Повторить попытку
          </button>
        </div>
      )}

      {!loading && !error && overview && (
        <>
          {overview.summary.order_count === 0 ? (
            <div className="dashboard-empty-card" data-testid="dashboard-empty">
              <p className="empty-title">Нет данных о продажах за выбранный период</p>
              <p className="empty-subtitle">
                Попробуйте изменить период или сбросить установленные фильтры.
              </p>
            </div>
          ) : (
            <div className="dashboard-content space-y-6">
              <SalesKpiCards summary={overview.summary} />

              <SalesTrendChart
                trend={overview.trend}
                selectedDate={selectedDateValue}
                onSelectDate={handleSelectDate}
              />

              <div className="breakdowns-grid">
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
