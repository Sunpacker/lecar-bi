'use client'

import React, { useEffect, useState } from 'react'
import {
  Loader2,
  CheckCircle2,
  AlertCircle,
  Info,
  AlertTriangle,
  Flame,
} from 'lucide-react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
  notificationsGateway,
  type NotificationPreferences,
} from '../api/notifications-gateway'

export function NotificationPreferencesForm() {
  const [preferences, setPreferences] = useState<NotificationPreferences>({
    info: true,
    warning: true,
    critical: true,
  })
  const [loading, setLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    let isMounted = true

    notificationsGateway
      .getPreferences()
      .then((data) => {
        if (isMounted) {
          setPreferences(data)
          setLoading(false)
        }
      })
      .catch(() => {
        if (isMounted) {
          setLoading(false)
        }
      })

    return () => {
      isMounted = false
    }
  }, [])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsSubmitting(true)

    try {
      const updated = await notificationsGateway.updatePreferences(preferences)
      setPreferences(updated)
      setSuccessMessage('Настройки уведомлений успешно сохранены')
    } catch (err) {
      if (err instanceof Error) {
        setErrorMessage(err.message)
      } else {
        setErrorMessage('Не удалось сохранить настройки уведомлений')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin mr-2" />
        <span>Загрузка параметров уведомлений...</span>
      </div>
    )
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">Уровни важности уведомлений</CardTitle>
        <CardDescription>
          Выберите, какие уведомления должны отображаться в центре уведомлений и
          учитываться в счётчике для текущего рабочего пространства.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-4">
          {successMessage && (
            <div
              role="status"
              className="flex items-center gap-2 rounded-lg border border-emerald-500/50 bg-emerald-500/10 p-3 text-sm text-emerald-600 dark:text-emerald-400"
            >
              <CheckCircle2 className="h-4 w-4 shrink-0" />
              <span>{successMessage}</span>
            </div>
          )}

          {errorMessage && (
            <div
              role="alert"
              className="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
            >
              <AlertCircle className="h-4 w-4 shrink-0" />
              <span>{errorMessage}</span>
            </div>
          )}

          <div className="space-y-3">
            <label className="flex items-start gap-3 rounded-lg border border-border p-4 cursor-pointer hover:bg-muted/30 transition-colors">
              <input
                type="checkbox"
                checked={preferences.critical}
                onChange={(e) =>
                  setPreferences((prev) => ({ ...prev, critical: e.target.checked }))
                }
                disabled={isSubmitting}
                className="mt-1 h-4 w-4 rounded border-border text-emerald-600 focus:ring-emerald-500"
              />
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <Flame className="h-4 w-4 text-rose-500" />
                  <span className="font-medium text-sm text-foreground">
                    Критические события
                  </span>
                </div>
                <p className="text-xs text-muted-foreground mt-0.5">
                  Срочные алерты, блокирующие системные сбои и критические падения метрик.
                </p>
              </div>
            </label>

            <label className="flex items-start gap-3 rounded-lg border border-border p-4 cursor-pointer hover:bg-muted/30 transition-colors">
              <input
                type="checkbox"
                checked={preferences.warning}
                onChange={(e) =>
                  setPreferences((prev) => ({ ...prev, warning: e.target.checked }))
                }
                disabled={isSubmitting}
                className="mt-1 h-4 w-4 rounded border-border text-emerald-600 focus:ring-emerald-500"
              />
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <AlertTriangle className="h-4 w-4 text-amber-500" />
                  <span className="font-medium text-sm text-foreground">
                    Предупреждения
                  </span>
                </div>
                <p className="text-xs text-muted-foreground mt-0.5">
                  Низкий остаток товара, отклонения продаж от нормы и предупреждения по
                  поставкам.
                </p>
              </div>
            </label>

            <label className="flex items-start gap-3 rounded-lg border border-border p-4 cursor-pointer hover:bg-muted/30 transition-colors">
              <input
                type="checkbox"
                checked={preferences.info}
                onChange={(e) =>
                  setPreferences((prev) => ({ ...prev, info: e.target.checked }))
                }
                disabled={isSubmitting}
                className="mt-1 h-4 w-4 rounded border-border text-emerald-600 focus:ring-emerald-500"
              />
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <Info className="h-4 w-4 text-sky-500" />
                  <span className="font-medium text-sm text-foreground">
                    Информационные события
                  </span>
                </div>
                <p className="text-xs text-muted-foreground mt-0.5">
                  Успешные импорты данных, регулярные отчёты и плановые обновления.
                </p>
              </div>
            </label>
          </div>

          <div className="pt-2">
            <Button
              type="submit"
              disabled={isSubmitting}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Сохранение...
                </>
              ) : (
                'Сохранить настройки'
              )}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
