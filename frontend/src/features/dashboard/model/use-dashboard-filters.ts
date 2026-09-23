import { useState, useCallback, useMemo, useEffect } from 'react'
import type { DashboardFilterValues, DashboardSavedView } from '../api/dashboard-gateway'
import { isFiltersEqual } from './filter-resolver'

export interface UseDashboardFiltersOptions {
  initialFilters?: DashboardFilterValues
  initialViewId?: string | null
  savedViews?: DashboardSavedView[]
  onFilterChange?: (filters: DashboardFilterValues) => void
}

function parseUrlFilters(): { filters: DashboardFilterValues; viewId: string | null } {
  if (typeof window === 'undefined') {
    return { filters: {}, viewId: null }
  }

  const params = new URLSearchParams(window.location.search)
  const filters: DashboardFilterValues = {}

  const dateRange = params.get('date_range')
  if (dateRange && ['30d', '90d', '180d', '365d', 'all', 'custom'].includes(dateRange)) {
    filters.date_range = dateRange as DashboardFilterValues['date_range']
  }

  const dateFrom = params.get('date_from')
  if (dateFrom) filters.date_from = dateFrom

  const dateTo = params.get('date_to')
  if (dateTo) filters.date_to = dateTo

  const categoryId = params.get('category_id')
  if (categoryId) filters.category_id = categoryId

  const regionId = params.get('region_id')
  if (regionId) filters.region_id = regionId

  const warehouseId = params.get('warehouse_id')
  if (warehouseId) filters.warehouse_id = warehouseId

  const stockHealth = params.get('stock_health')
  if (
    stockHealth &&
    ['in_stock', 'low_stock', 'out_of_stock', 'overstock'].includes(stockHealth)
  ) {
    filters.stock_health = stockHealth as DashboardFilterValues['stock_health']
  }

  const viewId = params.get('view_id') || null

  return { filters, viewId }
}

function syncUrlFilters(filters: DashboardFilterValues, viewId: string | null) {
  if (typeof window === 'undefined') return

  const params = new URLSearchParams()
  if (viewId) params.set('view_id', viewId)
  if (filters.date_range) params.set('date_range', filters.date_range)
  if (filters.date_from) params.set('date_from', filters.date_from)
  if (filters.date_to) params.set('date_to', filters.date_to)
  if (filters.category_id) params.set('category_id', filters.category_id)
  if (filters.region_id) params.set('region_id', filters.region_id)
  if (filters.warehouse_id) params.set('warehouse_id', filters.warehouse_id)
  if (filters.stock_health) params.set('stock_health', filters.stock_health)

  const query = params.toString()
  const newUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname
  window.history.replaceState({}, '', newUrl)
}

export function useDashboardFilters({
  initialFilters,
  initialViewId = null,
  savedViews = [],
  onFilterChange,
}: UseDashboardFiltersOptions) {
  const [isReset, setIsReset] = useState(false)

  // User-modified filters or null if untouched
  const [userFilters, setUserFilters] = useState<DashboardFilterValues | null>(() => {
    const urlState = parseUrlFilters()
    if (Object.keys(urlState.filters).length > 0) {
      return urlState.filters
    }

    if (initialFilters && Object.keys(initialFilters).length > 0) {
      return initialFilters
    }

    return null
  })

  const [selectedViewId, setSelectedViewId] = useState<string | null>(() => {
    const urlState = parseUrlFilters()
    if (urlState.viewId) return urlState.viewId
    if (initialViewId) return initialViewId

    return null
  })

  const defaultView = useMemo(
    () => savedViews.find((v) => v.is_default) ?? null,
    [savedViews],
  )

  const activeViewId = useMemo(() => {
    if (isReset) return null
    if (selectedViewId !== null) return selectedViewId
    if (defaultView) return defaultView.id
    return null
  }, [isReset, selectedViewId, defaultView])

  const activeView = useMemo(
    () => savedViews.find((v) => v.id === activeViewId) ?? null,
    [savedViews, activeViewId],
  )

  // Effective filters:
  const filters: DashboardFilterValues = useMemo(() => {
    if (userFilters !== null) {
      return userFilters
    }
    if (activeView) {
      return activeView.filters
    }
    return {}
  }, [userFilters, activeView])

  // Synchronize URL whenever effective filters or activeViewId change
  useEffect(() => {
    syncUrlFilters(filters, activeViewId)
    onFilterChange?.(filters)
  }, [filters, activeViewId, onFilterChange])

  const setFilter = useCallback(
    <K extends keyof DashboardFilterValues>(key: K, value: DashboardFilterValues[K]) => {
      setIsReset(false)
      setUserFilters((prev) => {
        const base = prev ?? activeView?.filters ?? {}
        const next = { ...base }
        if (value === null || value === undefined || value === '') {
          delete next[key]
        } else {
          next[key] = value
        }
        return next
      })
    },
    [activeView],
  )

  const setFilters = useCallback(
    (newFilters: DashboardFilterValues, viewId: string | null = null) => {
      setIsReset(false)
      setUserFilters(newFilters)
      if (viewId !== undefined) {
        setSelectedViewId(viewId)
      }
    },
    [],
  )

  const resetFilters = useCallback(() => {
    setIsReset(true)
    setUserFilters({})
    setSelectedViewId(null)
  }, [])

  const applySavedView = useCallback((view: DashboardSavedView) => {
    setIsReset(false)
    setUserFilters(view.filters)
    setSelectedViewId(view.id)
  }, [])

  const isModifiedFromActiveView = useMemo(() => {
    if (!activeView) return false
    return !isFiltersEqual(filters, activeView.filters)
  }, [activeView, filters])

  return {
    filters,
    activeViewId,
    activeView,
    setFilter,
    setFilters,
    resetFilters,
    applySavedView,
    isModifiedFromActiveView,
  }
}
