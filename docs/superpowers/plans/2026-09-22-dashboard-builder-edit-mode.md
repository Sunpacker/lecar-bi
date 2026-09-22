# Dashboard Builder UI & Grid Editor (Phase 8 — Step 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the interactive Dashboard Builder UI & Grid Editor (Edit Mode) for Phase 8, enabling users to toggle between View and Edit modes, modify dashboard metadata, add, reconfigure, reposition, and resize semantic widgets across a responsive 12-column grid, and persist updates via the backend API.

**Architecture:** Encapsulate builder state and grid layout invariants in a dedicated `useDashboardBuilder` hook under `src/features/dashboard/model/`. Build an interactive grid editor (`DashboardGridEditor`) with `WidgetEditorCard` featuring drag-and-drop and granular keyboard/touch-accessible repositioning and sizing controls. Provide a slide-over `WidgetConfigSheet` for semantic widget configuration (dataset, metric, dimension, date range, type) strictly compliant with `contracts/openapi/analytics-v1.yaml`. Integrate into `DashboardViewer` with mode toggling, dirty state tracking, and seamless API synchronization.

**Tech Stack:** Next.js 16 (App Router, Client Components), React 19, TypeScript 5.7, Tailwind CSS 4, shadcn/ui (@base-ui/react Sheet, Card, Button, Input, Select, Badge, Skeleton), Lucide React, Vitest 5, Testing Library.

**Spec:** `docs/roadmap/08-dashboard-builder.md`, `docs/architecture/03-frontend-nextjs.md`, `docs/architecture/05-bounded-contexts.md`, `docs/architecture/07-api-and-integration.md`, `contracts/openapi/analytics-v1.yaml`.

## Global Constraints

- Strictly adhere to the OpenAPI schema in `contracts/openapi/analytics-v1.yaml` (`WidgetGridPosition`, `WidgetQueryConfig`, `WidgetInput`, `UpdateDashboardRequest`) and generated types in `frontend/src/shared/api/generated/schema.ts`.
- Multi-tenancy isolation: All API interactions via `dashboardGateway` must supply `userId` and `workspaceId`.
- Grid bounds invariant: 12-column grid where `0 <= x <= 11`, `1 <= w <= 12`, `x + w <= 12`, `y >= 0`, `1 <= h <= 24`.
- Frontend must not perform business metric calculations: semantic queries specify `dataset`, `metric`, `dimension`, `date_range`, and backend returns computed values.
- UI styling must use Tailwind CSS semantic tokens (`bg-card`, `text-card-foreground`, `border-border`, `text-muted-foreground`, `emerald-500`, etc.) compatible with light and dark themes.
- No placeholders ("TODO", "TBD", "implement later"); every task specifies complete code and test suites.
- All Vitest tests must pass cleanly.

---

### Task 1: Dashboard Builder State & Layout Logic Hook (`useDashboardBuilder`)

**Files:**
- Create: `frontend/src/features/dashboard/model/use-dashboard-builder.ts`
- Test: `frontend/src/features/dashboard/model/use-dashboard-builder.test.ts`

**Interfaces:**
- Consumes:
  - `DashboardDetail`, `WidgetDetail`, `WidgetInput`, `WidgetGridPosition`, `WidgetQueryConfig`, `UpdateDashboardRequest`, `dashboardGateway` from `../api/dashboard-gateway`.
- Produces:
  - `useDashboardBuilder(options: UseDashboardBuilderOptions)` hook returning:
    - `mode`: `'view' | 'edit'`
    - `title`: `string`
    - `description`: `string`
    - `widgets`: `WidgetDetail[]`
    - `isDirty`: `boolean`
    - `isSaving`: `boolean`
    - `error`: `string | null`
    - `editingWidget`: `WidgetDetail | null`
    - `isConfigOpen`: `boolean`
    - `setMode`: `(mode: 'view' | 'edit') => void`
    - `setTitle`: `(title: string) => void`
    - `setDescription`: `(desc: string) => void`
    - `openCreateWidget`: `() => void`
    - `openEditWidget`: `(widget: WidgetDetail) => void`
    - `closeConfigSheet`: `() => void`
    - `saveWidgetConfig`: `(config: WidgetFormValues) => void`
    - `removeWidget`: `(widgetId: string) => void`
    - `moveWidget`: `(widgetId: string, direction: 'up' | 'down' | 'left' | 'right') => void`
    - `resizeWidget`: `(widgetId: string, deltaW: number, deltaH: number) => void`
    - `repositionWidget`: `(widgetId: string, x: number, y: number) => void`
    - `saveDashboard`: `() => Promise<boolean>`
    - `discardChanges`: `() => void`
  - Types: `WidgetFormValues`, `UseDashboardBuilderOptions`.

- [ ] **Step 1: Write the failing test**

Create `frontend/src/features/dashboard/model/use-dashboard-builder.test.ts`:
```ts
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
      query_config: { dataset: 'sales', metric: 'revenue', dimension: 'date', date_range: '30d' },
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run src/features/dashboard/model/use-dashboard-builder.test.ts`
Expected: FAIL with "Cannot find module './use-dashboard-builder'"

- [ ] **Step 3: Write minimal implementation**

