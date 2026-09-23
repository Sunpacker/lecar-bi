'use client'

import React, { useState } from 'react'
import Link from 'next/link'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet'
import { Plus, LayoutDashboard, Calendar, Trash2 } from 'lucide-react'
import { dashboardGateway, type DashboardSummary } from '../api/dashboard-gateway'
import { useWorkspaceAccess } from '../../workspace/ui/workspace-access-provider'

interface DashboardListViewProps {
  initialDashboards: DashboardSummary[]
  userId: string
  workspaceId: string
}

export function DashboardListView({
  initialDashboards,
  userId,
  workspaceId,
}: DashboardListViewProps) {
  const { hasCapability } = useWorkspaceAccess()
  const canManageDashboards = hasCapability('dashboards.manage')

  const [dashboards, setDashboards] = useState<DashboardSummary[]>(initialDashboards)
  const [isOpen, setIsOpen] = useState(false)
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!title.trim()) return

    setIsSubmitting(true)
    setError(null)

    try {
      const created = await dashboardGateway.create(
        userId,
        { title: title.trim(), description: description.trim() || null },
        workspaceId,
      )

      const newSummary: DashboardSummary = {
        id: created.id,
        workspace_id: created.workspace_id,
        title: created.title,
        description: created.description,
        widget_count: created.widgets.length,
        created_at: created.created_at,
        updated_at: created.updated_at,
      }

      setDashboards((prev) => [newSummary, ...prev])
      setTitle('')
      setDescription('')
      setIsOpen(false)
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Не удалось создать дашборд')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleDelete = async (id: string, e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()

    if (!window.confirm('Вы уверены, что хотите удалить этот дашборд?')) {
      return
    }

    try {
      await dashboardGateway.delete(id, userId, workspaceId)
      setDashboards((prev) => prev.filter((d) => d.id !== id))
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Не удалось удалить дашборд')
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-foreground">
            Пользовательские дашборды
          </h2>
          <p className="text-sm text-muted-foreground mt-0.5">
            Управление аналитическими дашбордами и конфигурациями виджетов
          </p>
        </div>

        {canManageDashboards && (
          <Sheet open={isOpen} onOpenChange={setIsOpen}>
            <SheetTrigger className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 text-sm font-medium transition-colors cursor-pointer">
              <Plus className="size-4" />
              <span>Создать дашборд</span>
            </SheetTrigger>
            <SheetContent className="p-6">
              <SheetHeader>
                <SheetTitle>Новый дашборд</SheetTitle>
                <SheetDescription>
                  Задайте название и назначение дашборда для вашего рабочего пространства.
                </SheetDescription>
              </SheetHeader>

              <form onSubmit={handleCreate} className="mt-6 space-y-4">
                {error && (
                  <div className="p-3 text-xs rounded-md bg-rose-500/10 border border-rose-500/20 text-rose-400">
                    {error}
                  </div>
                )}
                <div className="space-y-2">
                  <Label htmlFor="dashboard-title">Название</Label>
                  <Input
                    id="dashboard-title"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="Например: Обзор продаж Тольятти"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="dashboard-desc">Описание (необязательно)</Label>
                  <Input
                    id="dashboard-desc"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    placeholder="Краткое описание метрик и назначения"
                  />
                </div>

                <div className="pt-4 flex justify-end gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setIsOpen(false)}
                    disabled={isSubmitting}
                  >
                    Отмена
                  </Button>
                  <Button
                    type="submit"
                    disabled={isSubmitting || !title.trim()}
                    className="bg-emerald-600 hover:bg-emerald-500 text-white"
                  >
                    {isSubmitting ? 'Сохранение...' : 'Сохранить'}
                  </Button>
                </div>
              </form>
            </SheetContent>
          </Sheet>
        )}
      </div>

      {dashboards.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/80 p-12 text-center bg-card/20">
          <div className="flex size-12 items-center justify-center rounded-full bg-muted/60 text-muted-foreground mb-4">
            <LayoutDashboard className="size-6" />
          </div>
          <h3 className="text-base font-semibold text-foreground">
            Нет доступных дашбордов
          </h3>
          <p className="mt-1 text-sm text-muted-foreground max-w-sm">
            {canManageDashboards
              ? 'Создайте свой первый дашборд для компоновки нужных метрик и аналитических срезов.'
              : 'В этом рабочем пространстве пока нет созданных дашбордов.'}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {dashboards.map((dash) => (
            <Link
              key={dash.id}
              href={`/dashboards/${dash.id}`}
              className="group relative block focus:outline-hidden"
            >
              <Card className="h-full transition-all duration-200 border-border bg-card/50 hover:bg-card hover:border-emerald-500/40 shadow-xs hover:shadow-md flex flex-col justify-between">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between gap-2">
                    <CardTitle className="text-base font-bold text-foreground group-hover:text-emerald-400 transition-colors">
                      {dash.title}
                    </CardTitle>
                    {canManageDashboards && (
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Удалить дашборд"
                        onClick={(e) => handleDelete(dash.id, e)}
                        className="size-8 opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-rose-400 transition-opacity"
                      >
                        <Trash2 className="size-4" />
                      </Button>
                    )}
                  </div>
                  {dash.description && (
                    <CardDescription className="text-xs line-clamp-2 mt-1">
                      {dash.description}
                    </CardDescription>
                  )}
                </CardHeader>
                <CardContent className="pt-0">
                  <div className="flex items-center justify-between text-xs text-muted-foreground pt-4 border-t border-border/50">
                    <span className="inline-flex items-center gap-1.5 font-medium text-foreground">
                      <LayoutDashboard className="size-3.5 text-emerald-500" />
                      {dash.widget_count}{' '}
                      {dash.widget_count === 1 ? 'виджет' : 'виджетов'}
                    </span>
                    <span className="inline-flex items-center gap-1 text-[11px]">
                      <Calendar className="size-3" />
                      {new Date(dash.created_at).toLocaleDateString('ru-RU')}
                    </span>
                  </div>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
