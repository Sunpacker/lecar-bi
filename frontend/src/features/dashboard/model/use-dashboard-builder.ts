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

function generateWidgetId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  // Fallback RFC 4122 v4 UUID generator
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

export function useDashboardBuilder({
  initialDashboard,
  userId,
  workspaceId,
  onSaveSuccess,
}: UseDashboardBuilderOptions) {
  const [currentDashboard, setCurrentDashboard] =
    useState<DashboardDetail>(initialDashboard)
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
    if ((description.trim() || null) !== (currentDashboard.description ?? null))
      return true
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
          id: generateWidgetId(),
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

  const repositionWidget = useCallback(
    (widgetId: string, targetX: number, targetY: number) => {
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
    },
    [],
  )

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
  }, [
    title,
    description,
    widgets,
    currentDashboard.id,
    userId,
    workspaceId,
    onSaveSuccess,
  ])

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
