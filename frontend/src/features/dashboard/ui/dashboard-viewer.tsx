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
import {
  dashboardGateway,
  type DashboardDetail,
  type DashboardSavedView,
} from '../api/dashboard-gateway'
import { useDashboardBuilder } from '../model/use-dashboard-builder'
import { useDashboardFilters } from '../model/use-dashboard-filters'
import { DashboardGrid } from './dashboard-grid'
import { DashboardGridEditor } from './dashboard-grid-editor'
import { WidgetConfigSheet } from './widget-config-sheet'
import { DashboardFilterBar } from './dashboard-filter-bar'
import { DashboardSavedViewsMenu } from './dashboard-saved-views-menu'
import { useWorkspaceAccess } from '../../workspace/ui/workspace-access-provider'

interface DashboardViewerProps {
  dashboard: DashboardDetail
  userId: string
  workspaceId: string
}

export function DashboardViewer({
  dashboard,
  userId,
  workspaceId,
}: DashboardViewerProps) {
  const { hasCapability } = useWorkspaceAccess()
  const canManageDashboards = hasCapability('dashboards.manage')

  const [refreshKey, setRefreshKey] = useState(0)
  const [saveSuccessMsg, setSaveSuccessMsg] = useState(false)
  const [savedViews, setSavedViews] = useState<DashboardSavedView[]>([])

  const loadSavedViews = React.useCallback(() => {
    dashboardGateway
      .listSavedViews(dashboard.id, userId, workspaceId)
      .then((items) => setSavedViews(items))
      .catch(() => setSavedViews([]))
  }, [dashboard.id, userId, workspaceId])

  React.useEffect(() => {
    loadSavedViews()
  }, [loadSavedViews])

  const filterManager = useDashboardFilters({
    savedViews,
  })

  const { filters, activeView, setFilters, applySavedView } = filterManager

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
              <Badge
                variant="secondary"
                className="text-xs bg-amber-500/10 text-amber-500 border-amber-500/20"
              >
                Режим редактирования
              </Badge>
            )}

            {isDirty && mode === 'edit' && (
              <Badge variant="outline" className="text-xs text-muted-foreground">
                Несохранённые изменения
              </Badge>
            )}

            {saveSuccessMsg && (
              <Badge
                variant="outline"
                className="text-xs text-emerald-500 border-emerald-500/30 flex items-center gap-1"
              >
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
                <p className="text-sm text-muted-foreground leading-relaxed">
                  {description}
                </p>
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
              {canManageDashboards && (
                <Button
                  size="sm"
                  onClick={() => setMode('edit')}
                  className="h-9 gap-1.5 text-xs bg-emerald-600 hover:bg-emerald-500 text-white"
                >
                  <Pencil className="size-3.5" />
                  <span>Редактировать</span>
                </Button>
              )}
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

      {/* View Mode Filters and Saved Views */}
      {mode === 'view' && (
        <div className="space-y-3">
          <div className="flex items-center justify-between flex-wrap gap-2">
            <DashboardSavedViewsMenu
              dashboardId={dashboard.id}
              userId={userId}
              workspaceId={workspaceId}
              currentFilters={filters}
              activeView={activeView}
              savedViews={savedViews}
              onSelectView={applySavedView}
              onViewsUpdated={loadSavedViews}
            />
          </div>

          <DashboardFilterBar
            activeFilters={filters}
            onFilterChange={(f) => setFilters(f, activeView?.id ?? null)}
            userId={userId}
            workspaceId={workspaceId}
          />
        </div>
      )}

      {/* Main Grid: View or Edit Mode */}
      {mode === 'view' ? (
        <DashboardGrid
          key={refreshKey}
          widgets={widgets}
          userId={userId}
          workspaceId={workspaceId}
          filters={filters}
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
