'use client'

import React, { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  BookmarkIcon,
  ChevronDownIcon,
  PlusIcon,
  CheckIcon,
  Trash2Icon,
  StarIcon,
  XIcon,
} from 'lucide-react'
import {
  dashboardGateway,
  type DashboardFilterValues,
  type DashboardSavedView,
} from '../api/dashboard-gateway'

interface DashboardSavedViewsMenuProps {
  dashboardId: string
  userId: string
  workspaceId: string
  currentFilters: DashboardFilterValues
  activeView: DashboardSavedView | null
  savedViews: DashboardSavedView[]
  onSelectView: (view: DashboardSavedView) => void
  onViewsUpdated: () => void
}

export function DashboardSavedViewsMenu({
  dashboardId,
  userId,
  workspaceId,
  currentFilters,
  activeView,
  savedViews,
  onSelectView,
  onViewsUpdated,
}: DashboardSavedViewsMenuProps) {
  const [isOpen, setIsOpen] = useState(false)
  const [isCreating, setIsCreating] = useState(false)
  const [newViewName, setNewViewName] = useState('')
  const [isDefault, setIsDefault] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!newViewName.trim()) return

    setIsSubmitting(true)
    setError(null)
    try {
      const created = await dashboardGateway.createSavedView(
        dashboardId,
        userId,
        {
          name: newViewName.trim(),
          filters: currentFilters,
          is_default: isDefault,
        },
        workspaceId,
      )
      setIsCreating(false)
      setNewViewName('')
      setIsDefault(false)
      setIsOpen(false)
      onSelectView(created)
      onViewsUpdated()
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Не удалось сохранить представление')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleDelete = async (viewId: string, e: React.MouseEvent) => {
    e.stopPropagation()
    if (!confirm('Удалить это сохранённое представление?')) return

    try {
      await dashboardGateway.deleteSavedView(dashboardId, viewId, userId, workspaceId)
      onViewsUpdated()
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Ошибка при удалении')
    }
  }

  const handleSetDefault = async (view: DashboardSavedView, e: React.MouseEvent) => {
    e.stopPropagation()
    try {
      await dashboardGateway.updateSavedView(
        dashboardId,
        view.id,
        userId,
        {
          name: view.name,
          filters: view.filters,
          is_default: !view.is_default,
        },
        workspaceId,
      )
      onViewsUpdated()
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Ошибка при обновлении')
    }
  }

  return (
    <div className="relative inline-block text-left" data-testid="saved-views-menu">
      <div className="flex items-center gap-1.5">
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setIsOpen(!isOpen)}
          className="h-8 gap-1.5 text-xs text-foreground bg-background/80 hover:bg-muted/30 border-border"
        >
          <BookmarkIcon className="size-3.5 text-emerald-500" />
          <span className="font-medium max-w-[150px] truncate">
            {activeView ? activeView.name : 'Пользовательский вид'}
          </span>
          {activeView?.is_default && (
            <Badge
              variant="secondary"
              className="text-[10px] h-4 px-1 bg-emerald-500/10 text-emerald-500"
            >
              По умолчанию
            </Badge>
          )}
          <ChevronDownIcon className="size-3 text-muted-foreground ml-1" />
        </Button>

        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => {
            setIsCreating(true)
            setIsOpen(true)
          }}
          className="h-8 px-2 text-xs text-muted-foreground hover:text-foreground gap-1"
          title="Сохранить текущие фильтры"
        >
          <PlusIcon className="size-3.5" />
          <span className="hidden sm:inline">Сохранить представление</span>
        </Button>
      </div>

      {isOpen && (
        <div className="absolute left-0 mt-1.5 w-72 rounded-xl border border-border bg-popover p-2 shadow-lg z-50 animate-in fade-in-0 zoom-in-95">
          {isCreating ? (
            <form onSubmit={handleCreate} className="space-y-2 p-1">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-foreground">
                  Новое представление
                </span>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => setIsCreating(false)}
                  className="h-6 w-6 p-0 text-muted-foreground"
                >
                  <XIcon className="size-3.5" />
                </Button>
              </div>

              {error && <p className="text-[11px] text-rose-500">{error}</p>}

              <Input
                placeholder="Название представления"
                value={newViewName}
                onChange={(e) => setNewViewName(e.target.value)}
                className="h-8 text-xs"
                autoFocus
              />

              <label className="flex items-center gap-2 text-xs text-muted-foreground cursor-pointer">
                <input
                  type="checkbox"
                  checked={isDefault}
                  onChange={(e) => setIsDefault(e.target.checked)}
                  className="rounded border-border text-emerald-600 focus:ring-emerald-500 size-3.5"
                />
                Сделать представлением по умолчанию
              </label>

              <div className="flex justify-end gap-1.5 pt-1">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => setIsCreating(false)}
                  className="h-7 text-xs"
                >
                  Отмена
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={!newViewName.trim() || isSubmitting}
                  className="h-7 text-xs bg-emerald-600 hover:bg-emerald-500 text-white"
                >
                  {isSubmitting ? 'Сохранение...' : 'Подтвердить сохранение'}
                </Button>
              </div>
            </form>
          ) : (
            <div className="space-y-1">
              <div className="px-2 py-1 text-[11px] font-medium text-muted-foreground border-b border-border/40 flex items-center justify-between">
                <span>Сохранённые представления ({savedViews.length})</span>
                <button
                  type="button"
                  onClick={() => setIsCreating(true)}
                  className="text-emerald-500 hover:underline flex items-center gap-0.5 text-[11px]"
                >
                  <PlusIcon className="size-3" /> Добавить
                </button>
              </div>

              {savedViews.length === 0 ? (
                <div className="p-3 text-center text-xs text-muted-foreground">
                  Нет сохранённых представлений
                </div>
              ) : (
                <div className="max-h-60 overflow-y-auto space-y-0.5">
                  {savedViews.map((view) => {
                    const isSelected = activeView?.id === view.id
                    return (
                      <div
                        key={view.id}
                        onClick={() => {
                          onSelectView(view)
                          setIsOpen(false)
                        }}
                        className={`flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs cursor-pointer transition-colors ${
                          isSelected
                            ? 'bg-emerald-500/10 text-emerald-400 font-medium'
                            : 'hover:bg-muted text-foreground'
                        }`}
                      >
                        <div className="flex items-center gap-2 truncate flex-1">
                          {isSelected && (
                            <CheckIcon className="size-3 text-emerald-500 shrink-0" />
                          )}
                          <span className="truncate">{view.name}</span>
                          {view.is_default && (
                            <Badge
                              variant="outline"
                              className="text-[9px] px-1 py-0 h-3.5 border-emerald-500/30 text-emerald-500"
                            >
                              дефолт
                            </Badge>
                          )}
                        </div>

                        <div className="flex items-center gap-1 shrink-0 ml-2">
                          <button
                            type="button"
                            title={view.is_default ? 'Снять дефолт' : 'Сделать дефолтным'}
                            onClick={(e) => handleSetDefault(view, e)}
                            className="p-1 text-muted-foreground hover:text-amber-400"
                          >
                            <StarIcon
                              className={`size-3 ${view.is_default ? 'fill-amber-400 text-amber-400' : ''}`}
                            />
                          </button>
                          <button
                            type="button"
                            title="Удалить представление"
                            onClick={(e) => handleDelete(view.id, e)}
                            className="p-1 text-muted-foreground hover:text-rose-400"
                          >
                            <Trash2Icon className="size-3" />
                          </button>
                        </div>
                      </div>
                    )
                  })}
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
