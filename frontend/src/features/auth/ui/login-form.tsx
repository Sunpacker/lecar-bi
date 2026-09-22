'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Lock, Mail, AlertCircle, Loader2, ArrowRight, UserCheck } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface LoginFormProps {
  onSuccess?: () => void
}

export function LoginForm({ onSuccess }: LoginFormProps) {
  const router = useRouter()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (
    e?: React.FormEvent,
    customCredentials?: { email: string; pass: string },
  ) => {
    if (e) {
      e.preventDefault()
    }
    setError(null)
    setLoading(true)

    const targetEmail = customCredentials?.email ?? email
    const targetPassword = customCredentials?.pass ?? password

    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: targetEmail, password: targetPassword }),
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || 'Неверный email или пароль')
      }

      if (onSuccess) {
        onSuccess()
      } else {
        router.push('/')
        router.refresh()
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Произошла ошибка при входе')
    } finally {
      setLoading(false)
    }
  }

  const handleQuickLogin = (demoEmail: string) => {
    setEmail(demoEmail)
    setPassword('password123')
    handleSubmit(undefined, { email: demoEmail, pass: 'password123' })
  }

  return (
    <div className="space-y-6">
      <form onSubmit={(e) => handleSubmit(e)} className="space-y-4">
        {error && (
          <div
            role="alert"
            className="flex items-start gap-3 p-3 rounded-lg bg-destructive/15 border border-destructive/30 text-destructive text-sm"
          >
            <AlertCircle className="size-4 shrink-0 mt-0.5" />
            <div className="leading-snug">{error}</div>
          </div>
        )}

        <div className="space-y-2">
          <Label htmlFor="login-email" className="text-xs font-medium text-foreground">
            Рабочий Email
          </Label>
          <div className="relative">
            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
            <Input
              id="login-email"
              type="email"
              required
              placeholder="name@autobi.internal"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="pl-9 h-10 bg-background/50 border-input"
              disabled={loading}
              autoComplete="username"
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="login-password" className="text-xs font-medium text-foreground">
            Пароль
          </Label>
          <div className="relative">
            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
            <Input
              id="login-password"
              type="password"
              required
              placeholder="••••••••"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="pl-9 h-10 bg-background/50 border-input"
              disabled={loading}
              autoComplete="current-password"
            />
          </div>
        </div>

        <Button
          type="submit"
          className="w-full h-10 text-sm font-semibold tracking-wide bg-primary text-primary-foreground hover:bg-primary/90 transition-all cursor-pointer"
          disabled={loading}
          data-testid="login-submit"
        >
          {loading ? (
            <>
              <Loader2 className="size-4 animate-spin mr-2" />
              Вход в систему...
            </>
          ) : (
            <>
              Войти в AutoBI
              <ArrowRight className="size-4 ml-2" />
            </>
          )}
        </Button>
      </form>

      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-border" />
        </div>
        <div className="relative flex justify-center text-xs uppercase">
          <span className="bg-card px-2 text-muted-foreground font-medium">
            Демонстрационный доступ
          </span>
        </div>
      </div>

      <div className="space-y-2">
        <p className="text-xs text-muted-foreground text-center">
          Выберите учетную запись для мгновенного входа:
        </p>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => handleQuickLogin('elena@autobi.internal')}
            disabled={loading}
            className="flex flex-col items-start h-auto p-2.5 text-left border-border/80 hover:border-emerald-500/50 hover:bg-emerald-950/20 transition-all"
          >
            <div className="flex items-center gap-1.5 w-full">
              <UserCheck className="size-3.5 text-emerald-400 shrink-0" />
              <span className="text-xs font-semibold text-foreground truncate">
                Elena Rostova
              </span>
            </div>
            <span className="text-[10px] text-muted-foreground truncate w-full mt-0.5">
              AutoParts Retail (ws-1)
            </span>
          </Button>

          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => handleQuickLogin('dmitry@autobi.internal')}
            disabled={loading}
            className="flex flex-col items-start h-auto p-2.5 text-left border-border/80 hover:border-blue-500/50 hover:bg-blue-950/20 transition-all"
          >
            <div className="flex items-center gap-1.5 w-full">
              <UserCheck className="size-3.5 text-blue-400 shrink-0" />
              <span className="text-xs font-semibold text-foreground truncate">
                Dmitry Smirnov
              </span>
            </div>
            <span className="text-[10px] text-muted-foreground truncate w-full mt-0.5">
              Lecar Wholesale (ws-2)
            </span>
          </Button>
        </div>
      </div>
    </div>
  )
}
