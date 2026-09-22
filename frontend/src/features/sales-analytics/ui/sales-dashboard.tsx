'use client'

import React, { useCallback, useEffect, useState } from 'react'
import {
  salesGateway,
  type SalesFilterOptions,
  type SalesFilterParams,
  type SalesOverview,
} from '../api/sales-gateway'
import { SalesCategoryBreakdownView } from './sales-category-breakdown'
import { SalesFiltersBar } from './sales-filters-bar'
import { SalesKpiCards } from './sales-kpi-cards'
import { SalesRegionalBreakdownView } from './sales-regional-breakdown'
import { SalesTrendChart } from './sales-trend-chart'

interface SalesDashboardProps {
  userId: string
  workspaceId: string
}

export function SalesDashboard({ userId, workspaceId }: SalesDashboardProps) {
  const [filterOptions, setFilterOptions] = useState<SalesFilterOptions | null>(null)
  const [activeFilters, setActiveFilters] = useState<SalesFilterParams>({})
  const [overview, setOverview] = useState<SalesOverview | null>(null)
  const [loading, setLoading] = useState<boolean>(true)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      const [optionsRes, overviewRes] = await Promise.all([
        salesGateway.getFilterOptions(userId, workspaceId),
        salesGateway.getOverview(userId, workspaceId, activeFilters),
      ])

      setFilterOptions(optionsRes)
      setOverview(overviewRes)
    } catch (err: unknown) {
      const message =
        err instanceof Error ? err.message : 'Не удалось загрузить данные аналитики'
      setError(message)
    } finally {
      setLoading(false)
    }
  }, [userId, workspaceId, activeFilters])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleFilterChange = (newFilters: SalesFilterParams) => {
    setActiveFilters(newFilters)
  }

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

              <SalesTrendChart trend={overview.trend} />

              <div className="breakdowns-grid">
                <SalesCategoryBreakdownView categories={overview.categories} />
                <SalesRegionalBreakdownView regions={overview.regions} />
              </div>
            </div>
          )}
        </>
      )}
    </section>
  )
}
