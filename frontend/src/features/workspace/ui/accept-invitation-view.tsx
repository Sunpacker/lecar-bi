'use client'

import React, { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { Loader2, CheckCircle2, AlertCircle, Users, ArrowRight } from 'lucide-react'
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
import { workspaceGateway, type InvitationPublic } from '../api/workspace-gateway'
import { ROLE_LABELS } from './workspace-member-list'

interface AcceptInvitationViewProps {
  token: string
}

export function AcceptInvitationView({ token }: AcceptInvitationViewProps) {
  const router = useRouter()
  const [details, setDetails] = useState<InvitationPublic | null>(null)
  const [loading, setLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  // Registration fields for new user
  const [name, setName] = useState('')
  const [password, setPassword] = useState('')

  useEffect(() => {
    let isMounted = true

    workspaceGateway
      .getInvitationDetails(token)
      .then((data) => {
        if (isMounted) {
          setDetails(data)
          setLoading(false)
        }
      })
      .catch((err: unknown) => {
        if (isMounted) {
          if (err instanceof Error) {
            setErrorMessage(err.message)
          } else {
            setErrorMessage('Приглашение не найдено или срок его действия истёк.')
          }
          setLoading(false)
        }
      })

    return () => {
      isMounted = false
    }
  }, [token])

  const handleAccept = async (e?: React.FormEvent) => {
    if (e) e.preventDefault()
    setErrorMessage(null)

    if (details && !details.is_existing_user) {
      if (!name.trim()) {
        setErrorMessage('Укажите ваше имя')
        return
      }
      if (password.length < 8) {
        setErrorMessage('Пароль должен содержать минимум 8 символов')
        return
      }
    }

    setIsSubmitting(true)
    try {
      const payload = details?.is_existing_user
        ? undefined
        : { name: name.trim(), password }

      const result = await workspaceGateway.acceptInvitation(token, payload)

      // Set cookie session and workspace on frontend
      await fetch('/api/auth/session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user: result.user,
          token: result.token,
          workspaceId: result.workspace_id,
        }),
      })

      setSuccess(true)
      setTimeout(() => {
        router.push('/')
        router.refresh()
      }, 1000)
    } catch (err) {
      if (err instanceof Error) {
        setErrorMessage(err.message)
      } else {
        setErrorMessage('Не удалось принять приглашение')
      }
      setIsSubmitting(false)
    }
  }

  if (loading) {
    return (
      <div className="flex min-h-[60vh] flex-col items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-emerald-500 mb-4" />
        <p className="text-sm text-muted-foreground">
          Загрузка информации о приглашении...
        </p>
      </div>
    )
  }

  if (errorMessage && !details) {
    return (
      <Card className="max-w-md mx-auto my-12">
        <CardHeader className="text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10 text-destructive mb-2">
            <AlertCircle className="h-6 w-6" />
          </div>
          <CardTitle className="text-xl">Приглашение недоступно</CardTitle>
          <CardDescription>{errorMessage}</CardDescription>
        </CardHeader>
        <CardContent className="flex justify-center pt-2">
          <Link href="/login">
            <Button variant="outline">Перейти к авторизации</Button>
          </Link>
        </CardContent>
      </Card>
    )
  }

  if (success) {
    return (
      <Card className="max-w-md mx-auto my-12">
        <CardHeader className="text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500 mb-2">
            <CheckCircle2 className="h-6 w-6" />
          </div>
          <CardTitle className="text-xl">Добро пожаловать!</CardTitle>
          <CardDescription>
            Вы успешно присоединились к пространству «{details?.workspace_name}».
            Перенаправление...
          </CardDescription>
        </CardHeader>
      </Card>
    )
  }

  return (
    <Card className="max-w-md mx-auto my-12 shadow-lg">
      <CardHeader className="text-center">
        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500 mb-2">
          <Users className="h-6 w-6" />
        </div>
        <CardTitle className="text-2xl font-bold">Приглашение в команду</CardTitle>
        <CardDescription className="text-sm">
          Вас пригласили в рабочее пространство{' '}
          <strong className="text-foreground">{details?.workspace_name}</strong> с ролью{' '}
          <span className="font-semibold text-emerald-500">
            {details?.role ? ROLE_LABELS[details.role] : ''}
          </span>
          .
        </CardDescription>
      </CardHeader>
      <CardContent>
        {errorMessage && (
          <div
            role="alert"
            className="mb-4 flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
          >
            <AlertCircle className="h-4 w-4 shrink-0" />
            <span>{errorMessage}</span>
          </div>
        )}

        {details?.is_existing_user ? (
          <div className="space-y-4">
            <div className="rounded-lg border border-border bg-muted/40 p-4 text-sm text-muted-foreground">
              Вы уже зарегистрированы в AutoBI с адресом{' '}
              <strong className="text-foreground">{details.email}</strong>.
            </div>

            <Button
              type="button"
              onClick={() => handleAccept()}
              disabled={isSubmitting}
              className="w-full bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Принятие...
                </>
              ) : (
                <>
                  Принять приглашение
                  <ArrowRight className="ml-2 h-4 w-4" />
                </>
              )}
            </Button>
          </div>
        ) : (
          <form onSubmit={handleAccept} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="invite-email">Email</Label>
              <Input
                id="invite-email"
                type="email"
                value={details?.email || ''}
                disabled
                readOnly
                className="bg-muted text-muted-foreground cursor-not-allowed"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="invite-name">Ваше имя</Label>
              <Input
                id="invite-name"
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                disabled={isSubmitting}
                required
                placeholder="Иван Иванов"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="invite-password">Придумайте пароль</Label>
              <Input
                id="invite-password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={isSubmitting}
                required
                minLength={8}
                placeholder="Минимум 8 символов"
              />
            </div>

            <Button
              type="submit"
              disabled={isSubmitting || !name.trim() || password.length < 8}
              className="w-full bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Регистрация...
                </>
              ) : (
                'Зарегистрироваться и принять'
              )}
            </Button>
          </form>
        )}
      </CardContent>
    </Card>
  )
}
