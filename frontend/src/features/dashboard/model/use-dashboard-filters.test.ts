import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useDashboardFilters } from './use-dashboard-filters'
import type { DashboardSavedView } from '../api/dashboard-gateway'

const mockViews: DashboardSavedView[] = [
  {
    id: 'view-1',
    dashboard_id: 'dash-1',
    name: 'По умолчанию',
    filters: { date_range: '30d' },
    is_default: true,
    created_at: '2026-09-23T10:00:00Z',
    updated_at: '2026-09-23T10:00:00Z',
  },
  {
    id: 'view-2',
    dashboard_id: 'dash-1',
    name: 'Южный склад',
    filters: { date_range: '90d', warehouse_id: 'wh-2' },
    is_default: false,
    created_at: '2026-09-23T10:00:00Z',
    updated_at: '2026-09-23T10:00:00Z',
  },
]

describe('useDashboardFilters', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/dashboards/dash-1')
  })

  it('initializes with default view if available and no URL params', () => {
    const { result } = renderHook(() => useDashboardFilters({ savedViews: mockViews }))

    expect(result.current.activeViewId).toBe('view-1')
    expect(result.current.filters).toEqual({ date_range: '30d' })
  })

  it('updates a single filter and marks view as modified', () => {
    const { result } = renderHook(() => useDashboardFilters({ savedViews: mockViews }))

    act(() => {
      result.current.setFilter('category_id', 'cat-1')
    })

    expect(result.current.filters.category_id).toBe('cat-1')
    expect(result.current.isModifiedFromActiveView).toBe(true)
  })

  it('applies a saved view', () => {
    const { result } = renderHook(() => useDashboardFilters({ savedViews: mockViews }))

    act(() => {
      result.current.applySavedView(mockViews[1])
    })

    expect(result.current.activeViewId).toBe('view-2')
    expect(result.current.filters).toEqual({ date_range: '90d', warehouse_id: 'wh-2' })
    expect(result.current.isModifiedFromActiveView).toBe(false)
  })

  it('resets filters', () => {
    const { result } = renderHook(() => useDashboardFilters({ savedViews: mockViews }))

    act(() => {
      result.current.setFilter('region_id', 'reg-1')
    })
    expect(result.current.filters.region_id).toBe('reg-1')

    act(() => {
      result.current.resetFilters()
    })

    expect(result.current.filters.region_id).toBeUndefined()
    expect(result.current.activeViewId).toBeNull()
  })
})
