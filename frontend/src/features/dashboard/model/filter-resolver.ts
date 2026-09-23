import type { DashboardFilterValues } from '../api/dashboard-gateway'

const DAYS_MAP: Record<string, number> = {
  '30d': 30,
  '90d': 90,
  '180d': 180,
  '365d': 365,
}

export function resolveDateRange(
  dateRange?: string | null,
  customFrom?: string | null,
  customTo?: string | null,
  refDate: Date = new Date(),
): { dateFrom?: string; dateTo?: string } {
  if (customFrom || customTo) {
    return {
      dateFrom: customFrom || undefined,
      dateTo: customTo || undefined,
    }
  }

  if (!dateRange || dateRange === 'all') {
    return {}
  }

  const days = DAYS_MAP[dateRange]
  if (!days) {
    return {}
  }

  const to = new Date(refDate.getTime())
  const from = new Date(refDate.getTime())
  from.setDate(to.getDate() - days)

  return {
    dateFrom: from.toISOString().split('T')[0],
    dateTo: to.toISOString().split('T')[0],
  }
}

export function mergeFilters(
  dashboardFilters?: DashboardFilterValues | null,
  widgetFilters?: DashboardFilterValues | null,
): DashboardFilterValues {
  const merged: DashboardFilterValues = {
    date_range: widgetFilters?.date_range ?? dashboardFilters?.date_range ?? null,
    date_from: widgetFilters?.date_from ?? dashboardFilters?.date_from ?? null,
    date_to: widgetFilters?.date_to ?? dashboardFilters?.date_to ?? null,
    category_id: widgetFilters?.category_id ?? dashboardFilters?.category_id ?? null,
    region_id: widgetFilters?.region_id ?? dashboardFilters?.region_id ?? null,
    warehouse_id: widgetFilters?.warehouse_id ?? dashboardFilters?.warehouse_id ?? null,
    stock_health: widgetFilters?.stock_health ?? dashboardFilters?.stock_health ?? null,
  }

  return merged
}

export function sanitizeFiltersForDataset(
  filters: DashboardFilterValues,
  dataset: 'sales' | 'inventory',
): DashboardFilterValues {
  if (dataset === 'sales') {
    return {
      date_range: filters.date_range ?? null,
      date_from: filters.date_from ?? null,
      date_to: filters.date_to ?? null,
      category_id: filters.category_id ?? null,
      region_id: filters.region_id ?? null,
      warehouse_id: null,
      stock_health: null,
    }
  }

  return {
    date_range: filters.date_range ?? null,
    date_from: filters.date_from ?? null,
    date_to: filters.date_to ?? null,
    category_id: filters.category_id ?? null,
    region_id: null,
    warehouse_id: filters.warehouse_id ?? null,
    stock_health: filters.stock_health ?? null,
  }
}

export function hasActiveFilters(filters?: DashboardFilterValues | null): boolean {
  if (!filters) return false
  if (filters.date_range && filters.date_range !== 'all') return true
  if (filters.date_from || filters.date_to) return true
  if (filters.category_id) return true
  if (filters.region_id) return true
  if (filters.warehouse_id) return true
  if (filters.stock_health) return true
  return false
}

export function isFiltersEqual(
  a?: DashboardFilterValues | null,
  b?: DashboardFilterValues | null,
): boolean {
  const norm = (f?: DashboardFilterValues | null) => ({
    date_range: f?.date_range || null,
    date_from: f?.date_from || null,
    date_to: f?.date_to || null,
    category_id: f?.category_id || null,
    region_id: f?.region_id || null,
    warehouse_id: f?.warehouse_id || null,
    stock_health: f?.stock_health || null,
  })

  return JSON.stringify(norm(a)) === JSON.stringify(norm(b))
}