Create `frontend/src/features/dashboard/model/use-dashboard-builder.ts`:
```ts
import { useState, useCallback, useMemo } from 'react'
import {
  dashboardGateway,
  type DashboardDetail,
  type WidgetDetail,
  type WidgetInput,
  type UpdateDashboardRequest,
} from '../api/dashboard-gateway'

export interface WidgetFormValues {
  title: string
  type: WidgetDetail['type']
  dataset: 'sales' | 'inventory'
  metric: WidgetDetail['query_config']['metric']
  dimension?: WidgetDetail['query_config']['dimension'] | null
  date_range?: WidgetDetail['query_config']['date_range'] | null
  w: number
  h: number
}

export interface UseDashboardBuilderOptions {
  initialDashboard: DashboardDetail
  userId: string
  workspaceId?: string
  onSaveSuccess?: (updated: DashboardDetail) => void
}

export function useDashboardBuilder({
  initialDashboard,
  userId,
  workspaceId,
  onSaveSuccess,
}: UseDashboardBuilderOptions) {
  const [currentDashboard, setCurrentDashboard] = useState<DashboardDetail>(initialDashboard)
  const [mode, setMode] = useState<'view' | 'edit'>('view')
  const [title, setTitle] = useState(initialDashboard.title)
  const [description, setDescription] = useState(initialDashboard.description ?? '')
  const [widgets, setWidgets] = useState<WidgetDetail[]>(initialDashboard.widgets)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [isConfigOpen, setIsConfigOpen] = useState(false)
  const [editingWidget, setEditingWidget] = useState<WidgetDetail | null>(null)

  const isDirty = useMemo(() => {
    if (title.trim() !== currentDashboard.title.trim()) return true
    if ((description.trim() || null) !== (currentDashboard.description ?? null)) return true
    if (widgets.length !== currentDashboard.widgets.length) return true
    return JSON.stringify(widgets) !== JSON.stringify(currentDashboard.widgets)
  }, [title, description, widgets, currentDashboard])

  const openCreateWidget = useCallback(() => {
    setEditingWidget(null)
    setIsConfigOpen(true)
  }, [])

  const openEditWidget = useCallback((widget: WidgetDetail) => {
    setEditingWidget(widget)
    setIsConfigOpen(true)
  }, [])

  const closeConfigSheet = useCallback(() => {
    setEditingWidget(null)
    setIsConfigOpen(false)
  }, [])

  const saveWidgetConfig = useCallback(
    (values: WidgetFormValues) => {
      if (editingWidget) {
        setWidgets((prev) =>
          prev.map((w) => {
            if (w.id !== editingWidget.id) return w
            const newW = Math.min(12 - w.position.x, Math.max(1, values.w))
            return {
              ...w,
              title: values.title.trim(),
              type: values.type,
              query_config: {
                dataset: values.dataset,
                metric: values.metric,
                dimension: values.dimension ?? null,
                date_range: values.date_range ?? null,
              },
              position: {
                ...w.position,
                w: newW,
                h: Math.max(1, values.h),
              },
            }
          }),
        )
      } else {
        // Calculate new position at bottom of grid
        const maxY = widgets.reduce(
          (max, w) => Math.max(max, w.position.y + w.position.h),
          0,
        )
        const safeW = Math.min(12, Math.max(1, values.w))
        const newWidget: WidgetDetail = {
          id: typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `w-${Date.now()}`,
          title: values.title.trim(),
          type: values.type,
          query_config: {
            dataset: values.dataset,
            metric: values.metric,
            dimension: values.dimension ?? null,
            date_range: values.date_range ?? null,
          },
          position: {
            x: 0,
            y: maxY,
            w: safeW,
            h: Math.max(1, values.h),
          },
          options: {},
        }
        setWidgets((prev) => [...prev, newWidget])
      }
      closeConfigSheet()
    },
    [editingWidget, widgets, closeConfigSheet],
  )

  const removeWidget = useCallback((widgetId: string) => {
    setWidgets((prev) => prev.filter((w) => w.id !== widgetId))
  }, [])

  const moveWidget = useCallback(
    (widgetId: string, direction: 'up' | 'down' | 'left' | 'right') => {
      setWidgets((prev) =>
        prev.map((w) => {
          if (w.id !== widgetId) return w
          let { x, y } = w.position
          if (direction === 'left') {
            x = Math.max(0, x - 1)
          } else if (direction === 'right') {
            x = Math.min(12 - w.position.w, x + 1)
          } else if (direction === 'up') {
            y = Math.max(0, y - 1)
          } else if (direction === 'down') {
            y = y + 1
          }
          return {
            ...w,
            position: { ...w.position, x, y },
          }
        }),
      )
    },
    [],
  )

  const resizeWidget = useCallback((widgetId: string, deltaW: number, deltaH: number) => {
    setWidgets((prev) =>
      prev.map((w) => {
        if (w.id !== widgetId) return w
        const maxW = 12 - w.position.x
        const newW = Math.max(1, Math.min(maxW, w.position.w + deltaW))
        const newH = Math.max(1, Math.min(24, w.position.h + deltaH))
        return {
          ...w,
          position: { ...w.position, w: newW, h: newH },
        }
      }),
    )
  }, [])

  const repositionWidget = useCallback((widgetId: string, targetX: number, targetY: number) => {
    setWidgets((prev) =>
      prev.map((w) => {
        if (w.id !== widgetId) return w
        const safeX = Math.max(0, Math.min(12 - w.position.w, Math.round(targetX)))
        const safeY = Math.max(0, Math.round(targetY))
        return {
          ...w,
          position: { ...w.position, x: safeX, y: safeY },
        }
      }),
    )
  }, [])

  const saveDashboard = useCallback(async (): Promise<boolean> => {
    if (!title.trim()) {
      setError('Название дашборда не может быть пустым')
      return false
    }

    setIsSaving(true)
    setError(null)

    try {
      const widgetInputs: WidgetInput[] = widgets.map((w) => ({
        id: w.id,
        title: w.title,
        type: w.type,
        query_config: w.query_config,
        position: w.position,
        options: w.options ?? {},
      }))

      const payload: UpdateDashboardRequest = {
        title: title.trim(),
        description: description.trim() || null,
        widgets: widgetInputs,
      }

      const updated = await dashboardGateway.update(
        currentDashboard.id,
        userId,
        payload,
        workspaceId,
      )

      setCurrentDashboard(updated)
      setTitle(updated.title)
      setDescription(updated.description ?? '')
      setWidgets(updated.widgets)
      setMode('view')
      onSaveSuccess?.(updated)
      return true
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Не удалось сохранить изменения'
      setError(msg)
      return false
    } finally {
      setIsSaving(false)
    }
  }, [title, description, widgets, currentDashboard.id, userId, workspaceId, onSaveSuccess])

  const discardChanges = useCallback(() => {
    setTitle(currentDashboard.title)
    setDescription(currentDashboard.description ?? '')
    setWidgets(currentDashboard.widgets)
    setError(null)
    setMode('view')
  }, [currentDashboard])

  return {
    mode,
    title,
    description,
    widgets,
    isDirty,
    isSaving,
    error,
    editingWidget,
    isConfigOpen,
    setMode,
    setTitle,
    setDescription,
    openCreateWidget,
    openEditWidget,
    closeConfigSheet,
    saveWidgetConfig,
    removeWidget,
    moveWidget,
    resizeWidget,
    repositionWidget,
    saveDashboard,
    discardChanges,
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx vitest run src/features/dashboard/model/use-dashboard-builder.test.ts`
Expected: PASS (all 7 tests passing)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/model/use-dashboard-builder.ts frontend/src/features/dashboard/model/use-dashboard-builder.test.ts
git commit -m "feat(dashboard): implement useDashboardBuilder hook for edit mode and grid operations"
```

---

### Task 2: Semantic Widget Configuration Sheet (`WidgetConfigSheet`)

**Files:**
- Create: `frontend/src/features/dashboard/ui/widget-config-sheet.tsx`
- Test: `frontend/src/features/dashboard/ui/widget-config-sheet.test.tsx`

**Interfaces:**
- Consumes:
  - `WidgetDetail`, `WidgetFormValues` from `../model/use-dashboard-builder`
  - Components: `Sheet`, `SheetContent`, `SheetHeader`, `SheetTitle`, `SheetDescription` from `@/components/ui/sheet`, `Button` from `@/components/ui/button`, `Input` from `@/components/ui/input`, `Label` from `@/components/ui/label`, `Select`, `SelectTrigger`, `SelectValue`, `SelectContent`, `SelectItem` from `@/components/ui/select`.
- Produces:
  - `WidgetConfigSheet({ open, onOpenChange, editingWidget, onSave }: WidgetConfigSheetProps)`

- [ ] **Step 1: Write the failing test**

Create `frontend/src/features/dashboard/ui/widget-config-sheet.test.tsx`:
```tsx
import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { WidgetConfigSheet } from './widget-config-sheet'
import type { WidgetDetail } from '../api/dashboard-gateway'

