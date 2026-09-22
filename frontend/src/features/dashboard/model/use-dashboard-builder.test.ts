import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useDashboardBuilder } from './use-dashboard-builder'
import { dashboardGateway, type DashboardDetail } from '../api/dashboard-gateway'

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    update: vi.fn(),
  },
}))

const mockInitialDashboard: DashboardDetail = {
  id: 'dash-1',
  workspace_id: 'ws-1',
  title: 'Обзор продаж',
  description: 'Исходное описание',
  created_at: '2026-09-22T10:00:00Z',
  updated_at: '2026-09-22T10:00:00Z',
  widgets: [
    {
      id: 'w-1',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue', date_range: '30d' },
      position: { x: 0, y: 0, w: 4, h: 2 },
      options: {},
    },
    {
      id: 'w-2',
      title: 'Динамика продаж',
      type: 'line_chart',
      query_config: {
        dataset: 'sales',
        metric: 'revenue',
        dimension: 'date',
        date_range: '30d',
      },
      position: { x: 4, y: 0, w: 8, h: 4 },
      options: {},
    },
  ],
}

describe('useDashboardBuilder', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('initializes with default view mode and dashboard data', () => {
    const { result } = renderHook(() =>
      useDashboardBuilder({
        initialDashboard: mockInitialDashboard,
        userId: 'user-1',
        workspaceId: 'ws-1',
      }),
    )

    expect(result.current.mode).toBe('view')
    expect(result.current.title).toBe('Обзор продаж')
    expect(result.current.description).toBe('Исходное описание')
    expect(result.current.widgets).toHaveLength(2)
    expect(result.current.isDirty).toBe(false)
  })

  it('toggles mode and marks dirty when title or description changes', () => {
    const { result } = renderHook(() =>
      useDashboardBuilder({
        initialDashboard: mockInitialDashboard,
        userId: 'user-1',
        workspaceId: 'ws-1',
      }),
    )

    act(() => {
      result.current.setMode('edit')
    })
    expect(result.current.mode).toBe('edit')
    expect(result.current.isDirty).toBe(false)

    act(() => {
      result.current.setTitle('Новый заголовок')
    })
    expect(result.current.title).toBe('Новый заголовок')
    expect(result.current.isDirty).toBe(true)
  })

  it('moves widget while respecting 12-column grid boundaries', () => {
    const { result } = renderHook(() =>
      useDashboardBuilder({
        initialDashboard: mockInitialDashboard,
        userId: 'user-1',
        workspaceId: 'ws-1',
      }),
    )

    act(() => {
      result.current.setMode('edit')
    })

    // Move left when already at x=0 should stay x=0
    act(() => {
      result.current.moveWidget('w-1', 'left')
    })
    expect(result.current.widgets[0].position.x).toBe(0)

    // Move right increases x
    act(() => {
      result.current.moveWidget('w-1', 'right')
    })
    expect(result.current.widgets[0].position.x).toBe(1)
    expect(result.current.isDirty).toBe(true)

    // Move up when at y=0 stays y=0
    act(() => {
      result.current.moveWidget('w-1', 'up')
    })
    expect(result.current.widgets[0].position.y).toBe(0)

    // Move down increases y
    act(() => {
      result.current.moveWidget('w-1', 'down')
    })
    expect(result.current.widgets[0].position.y).toBe(1)
  })

  it('resizes widget while keeping width within [1, 12 - x] and height within [1, 24]', () => {
    const { result } = renderHook(() =>
      useDashboardBuilder({
        initialDashboard: mockInitialDashboard,
        userId: 'user-1',
        workspaceId: 'ws-1',
      }),
    )

    act(() => {
      result.current.setMode('edit')
    })

    // w-2 is at x=4, w=8 -> max width is 12 - 4 = 8
    act(() => {
      result.current.resizeWidget('w-2', 2, 0)
    })
    expect(result.current.widgets[1].position.w).toBe(8) // cannot exceed 12 - 4

    // Shrink width
    act(() => {
      result.current.resizeWidget('w-2', -2, 1)
    })
    expect(result.current.widgets[1].position.w).toBe(6)
    expect(result.current.widgets[1].position.h).toBe(5)
    expect(result.current.isDirty).toBe(true)
  })

  it('adds and updates widgets via config sheet', () => {
    const { result } = renderHook(() =>
      useDashboardBuilder({
        initialDashboard: mockInitialDashboard,
        userId: 'user-1',
        workspaceId: 'ws-1',
      }),
    )

    act(() => {
      result.current.setMode('edit')
      result.current.openCreateWidget()
    })

    expect(result.current.isConfigOpen).toBe(true)
    expect(result.current.editingWidget).toBeNull()

    act(() => {
      result.current.saveWidgetConfig({
        title: 'Остатки на складах',
        type: 'bar_chart',
        dataset: 'inventory',
        metric: 'stock_quantity',
        dimension: 'warehouse',
        date_range: '30d',
        w: 6,
        h: 4,
      })
    })

    expect(result.current.widgets).toHaveLength(3)
    const newWidget = result.current.widgets[2]
    expect(newWidget.title).toBe('Остатки на складах')
    expect(newWidget.query_config.dataset).toBe('inventory')
    expect(result.current.isConfigOpen).toBe(false)
    expect(result.current.isDirty).toBe(true)

    // Edit widget
    act(() => {
      result.current.openEditWidget(newWidget)
    })
    expect(result.current.isConfigOpen).toBe(true)
    expect(result.current.editingWidget?.id).toBe(newWidget.id)

    act(() => {
      result.current.saveWidgetConfig({
        title: 'Остатки (обновлено)',
        type: 'bar_chart',
        dataset: 'inventory',
        metric: 'stock_quantity',
        dimension: 'warehouse',
        date_range: '90d',
        w: 6,
        h: 4,
      })
    })

    expect(result.current.widgets[2].title).toBe('Остатки (обновлено)')
    expect(result.current.widgets[2].query_config.date_range).toBe('90d')
  })

  it('removes widget and updates dirty state', () => {
    const { result } = renderHook(() =>
      useDashboardBuilder({
        initialDashboard: mockInitialDashboard,
        userId: 'user-1',
        workspaceId: 'ws-1',
      }),
    )

    act(() => {
      result.current.setMode('edit')
      result.current.removeWidget('w-1')
    })

    expect(result.current.widgets).toHaveLength(1)
    expect(result.current.widgets[0].id).toBe('w-2')
    expect(result.current.isDirty).toBe(true)
  })

  it('saves changes successfully and resets dirty state to view mode', async () => {
    const onSaveSuccess = vi.fn()
    const updatedDashboard: DashboardDetail = {
      ...mockInitialDashboard,
      title: 'Обновленный заголовок',
    }
    vi.mocked(dashboardGateway.update).mockResolvedValueOnce(updatedDashboard)

    const { result } = renderHook(() =>
      useDashboardBuilder({
        initialDashboard: mockInitialDashboard,
        userId: 'user-1',
        workspaceId: 'ws-1',
        onSaveSuccess,
      }),
    )

    act(() => {
      result.current.setMode('edit')
      result.current.setTitle('Обновленный заголовок')
    })

    let success = false
    await act(async () => {
      success = await result.current.saveDashboard()
    })

    expect(success).toBe(true)
    expect(dashboardGateway.update).toHaveBeenCalledWith(
      'dash-1',
      'user-1',
      expect.objectContaining({
        title: 'Обновленный заголовок',
      }),
      'ws-1',
    )
    expect(result.current.mode).toBe('view')
    expect(result.current.isDirty).toBe(false)
    expect(onSaveSuccess).toHaveBeenCalledWith(updatedDashboard)
  })

  it('discards changes and reverts to initial state', () => {
    const { result } = renderHook(() =>
      useDashboardBuilder({
        initialDashboard: mockInitialDashboard,
        userId: 'user-1',
        workspaceId: 'ws-1',
      }),
    )

    act(() => {
      result.current.setMode('edit')
      result.current.setTitle('Черновик')
      result.current.removeWidget('w-1')
    })

    expect(result.current.isDirty).toBe(true)

    act(() => {
      result.current.discardChanges()
    })

    expect(result.current.mode).toBe('view')
    expect(result.current.title).toBe('Обзор продаж')
    expect(result.current.widgets).toHaveLength(2)
    expect(result.current.isDirty).toBe(false)
  })
})
