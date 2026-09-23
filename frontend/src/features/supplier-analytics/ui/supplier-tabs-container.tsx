'use client'

import React, { useState, useEffect, useCallback } from 'react'
import {
  supplierGateway,
  type SupplierOverviewResponse,
  type SupplierPerformanceResponse,
  type SupplierDeliveriesResponse,
  type SupplierFilterOptionsResponse,
  type DeliveryStatus,
} from '../api/supplier-gateway'
import { SupplierKpiCards } from './supplier-kpi-cards'
import { SupplierTrendsChart } from './supplier-trends-chart'
import { SupplierStatusBreakdown } from './supplier-status-breakdown'
import { SupplierFiltersBar, type SupplierFilterState } from './supplier-filters-bar'
import { SupplierPerformanceTable } from './supplier-performance-table'
import { SupplierDeliveriesTable } from './supplier-deliveries-table'
import { Skeleton } from '@/components/ui/skeleton'
import { Button } from '@/components/ui/button'
import { LayoutDashboard, Award, FileText, AlertCircle, RefreshCw } from 'lucide-react'

interface SupplierTabsContainerProps {
  userId: string
  workspaceId: string
}

type TabType = 'overview' | 'performance' | 'deliveries'

export function SupplierTabsContainer({
  userId,
  workspaceId,
}: SupplierTabsContainerProps) {
  const [activeTab, setActiveTab] = useState<TabType>('overview')

  // Global filters
  const [filters, setFilters] = useState<SupplierFilterState>({
    dateFrom: '2025-01-01',
    dateTo: '2025-12-31',
  })
  const [filterOptions, setFilterOptions] =
    useState<SupplierFilterOptionsResponse | null>(null)

  // Overview data
  const [overview, setOverview] = useState<SupplierOverviewResponse | null>(null)
  const [isOverviewLoading, setIsOverviewLoading] = useState(true)
  const [overviewError, setOverviewError] = useState<string | null>(null)

  // Performance table state
  const [performance, setPerformance] =
    useState<SupplierPerformanceResponse | null>(null)
  const [isPerformanceLoading, setIsPerformanceLoading] = useState(false)
  const [performanceSearch, setPerformanceSearch] = useState('')
  const [performanceSortBy, setPerformanceSortBy] = useState('total_spend')
  const [performanceSortDir, setPerformanceSortDir] = useState<'asc' | 'desc'>('desc')
  const [performancePage, setPerformancePage] = useState(1)

  // Deliveries table state
  const [deliveries, setDeliveries] =
    useState<SupplierDeliveriesResponse | null>(null)
  const [isDeliveriesLoading, setIsDeliveriesLoading] = useState(false)
  const [deliveriesSearch, setDeliveriesSearch] = useState('')
  const [deliveriesStatus, setDeliveriesStatus] = useState<DeliveryStatus | undefined>()
  const [deliveriesSortBy, setDeliveriesSortBy] = useState('order_date')
  const [deliveriesSortDir, setDeliveriesSortDir] = useState<'asc' | 'desc'>('desc')
  const [deliveriesPage, setDeliveriesPage] = useState(1)

  // 1. Load Filter Options
  useEffect(() => {
    let isMounted = true
    supplierGateway
      .getFilters(userId, workspaceId)
      .then((opts) => {
        if (isMounted) setFilterOptions(opts)
      })
      .catch(() => {
        // Ignore filter loading error, use empty options
      })
    return () => {
      isMounted = false
    }
  }, [userId, workspaceId])

  // 2. Load Overview Data
  const loadOverview = useCallback(async () => {
    setIsOverviewLoading(true)
    setOverviewError(null)
    try {
      const data = await supplierGateway.getOverview(userId, workspaceId, {
        dateFrom: filters.dateFrom,
        dateTo: filters.dateTo,
        supplierId: filters.supplierId,
        warehouseId: filters.warehouseId,
      })
      setOverview(data)
    } catch (err) {
      setOverviewError(
        err instanceof Error ? err.message : 'Не удалось загрузить аналитику поставщиков',
      )
    } finally {
      setIsOverviewLoading(false)
    }
  }, [userId, workspaceId, filters])

  useEffect(() => {
    let ignore = false
    void Promise.resolve().then(() => {
      if (!ignore) {
        void loadOverview()
      }
    })
    return () => {
      ignore = true
    }
  }, [loadOverview])

  // 3. Load Performance Data
  const loadPerformance = useCallback(async () => {
    setIsPerformanceLoading(true)
    try {
      const data = await supplierGateway.getPerformance(userId, workspaceId, {
        dateFrom: filters.dateFrom,
        dateTo: filters.dateTo,
        warehouseId: filters.warehouseId,
        search: performanceSearch || undefined,
        page: performancePage,
        perPage: 20,
        sortBy: performanceSortBy as any,
        sortDirection: performanceSortDir,
      })
      setPerformance(data)
    } catch {
      // Ignore
    } finally {
      setIsPerformanceLoading(false)
    }
  }, [
    userId,
    workspaceId,
    filters.dateFrom,
    filters.dateTo,
    filters.warehouseId,
    performanceSearch,
    performancePage,
    performanceSortBy,
    performanceSortDir,
  ])

  useEffect(() => {
    let ignore = false
    if (activeTab === 'performance') {
      void Promise.resolve().then(() => {
        if (!ignore) {
          void loadPerformance()
        }
      })
    }
    return () => {
      ignore = true
    }
  }, [activeTab, loadPerformance])

  // 4. Load Deliveries Data
  const loadDeliveries = useCallback(async () => {
    setIsDeliveriesLoading(true)
    try {
      const data = await supplierGateway.getDeliveries(userId, workspaceId, {
        dateFrom: filters.dateFrom,
        dateTo: filters.dateTo,
        supplierId: filters.supplierId,
        warehouseId: filters.warehouseId,
        status: deliveriesStatus,
        search: deliveriesSearch || undefined,
        page: deliveriesPage,
        perPage: 20,
        sortBy: deliveriesSortBy as any,
        sortDirection: deliveriesSortDir,
      })
      setDeliveries(data)
    } catch {
      // Ignore
    } finally {
      setIsDeliveriesLoading(false)
    }
  }, [
    userId,
    workspaceId,
    filters.dateFrom,
    filters.dateTo,
    filters.supplierId,
    filters.warehouseId,
    deliveriesStatus,
    deliveriesSearch,
    deliveriesPage,
    deliveriesSortBy,
    deliveriesSortDir,
  ])

  useEffect(() => {
    let ignore = false
    if (activeTab === 'deliveries') {
      void Promise.resolve().then(() => {
        if (!ignore) {
          void loadDeliveries()
        }
      })
    }
    return () => {
      ignore = true
    }
  }, [activeTab, loadDeliveries])

  const handlePerformanceSort = (col: string) => {
    if (performanceSortBy === col) {
      setPerformanceSortDir(performanceSortDir === 'asc' ? 'desc' : 'asc')
    } else {
      setPerformanceSortBy(col)
      setPerformanceSortDir('desc')
    }
    setPerformancePage(1)
  }

  const handleDeliveriesSort = (col: string) => {
    if (deliveriesSortBy === col) {
      setDeliveriesSortDir(deliveriesSortDir === 'asc' ? 'desc' : 'asc')
    } else {
      setDeliveriesSortBy(col)
      setDeliveriesSortDir('desc')
    }
    setDeliveriesPage(1)
  }

  return (
    <div className="space-y-6">
      {/* Global Filter Bar */}
      <SupplierFiltersBar
        options={filterOptions}
        filters={filters}
        onFilterChange={(newFilters) => {
          setFilters(newFilters)
          setPerformancePage(1)
          setDeliveriesPage(1)
        }}
      />

      {/* Tabs Navigation */}
      <div className="flex border-b border-border space-x-1">
        <button
          onClick={() => setActiveTab('overview')}
          className={`flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors ${
            activeTab === 'overview'
              ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <LayoutDashboard className="h-4 w-4" />
          <span>Обзор и тренды</span>
        </button>

        <button
          onClick={() => setActiveTab('performance')}
          className={`flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors ${
            activeTab === 'performance'
              ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <Award className="h-4 w-4" />
          <span>Рейтинг поставщиков</span>
        </button>

        <button
          onClick={() => setActiveTab('deliveries')}
          className={`flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors ${
            activeTab === 'deliveries'
              ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <FileText className="h-4 w-4" />
          <span>Журнал поставок</span>
        </button>
      </div>

      {/* Tab 1: Overview */}
      {activeTab === 'overview' && (
        <div className="space-y-6">
          {isOverviewLoading && (
            <div className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                {[1, 2, 3, 4, 5, 6].map((i) => (
                  <Skeleton key={i} className="h-24 rounded-xl" />
                ))}
              </div>
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Skeleton className="h-72 rounded-xl lg:col-span-2" />
                <Skeleton className="h-72 rounded-xl" />
              </div>
            </div>
          )}

          {!isOverviewLoading && overviewError && (
            <div className="p-6 rounded-xl border border-destructive/30 bg-destructive/5 text-destructive flex items-center justify-between">
              <div className="flex items-center gap-3 text-xs">
                <AlertCircle className="h-5 w-5" />
                <span>{overviewError}</span>
              </div>
              <Button
                variant="outline"
                size="sm"
                className="h-8 text-xs gap-1.5"
                onClick={loadOverview}
              >
                <RefreshCw className="h-3.5 w-3.5" />
                Повторить
              </Button>
            </div>
          )}

          {!isOverviewLoading && overview && (
            <>
              <SupplierKpiCards summary={overview.summary} />

              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2">
                  <SupplierTrendsChart trends={overview.trends} />
                </div>
                <div>
                  <SupplierStatusBreakdown
                    breakdown={overview.status_breakdown}
                    totalDeliveries={overview.summary.total_deliveries}
                  />
                </div>
              </div>

              {/* Top Suppliers Preview in Overview */}
              <div className="space-y-2 pt-2">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold text-foreground">
                    Ключевые поставщики по объему закупок
                  </h3>
                  <Button
                    variant="link"
                    size="sm"
                    className="text-xs text-emerald-600 dark:text-emerald-400 p-0 h-auto"
                    onClick={() => setActiveTab('performance')}
                  >
                    Посмотреть полный рейтинг →
                  </Button>
                </div>
                <SupplierPerformanceTable
                  items={overview.top_suppliers}
                  pagination={{
                    page: 1,
                    per_page: 5,
                    total: overview.top_suppliers.length,
                    total_pages: 1,
                  }}
                  sortBy={performanceSortBy}
                  sortDirection={performanceSortDir}
                  search=""
                  onSort={handlePerformanceSort}
                  onPageChange={() => {}}
                  onSearchChange={() => {}}
                />
              </div>
            </>
          )}
        </div>
      )}

      {/* Tab 2: Performance Ranking */}
      {activeTab === 'performance' && (
        <div className="space-y-4">
          {isPerformanceLoading ? (
            <Skeleton className="h-96 rounded-xl w-full" />
          ) : (
            <SupplierPerformanceTable
              items={performance?.items ?? []}
              pagination={
                performance?.pagination ?? {
                  page: 1,
                  per_page: 20,
                  total: 0,
                  total_pages: 0,
                }
              }
              sortBy={performanceSortBy}
              sortDirection={performanceSortDir}
              search={performanceSearch}
              onSort={handlePerformanceSort}
              onPageChange={(page) => setPerformancePage(page)}
              onSearchChange={(s) => {
                setPerformanceSearch(s)
                setPerformancePage(1)
              }}
            />
          )}
        </div>
      )}

      {/* Tab 3: Deliveries Journal */}
      {activeTab === 'deliveries' && (
        <div className="space-y-4">
          {isDeliveriesLoading ? (
            <Skeleton className="h-96 rounded-xl w-full" />
          ) : (
            <SupplierDeliveriesTable
              items={deliveries?.items ?? []}
              pagination={
                deliveries?.pagination ?? {
                  page: 1,
                  per_page: 20,
                  total: 0,
                  total_pages: 0,
                }
              }
              sortBy={deliveriesSortBy}
              sortDirection={deliveriesSortDir}
              search={deliveriesSearch}
              statusFilter={deliveriesStatus}
              onSort={handleDeliveriesSort}
              onPageChange={(page) => setDeliveriesPage(page)}
              onSearchChange={(s) => {
                setDeliveriesSearch(s)
                setDeliveriesPage(1)
              }}
              onStatusChange={(st) => {
                setDeliveriesStatus(st)
                setDeliveriesPage(1)
              }}
            />
          )}
        </div>
      )}
    </div>
  )
}
