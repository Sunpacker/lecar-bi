'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Loader2, AlertCircle, ShieldAlert } from 'lucide-react'
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
import { profileGateway } from '../api/profile-gateway'

export function SecurityForm() {
  const router = useRouter()
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMessage(null)

    if (!currentPassword) {
      setErrorMessage('Укажите текущий пароль')
      return
    }

    if (newPassword.length < 8) {
      setErrorMessage('Новый пароль должен содержать не менее 8 символов')
      return
    }

    if (newPassword !== confirmPassword) {
      setErrorMessage('Новый пароль и подтверждение не совпадают')
      return
    }

    setIsSubmitting(true)
    try {
      await profileGateway.changePassword(currentPassword, newPassword, confirmPassword)

      // Invalidate frontend session and redirect to login
      await fetch('/api/auth/logout', { method: 'POST' }).catch(() => {})
      router.push('/login?reason=password_changed')
    } catch (err) {
      if (err instanceof Error) {
        setErrorMessage(err.message)
      } else {
        setErrorMessage('Произошла ошибка при смене пароля')
      }
      setIsSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">Смена пароля</CardTitle>
        <CardDescription>Обновите пароль для вашей учётной записи.</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-600 dark:text-amber-400">
            <ShieldAlert className="h-5 w-5 shrink-0 mt-0.5" />
            <div>
              <p className="font-semibold">Внимание: отзыв сессий</p>
              <p className="mt-0.5">
                После успешной смены пароля все активные сессии на всех ваших устройствах
                будут отозваны, и вам потребуется выполнить повторный вход с новым
                паролем.
              </p>
            </div>
          </div>

          {errorMessage && (
            <div
              role="alert"
              className="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
            >
              <AlertCircle className="h-4 w-4 shrink-0" />
              <span>{errorMessage}</span>
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="current-password">Текущий пароль</Label>
            <Input
              id="current-password"
              type="password"
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              disabled={isSubmitting}
              required
              placeholder="••••••••"
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="new-password">Новый пароль</Label>
            <Input
              id="new-password"
              type="password"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              disabled={isSubmitting}
              required
              minLength={8}
              placeholder="Минимум 8 символов"
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="confirm-password">Подтверждение нового пароля</Label>
            <Input
              id="confirm-password"
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              disabled={isSubmitting}
              required
              placeholder="Повторите новый пароль"
            />
          </div>

          <div className="pt-2">
            <Button
              type="submit"
              disabled={
                isSubmitting || !currentPassword || !newPassword || !confirmPassword
              }
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Смена пароля...
                </>
              ) : (
                'Сменить пароль'
              )}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
