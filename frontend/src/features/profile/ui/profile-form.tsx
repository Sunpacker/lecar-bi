'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Loader2, CheckCircle2, AlertCircle } from 'lucide-react'
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

interface ProfileFormProps {
  initialUser: {
    id: string
    email: string
    name: string
  }
}

export function ProfileForm({ initialUser }: ProfileFormProps) {
  const router = useRouter()
  const [name, setName] = useState(initialUser.name)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMessage(null)
    setSuccessMessage(null)

    const trimmed = name.trim()
    if (!trimmed) {
      setErrorMessage('Имя не может быть пустым')
      return
    }

    setIsSubmitting(true)
    try {
      const updated = await profileGateway.updateProfile(trimmed)
      setName(updated.name)
      setSuccessMessage('Профиль успешно обновлён')
      router.refresh()
    } catch (err) {
      if (err instanceof Error) {
        setErrorMessage(err.message)
      } else {
        setErrorMessage('Произошла ошибка при обновлении профиля')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">Личные данные</CardTitle>
        <CardDescription>
          Управление вашим именем и просмотр адреса электронной почты.
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

          <div className="space-y-2">
            <Label htmlFor="profile-email">Email</Label>
            <Input
              id="profile-email"
              type="email"
              value={initialUser.email}
              disabled
              readOnly
              className="bg-muted text-muted-foreground cursor-not-allowed"
            />
            <p className="text-xs text-muted-foreground">
              Email аккаунта не подлежит изменению.
            </p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="profile-name">Имя пользователя</Label>
            <Input
              id="profile-name"
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              disabled={isSubmitting}
              required
              minLength={1}
              maxLength={255}
              placeholder="Введите ваше имя"
            />
          </div>

          <div className="pt-2">
            <Button
              type="submit"
              disabled={isSubmitting || name.trim() === initialUser.name}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Сохранение...
                </>
              ) : (
                'Сохранить изменения'
              )}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
