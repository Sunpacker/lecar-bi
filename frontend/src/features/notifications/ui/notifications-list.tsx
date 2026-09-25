'use client'

import React, { useEffect, useState, useCallback } from 'react'
import {
  Bell,
  CheckCheck,
  Check,
  Loader2,
  Flame,
  AlertTriangle,
  Info,
  ChevronLeft,
  ChevronRight,
  Filter,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import {
  notificationsGateway,
  type NotificationItem,
  type NotificationSeverity,
} from '../api/notifications-gateway'

export function NotificationsList() {
  const [items, setItems] = useState<NotificationItem[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [unreadCount, setUnreadCount] = useState(0)
  const [unreadOnly, setUnreadOnly] = useState(false)
  const [selectedSeverity, setSelectedSeverity] = useState<NotificationSeverity | 'all'>(
    'all',
  )

  const [loading, setLoading] = useState(true)
  const [markingAll, setMarkingAll] = useState(false)
  const [markingId, setMarkingId] = useState<string | null>(null)

  const loadNotifications = useCallback(async () => {
    setLoading(true)
    try {
      const data = await notificationsGateway.listNotifications({
        page,
        per_page: 20,
        unread_only: unreadOnly,
        severity: selectedSeverity === 'all' ? undefined : selectedSeverity,
      })
      setItems(data.items)
      setTotal(data.total)
      setUnreadCount(data.unread_count)
    } finally {
      setLoading(false)
    }
  }, [page, unreadOnly, selectedSeverity])

  useEffect(() => {
    let isMounted = true
    void Promise.resolve().then(() => {
      if (isMounted) {
        void loadNotifications()
      }
    })
    return () => {
      isMounted = false
    }
  }, [loadNotifications])

  const handleMarkRead = async (id: string) => {
    setMarkingId(id)
    try {
      await notificationsGateway.markRead(id)
      setItems((prev) =>
        prev.map((item) =>
          item.id === id ? { ...item, read_at: new Date().toISOString() } : item,
        ),
      )
      setUnreadCount((prev) => Math.max(0, prev - 1))
    } finally {
      setMarkingId(null)
    }
  }

  const handleMarkAllRead = async () => {
    if (items.length === 0) return
    setMarkingAll(true)
    try {
      // Bounded snapshot: up to newest visible notification
      const upToId = items[0]?.id
      await notificationsGateway.markAllRead(upToId)
      const now = new Date().toISOString()
      setItems((prev) => prev.map((item) => ({ ...item, read_at: item.read_at || now })))
      setUnreadCount(0)
    } finally {
      setMarkingAll(false)
    }
  }

  const totalPages = Math.ceil(total / 20) || 1

  return (
    <div className="space-y-4">
      {/* Controls Bar */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-lg border border-border bg-card p-4">
        <div className="flex flex-wrap items-center gap-2">
          <div className="inline-flex rounded-md border border-border p-1 bg-muted/30">
            <button
              type="button"
              onClick={() => {
                setUnreadOnly(false)
                setPage(1)
              }}
              className={cn(
                'px-3 py-1 text-xs font-medium rounded-sm transition-colors cursor-pointer',
                !unreadOnly
                  ? 'bg-background text-foreground shadow-2xs'
                  : 'text-muted-foreground hover:text-foreground',
              )}
            >
              Все
            </button>
            <button
              type="button"
              onClick={() => {
                setUnreadOnly(true)
                setPage(1)
              }}
              className={cn(
                'flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-sm transition-colors cursor-pointer',
                unreadOnly
                  ? 'bg-background text-foreground shadow-2xs'
                  : 'text-muted-foreground hover:text-foreground',
              )}
            >
              <span>Непрочитанные</span>
              {unreadCount > 0 && (
                <span className="rounded-full bg-rose-500/20 text-rose-500 px-1.5 py-0.2 text-[10px] font-bold">
                  {unreadCount}
                </span>
              )}
            </button>
          </div>

          <div className="flex items-center gap-1.5 text-xs">
            <Filter className="h-3.5 w-3.5 text-muted-foreground ml-2" />
            <select
              aria-label="Фильтр по уровню важности"
              value={selectedSeverity}
              onChange={(e) => {
                setSelectedSeverity(e.target.value as NotificationSeverity | 'all')
                setPage(1)
              }}
              className="rounded-md border border-input bg-background px-2.5 py-1 text-xs text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
              <option value="all">Все уровни</option>
              <option value="critical">Критические</option>
              <option value="warning">Предупреждения</option>
              <option value="info">Информационные</option>
            </select>
          </div>
        </div>

        <Button
          variant="outline"
          size="sm"
          disabled={markingAll || unreadCount === 0}
          onClick={handleMarkAllRead}
          className="text-xs h-8 gap-1.5 cursor-pointer"
        >
          {markingAll ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
          ) : (
            <CheckCheck className="h-3.5 w-3.5 text-emerald-500" />
          )}
          <span>Прочитать всё</span>
        </Button>
      </div>

      {/* Notifications List */}
      {loading ? (
        <div className="flex flex-col items-center justify-center p-12 text-muted-foreground">
          <Loader2 className="h-6 w-6 animate-spin mb-2" />
          <span className="text-sm">Загрузка уведомлений...</span>
        </div>
      ) : items.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border p-12 text-center">
          <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground mb-3">
            <Bell className="h-6 w-6" />
          </div>
          <h3 className="text-base font-semibold text-foreground">Нет уведомлений</h3>
          <p className="text-xs text-muted-foreground mt-1 max-w-sm">
            {unreadOnly
              ? 'У вас нет непрочитанных уведомлений с выбранными критериями фильтрации.'
              : 'В этом рабочем пространстве пока нет зарегистрированных уведомлений.'}
          </p>
        </div>
      ) : (
        <div className="space-y-2">
          {items.map((item) => {
            const isUnread = !item.read_at
            const isMarking = markingId === item.id

            const formattedDate = new Date(item.occurred_at).toLocaleDateString('ru-RU', {
              day: 'numeric',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
            })

            return (
              <div
                key={item.id}
                className={cn(
                  'flex items-start justify-between gap-4 rounded-lg border p-4 transition-colors',
                  isUnread
                    ? 'border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-500/10'
                    : 'border-border bg-card text-muted-foreground',
                )}
                data-testid={`notification-item-${item.id}`}
              >
                <div className="flex items-start gap-3 min-w-0 flex-1">
                  <div className="mt-0.5 shrink-0">
                    {item.severity === 'critical' ? (
                      <div className="flex h-7 w-7 items-center justify-center rounded-full bg-rose-500/15 text-rose-500">
                        <Flame className="h-4 w-4" />
                      </div>
                    ) : item.severity === 'warning' ? (
                      <div className="flex h-7 w-7 items-center justify-center rounded-full bg-amber-500/15 text-amber-500">
                        <AlertTriangle className="h-4 w-4" />
                      </div>
                    ) : (
                      <div className="flex h-7 w-7 items-center justify-center rounded-full bg-sky-500/15 text-sky-500">
                        <Info className="h-4 w-4" />
                      </div>
                    )}
                  </div>

                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2 mb-1">
                      <h4
                        className={cn(
                          'text-sm leading-tight truncate',
                          isUnread
                            ? 'font-semibold text-foreground'
                            : 'font-medium text-foreground/80',
                        )}
                      >
                        {item.title}
                      </h4>
                      {isUnread && (
                        <Badge
                          variant="outline"
                          className="text-[10px] px-1.5 py-0 border-emerald-500/40 text-emerald-500"
                        >
                          Новое
                        </Badge>
                      )}
                    </div>
                    <p className="text-xs text-muted-foreground leading-relaxed line-clamp-2">
                      {item.body}
                    </p>
                    <span className="text-[11px] text-muted-foreground/70 mt-1.5 inline-block">
                      {formattedDate}
                    </span>
                  </div>
                </div>

                <div className="shrink-0 flex items-center gap-2">
                  {isUnread ? (
                    <Button
                      variant="ghost"
                      size="sm"
                      disabled={isMarking}
                      onClick={() => handleMarkRead(item.id)}
                      className="h-8 px-2.5 text-xs text-muted-foreground hover:text-emerald-500 hover:bg-emerald-500/10 gap-1 cursor-pointer"
                      title="Отметить как прочитанное"
                    >
                      {isMarking ? (
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      ) : (
                        <Check className="h-3.5 w-3.5" />
                      )}
                      <span className="hidden sm:inline">Прочитано</span>
                    </Button>
                  ) : (
                    <span className="text-xs text-muted-foreground/50 px-2 py-1 select-none">
                      Просмотрено
                    </span>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between pt-2">
          <span className="text-xs text-muted-foreground">
            Страница {page} из {totalPages} (всего {total})
          </span>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1 || loading}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="h-8 px-2.5 text-xs gap-1"
            >
              <ChevronLeft className="h-3.5 w-3.5" />
              <span>Назад</span>
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= totalPages || loading}
              onClick={() => setPage((p) => p + 1)}
              className="h-8 px-2.5 text-xs gap-1"
            >
              <span>Вперёд</span>
              <ChevronRight className="h-3.5 w-3.5" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
