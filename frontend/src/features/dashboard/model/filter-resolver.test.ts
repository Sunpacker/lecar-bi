import { describe, it, expect } from 'vitest'
import {
  resolveDateRange,
  mergeFilters,
  sanitizeFiltersForDataset,
  hasActiveFilters,
  isFiltersEqual,
} from './filter-resolver'
import type { DashboardFilterValues } from '../api/dashboard-gateway'

describe('filter-resolver', () => {
  const refDate = new Date('2026-09-23T12:00:00Z')

  describe('resolveDateRange', () => {
    it('returns empty date boundaries for "all" or undefined', () => {
      expect(resolveDateRange('all', null, null, refDate)).toEqual({})
      expect(resolveDateRange(undefined, null, null, refDate)).toEqual({})
      expect(resolveDateRange(null, null, null, refDate)).toEqual({})
    })

    it('calculates 30d relative window', () => {
      const { dateFrom, dateTo } = resolveDateRange('30d', null, null, refDate)
      expect(dateTo).toBe('2026-09-23')
      expect(dateFrom).toBe('2026-08-24')
    })

    it('calculates 90d, 180d, 365d relative windows', () => {
      expect(resolveDateRange('90d', null, null, refDate).dateTo).toBe('2026-09-23')
      expect(resolveDateRange('180d', null, null, refDate).dateTo).toBe('2026-09-23')
      expect(resolveDateRange('365d', null, null, refDate).dateTo).toBe('2026-09-23')
    })

    it('returns custom date range when specified', () => {
      const res = resolveDateRange('custom', '2026-01-01', '2026-06-30', refDate)
      expect(res).toEqual({
        dateFrom: '2026-01-01',
        dateTo: '2026-06-30',
      })
    })

    it('uses custom dates directly if dateRange is omitted but custom dates provided', () => {
      const res = resolveDateRange(null, '2026-02-01', '2026-02-28', refDate)
      expect(res).toEqual({
        dateFrom: '2026-02-01',
        dateTo: '2026-02-28',
      })
    })
  })

  describe('mergeFilters', () => {
    it('overrides dashboard filters with widget-specific filters', () => {
      const dash: DashboardFilterValues = {
        date_range: '30d',
        category_id: 'cat-1',
        region_id: 'reg-1',
      }
      const widget: DashboardFilterValues = {
        date_range: '90d',
        region_id: 'reg-2',
      }

      const merged = mergeFilters(dash, widget)
      expect(merged).toEqual({
        date_range: '90d',
        date_from: null,
        date_to: null,
        category_id: 'cat-1',
        region_id: 'reg-2',
        warehouse_id: null,
        stock_health: null,
      })
    })

    it('returns dashboard filters if widget filters are undefined', () => {
      const dash: DashboardFilterValues = { date_range: '30d' }
      const merged = mergeFilters(dash, null)
      expect(merged.date_range).toBe('30d')
    })
  })

  describe('sanitizeFiltersForDataset', () => {
    const fullFilters: DashboardFilterValues = {
      date_range: '30d',
      date_from: '2026-08-01',
      date_to: '2026-08-31',
      category_id: 'cat-1',
      region_id: 'reg-1',
      warehouse_id: 'wh-1',
      stock_health: 'low_stock',
    }

    it('sales dataset drops warehouse_id and stock_health', () => {
      const sanitized = sanitizeFiltersForDataset(fullFilters, 'sales')
      expect(sanitized).toEqual({
        date_range: '30d',
        date_from: '2026-08-01',
        date_to: '2026-08-31',
        category_id: 'cat-1',
        region_id: 'reg-1',
        warehouse_id: null,
        stock_health: null,
      })
    })

    it('inventory dataset drops region_id', () => {
      const sanitized = sanitizeFiltersForDataset(fullFilters, 'inventory')
      expect(sanitized).toEqual({
        date_range: '30d',
        date_from: '2026-08-01',
        date_to: '2026-08-31',
        category_id: 'cat-1',
        region_id: null,
        warehouse_id: 'wh-1',
        stock_health: 'low_stock',
      })
    })
  })

  describe('hasActiveFilters and isFiltersEqual', () => {
    it('detects when filters are active', () => {
      expect(hasActiveFilters({})).toBe(false)
      expect(hasActiveFilters({ date_range: 'all' })).toBe(false)
      expect(hasActiveFilters({ date_range: '30d' })).toBe(true)
      expect(hasActiveFilters({ category_id: 'cat-1' })).toBe(true)
    })

    it('checks equality between two filter sets', () => {
      const f1: DashboardFilterValues = { date_range: '30d', region_id: 'reg-1' }
      const f2: DashboardFilterValues = { date_range: '30d', region_id: 'reg-1' }
      const f3: DashboardFilterValues = { date_range: '90d', region_id: 'reg-1' }

      expect(isFiltersEqual(f1, f2)).toBe(true)
      expect(isFiltersEqual(f1, f3)).toBe(false)
    })
  })
})