const mockWidget: WidgetDetail = {
  id: 'w-1',
  title: 'Выручка за месяц',
  type: 'kpi_card',
  query_config: {
    dataset: 'sales',
    metric: 'revenue',
    dimension: null,
    date_range: '30d',
  },
  position: { x: 0, y: 0, w: 4, h: 2 },
  options: {},
}

describe('WidgetConfigSheet', () => {
  it('renders create mode with initial default fields', () => {
    render(
      <WidgetConfigSheet
        open={true}
        onOpenChange={vi.fn()}
        editingWidget={null}
        onSave={vi.fn()}
      />,
    )

    expect(screen.getByText('Добавить виджет')).toBeInTheDocument()
    expect(screen.getByLabelText(/Название виджета/i)).toHaveValue('')
    expect(screen.getByTestId('submit-widget-btn')).toBeInTheDocument()
  })

  it('populates fields from editingWidget in edit mode', () => {
    render(
      <WidgetConfigSheet
        open={true}
        onOpenChange={vi.fn()}
        editingWidget={mockWidget}
        onSave={vi.fn()}
      />,
    )

    expect(screen.getByText('Настройка виджета')).toBeInTheDocument()
    expect(screen.getByLabelText(/Название виджета/i)).toHaveValue('Выручка за месяц')
  })

  it('validates title and submits valid form data', () => {
    const onSave = vi.fn()
    const onOpenChange = vi.fn()

    render(
      <WidgetConfigSheet
        open={true}
        onOpenChange={onOpenChange}
        editingWidget={null}
        onSave={onSave}
      />,
    )

    const titleInput = screen.getByLabelText(/Название виджета/i)
    fireEvent.change(titleInput, { target: { value: 'Маржинальность по категориям' } })

    const submitBtn = screen.getByTestId('submit-widget-btn')
    fireEvent.click(submitBtn)

    expect(onSave).toHaveBeenCalledWith(
      expect.objectContaining({
        title: 'Маржинальность по категориям',
        dataset: 'sales',
        metric: 'revenue',
      }),
    )
  })

  it('calls onOpenChange(false) when cancel button is clicked', () => {
    const onOpenChange = vi.fn()
    render(
      <WidgetConfigSheet
        open={true}
        onOpenChange={onOpenChange}
        editingWidget={null}
        onSave={vi.fn()}
      />,
    )

    const cancelBtn = screen.getByRole('button', { name: /Отмена/i })
    fireEvent.click(cancelBtn)
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run src/features/dashboard/ui/widget-config-sheet.test.tsx`
Expected: FAIL with "Cannot find module './widget-config-sheet'"

- [ ] **Step 3: Write minimal implementation**

Create `frontend/src/features/dashboard/ui/widget-config-sheet.tsx`:
```tsx
'use client'

import React, { useState, useEffect } from 'react'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { WidgetDetail } from '../api/dashboard-gateway'
import type { WidgetFormValues } from '../model/use-dashboard-builder'

interface WidgetConfigSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  editingWidget: WidgetDetail | null
  onSave: (values: WidgetFormValues) => void
}

const DATASET_METRICS: Record<
  'sales' | 'inventory',
  { value: WidgetFormValues['metric']; label: string }[]
> = {
  sales: [
    { value: 'revenue', label: 'Выручка (руб)' },
    { value: 'order_count', label: 'Количество заказов' },
    { value: 'average_order_value', label: 'Средний чек' },
    { value: 'gross_profit', label: 'Валовая прибыль' },
    { value: 'margin_rate', label: 'Рентабельность (%)' },
  ],
  inventory: [
    { value: 'stock_quantity', label: 'Остаток (шт)' },
    { value: 'stock_value', label: 'Стоимость запасов (руб)' },
    { value: 'out_of_stock_count', label: 'Позиции без остатка' },
    { value: 'overstock_count', label: 'Позиции в избытке' },
  ],
}

const DATASET_DIMENSIONS: Record<
  'sales' | 'inventory',
  { value: NonNullable<WidgetFormValues['dimension']>; label: string }[]
> = {
  sales: [
    { value: 'date', label: 'По датам (динамика)' },
    { value: 'category', label: 'По категориям' },
    { value: 'region', label: 'По регионам' },
  ],
  inventory: [
    { value: 'warehouse', label: 'По складам' },
    { value: 'abc_class', label: 'По ABC-классам' },
    { value: 'xyz_class', label: 'По XYZ-классам' },
    { value: 'supplier', label: 'По поставщикам' },
  ],
}

const DEFAULT_SIZES: Record<WidgetDetail['type'], { w: number; h: number }> = {
  kpi_card: { w: 3, h: 2 },
  line_chart: { w: 8, h: 4 },
  bar_chart: { w: 6, h: 4 },
  donut_chart: { w: 4, h: 4 },
  table: { w: 12, h: 5 },
}

export function WidgetConfigSheet({
  open,
  onOpenChange,
  editingWidget,
  onSave,
}: WidgetConfigSheetProps) {
  const [title, setTitle] = useState('')
  const [type, setType] = useState<WidgetDetail['type']>('kpi_card')
  const [dataset, setDataset] = useState<'sales' | 'inventory'>('sales')
  const [metric, setMetric] = useState<WidgetFormValues['metric']>('revenue')
  const [dimension, setDimension] = useState<string>('none')
  const [dateRange, setDateRange] = useState<string>('30d')
  const [width, setWidth] = useState<number>(3)
  const [height, setHeight] = useState<number>(2)
  const [validationError, setValidationError] = useState<string | null>(null)

  useEffect(() => {
    if (editingWidget) {
      setTitle(editingWidget.title)
      setType(editingWidget.type)
      setDataset(editingWidget.query_config.dataset as 'sales' | 'inventory')
      setMetric(editingWidget.query_config.metric)
      setDimension(editingWidget.query_config.dimension ?? 'none')
      setDateRange(editingWidget.query_config.date_range ?? '30d')
      setWidth(editingWidget.position.w)
      setHeight(editingWidget.position.h)
    } else {
      setTitle('')
      setType('kpi_card')
      setDataset('sales')
      setMetric('revenue')
      setDimension('none')
      setDateRange('30d')
      setWidth(DEFAULT_SIZES.kpi_card.w)
      setHeight(DEFAULT_SIZES.kpi_card.h)
    }
    setValidationError(null)
  }, [editingWidget, open])

  const handleDatasetChange = (newDataset: 'sales' | 'inventory') => {
    setDataset(newDataset)
    const firstMetric = DATASET_METRICS[newDataset][0].value
    setMetric(firstMetric)
    if (type === 'line_chart') {
      setDimension(newDataset === 'sales' ? 'date' : 'warehouse')
    } else if (dimension !== 'none') {
      setDimension(DATASET_DIMENSIONS[newDataset][0].value)
    }
  }

  const handleTypeChange = (newType: WidgetDetail['type']) => {
    setType(newType)
    const defaults = DEFAULT_SIZES[newType]
    setWidth(defaults.w)
    setHeight(defaults.h)

    if (newType === 'kpi_card') {
      setDimension('none')
    } else if (newType === 'line_chart') {
      setDimension('date')
    } else if (dimension === 'none') {
      setDimension(DATASET_DIMENSIONS[dataset][0].value)
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!title.trim()) {
      setValidationError('Укажите название виджета')
      return
    }

    onSave({
      title: title.trim(),
      type,
      dataset,
      metric,
      dimension: dimension === 'none' ? null : (dimension as WidgetFormValues['dimension']),
      date_range: (dateRange === 'all' ? null : dateRange) as WidgetFormValues['date_range'],
      w: width,
      h: height,
    })
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="p-6 overflow-y-auto sm:max-w-md">
        <SheetHeader>
          <SheetTitle>{editingWidget ? 'Настройка виджета' : 'Добавить виджет'}</SheetTitle>
          <SheetDescription>
            Выберите тип визуализации, источник данных и аналитические параметры.
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="mt-6 space-y-4">
          {validationError && (
            <div className="p-3 text-xs rounded-md bg-rose-500/10 border border-rose-500/20 text-rose-400">
              {validationError}
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="widget-title">Название виджета</Label>
            <Input
              id="widget-title"
              placeholder="Например, Динамика выручки"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              className="h-9 text-sm"
              autoFocus
            />
          </div>

          <div className="space-y-1.5">
            <Label>Тип визуализации</Label>
            <Select value={type} onValueChange={(val) => handleTypeChange(val as WidgetDetail['type'])}>
              <SelectTrigger className="w-full h-9 text-xs">
                <SelectValue placeholder="Выберите тип" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="kpi_card">KPI карточка (показатель)</SelectItem>
                <SelectItem value="line_chart">Линейный график</SelectItem>
                <SelectItem value="bar_chart">Столбчатая диаграмма</SelectItem>
                <SelectItem value="donut_chart">Круговая диаграмма (donut)</SelectItem>
                <SelectItem value="table">Таблица среза данных</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label>Набор данных</Label>
              <Select
                value={dataset}
                onValueChange={(val) => handleDatasetChange(val as 'sales' | 'inventory')}
              >
                <SelectTrigger className="w-full h-9 text-xs">
                  <SelectValue placeholder="Dataset" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="sales">Продажи (Sales)</SelectItem>
                  <SelectItem value="inventory">Склад (Inventory)</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label>Период дат</Label>
              <Select value={dateRange} onValueChange={setDateRange}>
                <SelectTrigger className="w-full h-9 text-xs">
                  <SelectValue placeholder="Период" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="30d">Последние 30 дней</SelectItem>
                  <SelectItem value="90d">Последние 90 дней</SelectItem>
                  <SelectItem value="180d">Последние 180 дней</SelectItem>
                  <SelectItem value="365d">Последний год</SelectItem>
                  <SelectItem value="all">Все время</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-1.5">
            <Label>Метрика</Label>
            <Select
              value={metric}
              onValueChange={(val) => setMetric(val as WidgetFormValues['metric'])}
            >
              <SelectTrigger className="w-full h-9 text-xs">
                <SelectValue placeholder="Выберите метрику" />
              </SelectTrigger>
              <SelectContent>
                {DATASET_METRICS[dataset].map((m) => (
                  <SelectItem key={m.value} value={m.value}>
                    {m.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {type !== 'kpi_card' && (
            <div className="space-y-1.5">
              <Label>Разрез / Измерение (Dimension)</Label>
              <Select value={dimension} onValueChange={setDimension}>
                <SelectTrigger className="w-full h-9 text-xs">
                  <SelectValue placeholder="Измерение" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Без детализации</SelectItem>
                  {DATASET_DIMENSIONS[dataset].map((d) => (
                    <SelectItem key={d.value} value={d.value}>
                      {d.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="grid grid-cols-2 gap-3 pt-2 border-t border-border/50">
            <div className="space-y-1.5">
              <Label htmlFor="widget-w">Ширина (колонок: 1-12)</Label>
              <Input
                id="widget-w"
                type="number"
                min={1}
                max={12}
                value={width}
                onChange={(e) => setWidth(Math.max(1, Math.min(12, Number(e.target.value) || 1)))}
                className="h-9 text-xs"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="widget-h">Высота (строк: 1-12)</Label>
              <Input
                id="widget-h"
                type="number"
                min={1}
                max={12}
                value={height}
                onChange={(e) => setHeight(Math.max(1, Math.min(12, Number(e.target.value) || 1)))}
                className="h-9 text-xs"
              />
            </div>
          </div>

          <div className="flex items-center justify-end gap-2 pt-4">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => onOpenChange(false)}
              className="text-xs"
            >
              Отмена
            </Button>
            <Button
              type="submit"
              size="sm"
              data-testid="submit-widget-btn"
              className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs"
            >
              {editingWidget ? 'Сохранить виджет' : 'Добавить виджет'}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  )
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx vitest run src/features/dashboard/ui/widget-config-sheet.test.tsx`
Expected: PASS (all 4 tests passing)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/ui/widget-config-sheet.tsx frontend/src/features/dashboard/ui/widget-config-sheet.test.tsx
git commit -m "feat(dashboard): implement WidgetConfigSheet for semantic widget creation and editing"
```

---

### Task 3: Interactive Grid Editor & Widget Container (`DashboardGridEditor` & `WidgetEditorCard`)

**Files:**
- Create: `frontend/src/features/dashboard/ui/widget-editor-card.tsx`
- Create: `frontend/src/features/dashboard/ui/dashboard-grid-editor.tsx`
- Test: `frontend/src/features/dashboard/ui/dashboard-grid-editor.test.tsx`

**Interfaces:**
- Consumes:
  - `WidgetDetail` from `../api/dashboard-gateway`
  - `WidgetRenderer` from `./widgets/widget-renderer`
  - `Badge` from `@/components/ui/badge`, `Button` from `@/components/ui/button`
  - Icons: `GripVertical`, `Pencil`, `Trash2`, `ArrowLeft`, `ArrowRight`, `ArrowUp`, `ArrowDown`, `Plus`, `Minus` from `lucide-react`.
- Produces:
  - `WidgetEditorCard({ widget, userId, workspaceId, onMove, onResize, onEdit, onDelete, onDragStart, onDrop }: WidgetEditorCardProps)`
  - `DashboardGridEditor({ widgets, userId, workspaceId, onMove, onResize, onReposition, onEdit, onDelete, onAddWidget }: DashboardGridEditorProps)`

- [ ] **Step 1: Write the failing test**

Create `frontend/src/features/dashboard/ui/dashboard-grid-editor.test.tsx`:
```tsx
import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { DashboardGridEditor } from './dashboard-grid-editor'
import type { WidgetDetail } from '../api/dashboard-gateway'

vi.mock('./widgets/widget-renderer', () => ({
  WidgetRenderer: ({ widget }: { widget: WidgetDetail }) => (
    <div data-testid={`rendered-${widget.id}`}>{widget.title}</div>
  ),
}))

const mockWidgets: WidgetDetail[] = [
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
    query_config: { dataset: 'sales', metric: 'revenue', dimension: 'date', date_range: '30d' },
    position: { x: 4, y: 0, w: 8, h: 4 },
    options: {},
  },
]

describe('DashboardGridEditor', () => {
  it('renders all widgets with editing toolbars and controls', () => {
    render(
      <DashboardGridEditor
        widgets={mockWidgets}
        userId="user-1"
        workspaceId="ws-1"
        onMove={vi.fn()}
        onResize={vi.fn()}
        onReposition={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onAddWidget={vi.fn()}
      />,
    )

    expect(screen.getByText('Выручка')).toBeInTheDocument()
    expect(screen.getByText('Динамика продаж')).toBeInTheDocument()
    expect(screen.getAllByLabelText(/Настроить виджет/i)).toHaveLength(2)
    expect(screen.getAllByLabelText(/Удалить виджет/i)).toHaveLength(2)
  })

  it('triggers onEdit and onDelete callbacks when buttons are clicked', () => {
    const onEdit = vi.fn()
    const onDelete = vi.fn()

    render(
      <DashboardGridEditor
        widgets={mockWidgets}
        userId="user-1"
        workspaceId="ws-1"
        onMove={vi.fn()}
        onResize={vi.fn()}
        onReposition={vi.fn()}
        onEdit={onEdit}
        onDelete={onDelete}
        onAddWidget={vi.fn()}
      />,
    )

    const editBtns = screen.getAllByLabelText(/Настроить виджет/i)
    fireEvent.click(editBtns[0])
    expect(onEdit).toHaveBeenCalledWith(mockWidgets[0])

    const deleteBtns = screen.getAllByLabelText(/Удалить виджет/i)
    fireEvent.click(deleteBtns[0])
    expect(onDelete).toHaveBeenCalledWith('w-1')
  })

  it('triggers onMove and onResize callbacks', () => {
    const onMove = vi.fn()
    const onResize = vi.fn()

    render(
      <DashboardGridEditor
        widgets={mockWidgets}
        userId="user-1"
        workspaceId="ws-1"
        onMove={onMove}
        onResize={onResize}
        onReposition={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onAddWidget={vi.fn()}
      />,
    )

    // w-1 is at x=0, left button should be disabled, right button should call onMove
    const moveRightBtns = screen.getAllByLabelText(/Сдвинуть вправо/i)
    fireEvent.click(moveRightBtns[0])
    expect(onMove).toHaveBeenCalledWith('w-1', 'right')

    // Width increase button
    const expandWidthBtns = screen.getAllByLabelText(/Увеличить ширину/i)
    fireEvent.click(expandWidthBtns[0])
    expect(onResize).toHaveBeenCalledWith('w-1', 1, 0)
  })

  it('renders add widget prompt when grid is empty', () => {
    const onAddWidget = vi.fn()
    render(
      <DashboardGridEditor
        widgets={[]}
        userId="user-1"
        workspaceId="ws-1"
        onMove={vi.fn()}
        onResize={vi.fn()}
        onReposition={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onAddWidget={onAddWidget}
      />,
    )

    expect(screen.getByText(/Сетка пуста/i)).toBeInTheDocument()
    const addBtn = screen.getByRole('button', { name: /Добавить первый виджет/i })
    fireEvent.click(addBtn)
    expect(onAddWidget).toHaveBeenCalledTimes(1)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run src/features/dashboard/ui/dashboard-grid-editor.test.tsx`
Expected: FAIL with "Cannot find module './dashboard-grid-editor'"

- [ ] **Step 3: Write minimal implementation**

Create `frontend/src/features/dashboard/ui/widget-editor-card.tsx`:
```tsx
'use client'

import React from 'react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  GripVertical,
  Pencil,
  Trash2,
  ArrowLeft,
  ArrowRight,
  ArrowUp,
  ArrowDown,
  Plus,
  Minus,
} from 'lucide-react'
import type { WidgetDetail } from '../api/dashboard-gateway'
import { WidgetRenderer } from './widgets/widget-renderer'

interface WidgetEditorCardProps {
  widget: WidgetDetail
  userId: string
  workspaceId: string
  onMove: (id: string, direction: 'up' | 'down' | 'left' | 'right') => void
  onResize: (id: string, deltaW: number, deltaH: number) => void
  onEdit: (widget: WidgetDetail) => void
  onDelete: (id: string) => void
  onDragStart: (e: React.DragEvent, id: string) => void
  onDrop: (e: React.DragEvent, targetId: string) => void
}

export function WidgetEditorCard({
  widget,
  userId,
  workspaceId,
  onMove,
  onResize,
  onEdit,
  onDelete,
  onDragStart,
  onDrop,
}: WidgetEditorCardProps) {
  const { x, y, w, h } = widget.position
  const isAtLeftEdge = x === 0
  const isAtRightEdge = x + w >= 12
  const isAtTopEdge = y === 0
  const isAtMaxWidth = x + w >= 12
  const isAtMinWidth = w <= 1
  const isAtMinHeight = h <= 1

  return (
    <div
      draggable
      onDragStart={(e) => onDragStart(e, widget.id)}
      onDragOver={(e) => e.preventDefault()}
      onDrop={(e) => onDrop(e, widget.id)}
      className="group relative flex flex-col h-full w-full rounded-xl border-2 border-dashed border-emerald-500/40 bg-card/60 shadow-sm transition-all hover:border-emerald-500 overflow-hidden"
    >
      {/* Editor Header Toolbar */}
      <div className="flex items-center justify-between gap-1 px-3 py-1.5 bg-muted/40 border-b border-border/60 text-xs">
        <div className="flex items-center gap-1.5 min-w-0">
          <div className="cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground">
            <GripVertical className="size-4" />
          </div>
          <span className="font-medium truncate max-w-[120px] sm:max-w-[200px]" title={widget.title}>
            {widget.title}
          </span>
          <Badge variant="outline" className="text-[10px] px-1 py-0 h-4 uppercase">
            {widget.type.replace('_', ' ')}
          </Badge>
        </div>

        <div className="flex items-center gap-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            aria-label="Настроить виджет"
            onClick={() => onEdit(widget)}
            className="h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
          >
            <Pencil className="size-3" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            aria-label="Удалить виджет"
            onClick={() => onDelete(widget.id)}
            className="h-6 w-6 p-0 text-rose-400 hover:text-rose-500 hover:bg-rose-500/10"
          >
            <Trash2 className="size-3" />
          </Button>
        </div>
      </div>

      {/* Embedded Live Renderer (pointer-events-none prevents interaction interference while in edit mode) */}
      <div className="flex-1 p-2 pointer-events-none select-none opacity-90 overflow-hidden">
        <WidgetRenderer widget={widget} userId={userId} workspaceId={workspaceId} />
      </div>

      {/* Editor Controls Footer: Move & Resize */}
      <div className="flex items-center justify-between gap-2 px-3 py-1 bg-muted/30 border-t border-border/40 text-[11px] text-muted-foreground">
        <div className="flex items-center gap-0.5">
          <span className="text-[10px] mr-1">Позиция:</span>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={isAtLeftEdge}
            aria-label="Сдвинуть влево"
            onClick={() => onMove(widget.id, 'left')}
            className="h-5 w-5 p-0"
          >
            <ArrowLeft className="size-2.5" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={isAtRightEdge}
            aria-label="Сдвинуть вправо"
            onClick={() => onMove(widget.id, 'right')}
            className="h-5 w-5 p-0"
          >
            <ArrowRight className="size-2.5" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={isAtTopEdge}
            aria-label="Сдвинуть вверх"
            onClick={() => onMove(widget.id, 'up')}
            className="h-5 w-5 p-0"
          >
            <ArrowUp className="size-2.5" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            aria-label="Сдвинуть вниз"
            onClick={() => onMove(widget.id, 'down')}
            className="h-5 w-5 p-0"
          >
            <ArrowDown className="size-2.5" />
          </Button>
        </div>

        <div className="flex items-center gap-1.5">
          <div className="flex items-center gap-0.5">
            <span className="text-[10px]">Ш:</span>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={isAtMinWidth}
              aria-label="Уменьшить ширину"
              onClick={() => onResize(widget.id, -1, 0)}
              className="h-5 w-5 p-0"
            >
              <Minus className="size-2.5" />
            </Button>
            <span className="text-[10px] font-mono">{w}</span>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={isAtMaxWidth}
              aria-label="Увеличить ширину"
              onClick={() => onResize(widget.id, 1, 0)}
              className="h-5 w-5 p-0"
            >
              <Plus className="size-2.5" />
            </Button>
          </div>

          <div className="flex items-center gap-0.5">
            <span className="text-[10px]">В:</span>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={isAtMinHeight}
              aria-label="Уменьшить высоту"
              onClick={() => onResize(widget.id, 0, -1)}
              className="h-5 w-5 p-0"
            >
              <Minus className="size-2.5" />
            </Button>
            <span className="text-[10px] font-mono">{h}</span>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              aria-label="Увеличить высоту"
              onClick={() => onResize(widget.id, 0, 1)}
              className="h-5 w-5 p-0"
            >
              <Plus className="size-2.5" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
```

Create `frontend/src/features/dashboard/ui/dashboard-grid-editor.tsx`:
```tsx
'use client'

import React from 'react'
import { LayoutGrid, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { WidgetDetail } from '../api/dashboard-gateway'
import { WidgetEditorCard } from './widget-editor-card'

interface DashboardGridEditorProps {
  widgets: WidgetDetail[]
  userId: string
  workspaceId: string
  onMove: (id: string, direction: 'up' | 'down' | 'left' | 'right') => void
  onResize: (id: string, deltaW: number, deltaH: number) => void
  onReposition: (id: string, targetX: number, targetY: number) => void
  onEdit: (widget: WidgetDetail) => void
  onDelete: (id: string) => void
  onAddWidget: () => void
}

export function DashboardGridEditor({
  widgets,
  userId,
  workspaceId,
  onMove,
  onResize,
  onReposition,
  onEdit,
  onDelete,
  onAddWidget,
}: DashboardGridEditorProps) {
  const handleDragStart = (e: React.DragEvent, id: string) => {
    e.dataTransfer.setData('text/plain', id)
    e.dataTransfer.effectAllowed = 'move'
  }

  const handleDrop = (e: React.DragEvent, targetId: string) => {
    e.preventDefault()
    const draggedId = e.dataTransfer.getData('text/plain')
    if (!draggedId || draggedId === targetId) return

    const dragged = widgets.find((w) => w.id === draggedId)
    const target = widgets.find((w) => w.id === targetId)
    if (!dragged || !target) return

    // Swap positions with target widget
    onReposition(dragged.id, target.position.x, target.position.y)
    onReposition(target.id, dragged.position.x, dragged.position.y)
  }

  if (widgets.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-border/80 p-12 text-center bg-card/20">
        <div className="flex size-12 items-center justify-center rounded-full bg-muted/60 text-muted-foreground mb-4">
          <LayoutGrid className="size-6" />
        </div>
        <h3 className="text-base font-semibold text-foreground">Сетка пуста</h3>
        <p className="mt-1 text-sm text-muted-foreground max-w-sm">
          В этом дашборде пока нет виджетов. Добавьте первый виджет, чтобы настроить аналитическую панель.
        </p>
        <Button
          type="button"
          onClick={onAddWidget}
          className="mt-4 gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs"
        >
          <Plus className="size-3.5" />
          <span>Добавить первый виджет</span>
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-12 gap-4 auto-rows-[110px]">
        {widgets.map((widget) => {
          const { x, y, w, h } = widget.position
          const colSpan = Math.min(12 - x, Math.max(1, w))
          const rowSpan = Math.max(1, h)

          return (
            <div
              key={widget.id}
              style={{
                gridColumn: `span ${colSpan} / span ${colSpan}`,
                gridRow: `span ${rowSpan} / span ${rowSpan}`,
              }}
              className="min-h-[160px]"
            >
              <WidgetEditorCard
                widget={widget}
                userId={userId}
                workspaceId={workspaceId}
                onMove={onMove}
                onResize={onResize}
                onEdit={onEdit}
                onDelete={onDelete}
                onDragStart={handleDragStart}
                onDrop={handleDrop}
              />
            </div>
          );
        })}
      </div>
    </div>
  )
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx vitest run src/features/dashboard/ui/dashboard-grid-editor.test.tsx`
Expected: PASS (all 4 tests passing)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/dashboard/ui/widget-editor-card.tsx frontend/src/features/dashboard/ui/dashboard-grid-editor.tsx frontend/src/features/dashboard/ui/dashboard-grid-editor.test.tsx
git commit -m "feat(dashboard): implement DashboardGridEditor and WidgetEditorCard for interactive layout editing"
```

---

### Task 4: Integration into Dashboard Viewer & Builder Header (`DashboardViewer`)

**Files:**
- Modify: `frontend/src/features/dashboard/ui/dashboard-viewer.tsx`
- Modify: `frontend/src/features/dashboard/ui/dashboard-viewer.test.tsx`

**Interfaces:**
- Consumes:
  - `useDashboardBuilder` from `../model/use-dashboard-builder`
  - `DashboardGrid` from `./dashboard-grid`
  - `DashboardGridEditor` from `./dashboard-grid-editor`
  - `WidgetConfigSheet` from `./widget-config-sheet`
  - Components: `Button`, `Input`, `Badge` from `@/components/ui`
  - Icons: `ArrowLeft`, `RefreshCw`, `LayoutDashboard`, `Pencil`, `Plus`, `Save`, `RotateCcw`, `Check`, `AlertCircle` from `lucide-react`.
- Produces:
  - Updated `DashboardViewer({ dashboard, userId, workspaceId }: DashboardViewerProps)` supporting View Mode and Edit Mode.

- [ ] **Step 1: Write the failing test**

Update `frontend/src/features/dashboard/ui/dashboard-viewer.test.tsx`:
```tsx
import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { DashboardViewer } from './dashboard-viewer'
import { dashboardGateway, type DashboardDetail } from '../api/dashboard-gateway'

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    update: vi.fn(),
  },
}))

vi.mock('./dashboard-grid', () => ({
  DashboardGrid: ({ widgets }: { widgets: unknown[] }) => (
    <div data-testid="dashboard-grid-view">Grid View with {widgets.length} widgets</div>
  ),
}))

vi.mock('./widgets/widget-renderer', () => ({
  WidgetRenderer: () => <div>Widget Renderer</div>,
}))

const mockDashboard: DashboardDetail = {
  id: 'dash-1',
  workspace_id: 'ws-1',
  title: 'Сводный дашборд',
  description: 'Аналитика бизнеса',
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
  ],
}

describe('DashboardViewer', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders in view mode with edit button and grid view', () => {
    render(<DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByText('Сводный дашборд')).toBeInTheDocument()
    expect(screen.getByText('Аналитика бизнеса')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Редактировать/i })).toBeInTheDocument()
    expect(screen.getByTestId('dashboard-grid-view')).toBeInTheDocument()
  })

  it('switches to edit mode, allows modifying title and saving changes', async () => {
    vi.mocked(dashboardGateway.update).mockResolvedValueOnce({
      ...mockDashboard,
      title: 'Новый Сводный дашборд',
    })

    render(<DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />)

    // Switch to edit mode
    const editBtn = screen.getByRole('button', { name: /Редактировать/i })
    fireEvent.click(editBtn)

    expect(screen.getByTestId('dashboard-title-input')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Добавить виджет/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Сохранить/i })).toBeInTheDocument()

    // Change title
    const titleInput = screen.getByTestId('dashboard-title-input')
    fireEvent.change(titleInput, { target: { value: 'Новый Сводный дашборд' } })

    // Save
    const saveBtn = screen.getByRole('button', { name: /Сохранить/i })
    fireEvent.click(saveBtn)

    await waitFor(() => {
      expect(dashboardGateway.update).toHaveBeenCalledWith(
        'dash-1',
        'user-1',
        expect.objectContaining({
          title: 'Новый Сводный дашборд',
        }),
        'ws-1',
      )
    })
  })

  it('allows canceling edit mode and discarding changes', () => {
    render(<DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />)

    // Switch to edit mode
    fireEvent.click(screen.getByRole('button', { name: /Редактировать/i }))
    const titleInput = screen.getByTestId('dashboard-title-input')
    fireEvent.change(titleInput, { target: { value: 'Изменение' } })

    // Click cancel
    const cancelBtn = screen.getByRole('button', { name: /Отмена/i })
    fireEvent.click(cancelBtn)

    // Back in view mode with original title
    expect(screen.queryByTestId('dashboard-title-input')).not.toBeInTheDocument()
    expect(screen.getByText('Сводный дашборд')).toBeInTheDocument()
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run src/features/dashboard/ui/dashboard-viewer.test.tsx`
Expected: FAIL with "Unable to find element by: [role="button"][name=/Редактировать/i]"

- [ ] **Step 3: Write minimal implementation**

Update `frontend/src/features/dashboard/ui/dashboard-viewer.tsx`:
```tsx
'use client'

import React, { useState } from 'react'
import Link from 'next/link'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  ArrowLeft,
  RefreshCw,
  LayoutDashboard,
  Pencil,
  Plus,
  Save,
  RotateCcw,
  Check,
  AlertCircle,
} from 'lucide-react'
import type { DashboardDetail } from '../api/dashboard-gateway'
import { useDashboardBuilder } from '../model/use-dashboard-builder'
import { DashboardGrid } from './dashboard-grid'
import { DashboardGridEditor } from './dashboard-grid-editor'
import { WidgetConfigSheet } from './widget-config-sheet'

interface DashboardViewerProps {
  dashboard: DashboardDetail
  userId: string
  workspaceId: string
}

export function DashboardViewer({ dashboard, userId, workspaceId }: DashboardViewerProps) {
  const [refreshKey, setRefreshKey] = useState(0)
  const [saveSuccessMsg, setSaveSuccessMsg] = useState(false)

  const builder = useDashboardBuilder({
    initialDashboard: dashboard,
    userId,
    workspaceId,
    onSaveSuccess: () => {
      setSaveSuccessMsg(true)
      setTimeout(() => setSaveSuccessMsg(false), 3000)
    },
  })

  const {
    mode,
    title,
    description,
    widgets,
    isDirty,
    isSaving,
    error,
    editingWidget,
    isConfigOpen,
    setMode,
    setTitle,
    setDescription,
    openCreateWidget,
    openEditWidget,
    closeConfigSheet,
    saveWidgetConfig,
    removeWidget,
    moveWidget,
    resizeWidget,
    repositionWidget,
    saveDashboard,
    discardChanges,
  } = builder

  return (
    <div className="space-y-6">
      {/* Top Header / Builder Toolbar */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 pb-4 border-b border-border/60">
        <div className="space-y-2 flex-1">
          <div className="flex items-center gap-2">
            <Link href="/dashboards">
              <Button
                variant="ghost"
                size="sm"
                className="h-8 px-2 text-xs gap-1.5 text-muted-foreground hover:text-foreground"
              >
                <ArrowLeft className="size-3.5" />
                <span>Назад к списку</span>
              </Button>
            </Link>

            {mode === 'edit' && (
              <Badge variant="secondary" className="text-xs bg-amber-500/10 text-amber-500 border-amber-500/20">
                Режим редактирования
              </Badge>
            )}

            {isDirty && mode === 'edit' && (
              <Badge variant="outline" className="text-xs text-muted-foreground">
                Несохранённые изменения
              </Badge>
            )}

            {saveSuccessMsg && (
              <Badge variant="outline" className="text-xs text-emerald-500 border-emerald-500/30 flex items-center gap-1">
                <Check className="size-3" />
                <span>Сохранено</span>
              </Badge>
            )}
          </div>

          {mode === 'view' ? (
            <div className="space-y-1">
              <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <LayoutDashboard className="size-6 text-emerald-500" />
                {title}
              </h1>
              {description && (
                <p className="text-sm text-muted-foreground leading-relaxed">{description}</p>
              )}
            </div>
          ) : (
            <div className="space-y-3 max-w-xl">
              <div>
                <Input
                  data-testid="dashboard-title-input"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="Название дашборда"
                  className="h-10 text-lg font-semibold"
                />
              </div>
              <div>
                <Input
                  data-testid="dashboard-desc-input"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Описание дашборда (необязательно)"
                  className="h-8 text-xs text-muted-foreground"
                />
              </div>
            </div>
          )}
        </div>

        {/* Action Buttons */}
        <div className="flex flex-wrap items-center gap-2 sm:self-start">
          {mode === 'view' ? (
            <>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setRefreshKey((k) => k + 1)}
                className="h-9 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
              >
                <RefreshCw className="size-3.5" />
                <span>Обновить данные</span>
              </Button>
              <Button
                size="sm"
                onClick={() => setMode('edit')}
                className="h-9 gap-1.5 text-xs bg-emerald-600 hover:bg-emerald-500 text-white"
              >
                <Pencil className="size-3.5" />
                <span>Редактировать</span>
              </Button>
            </>
          ) : (
            <>
              <Button
                variant="outline"
                size="sm"
                onClick={openCreateWidget}
                className="h-9 gap-1.5 text-xs border-emerald-500/40 hover:border-emerald-500 text-emerald-500 hover:text-emerald-400"
              >
                <Plus className="size-3.5" />
                <span>Добавить виджет</span>
              </Button>
              <Button
                variant="ghost"
                size="sm"
                onClick={discardChanges}
                className="h-9 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
              >
                <RotateCcw className="size-3.5" />
                <span>Отмена</span>
              </Button>
              <Button
                size="sm"
                disabled={!isDirty || isSaving}
                onClick={saveDashboard}
                className="h-9 gap-1.5 text-xs bg-emerald-600 hover:bg-emerald-500 text-white disabled:opacity-50"
              >
                <Save className="size-3.5" />
                <span>{isSaving ? 'Сохранение...' : 'Сохранить'}</span>
              </Button>
            </>
          )}
        </div>
      </div>

      {/* Error banner */}
      {error && (
        <div className="flex items-center gap-2 p-3 text-xs rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-400">
          <AlertCircle className="size-4 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* Main Grid: View or Edit Mode */}
      {mode === 'view' ? (
        <DashboardGrid
          key={refreshKey}
          widgets={widgets}
          userId={userId}
          workspaceId={workspaceId}
        />
      ) : (
        <DashboardGridEditor
          widgets={widgets}
          userId={userId}
          workspaceId={workspaceId}
          onMove={moveWidget}
          onResize={resizeWidget}
          onReposition={repositionWidget}
          onEdit={openEditWidget}
          onDelete={removeWidget}
          onAddWidget={openCreateWidget}
        />
      )}

      {/* Widget Configuration Sheet */}
      <WidgetConfigSheet
        open={isConfigOpen}
        onOpenChange={(open) => {
          if (!open) closeConfigSheet()
        }}
        editingWidget={editingWidget}
        onSave={saveWidgetConfig}
      />
    </div>
  )
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx vitest run src/features/dashboard/ui/dashboard-viewer.test.tsx`
Expected: PASS (all 3 tests passing)

- [ ] **Step 5: Run full frontend test suite and linters**

Run:
```bash
cd frontend && npm test && npm run typecheck && npm run lint
```
Expected: PASS with 0 errors across all files.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/features/dashboard/ui/dashboard-viewer.tsx frontend/src/features/dashboard/ui/dashboard-viewer.test.tsx
git commit -m "feat(dashboard): integrate builder toolbar, edit mode toggling, and saving into DashboardViewer"
```
