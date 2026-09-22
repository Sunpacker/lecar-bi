'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'
import { Lock, Mail, AlertCircle, Loader2, ArrowRight, UserCheck } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'

export const loginSchema = z.object({
  email: z
    .string()
    .min(1, 'Email обязателен для заполнения')
    .email('Некорректный формат email'),
  password: z.string().min(1, 'Пароль обязателен для заполнения'),
})

export type LoginFormValues = z.infer<typeof loginSchema>

interface LoginFormProps {
  onSuccess?: () => void
}

export function LoginForm({ onSuccess }: LoginFormProps) {
  const router = useRouter()
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: '',
      password: '',
    },
  })

  const onSubmit = async (values: LoginFormValues) => {
    setError(null)
    setLoading(true)

    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: values.email, password: values.password }),
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
    form.setValue('email', demoEmail, { shouldValidate: true })
    form.setValue('password', 'password123', { shouldValidate: true })
    onSubmit({ email: demoEmail, password: 'password123' })
  }

  return (
    <div className="space-y-6">
      <Form {...form}>
        <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          {error && (
            <div
              role="alert"
              className="flex items-start gap-3 p-3 rounded-lg bg-destructive/15 border border-destructive/30 text-destructive text-sm"
            >
              <AlertCircle className="size-4 shrink-0 mt-0.5" />
              <div className="leading-snug">{error}</div>
            </div>
          )}

          <FormField
            control={form.control}
            name="email"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel
                  htmlFor="login-email"
                  className="text-xs font-medium text-foreground"
                >
                  Рабочий Email
                </FormLabel>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                  <FormControl>
                    <Input
                      id="login-email"
                      type="email"
                      placeholder="name@autobi.internal"
                      className="pl-9 h-10 bg-background/50 border-input"
                      disabled={loading}
                      autoComplete="username"
                      {...field}
                    />
                  </FormControl>
                </div>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="password"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel
                  htmlFor="login-password"
                  className="text-xs font-medium text-foreground"
                >
                  Пароль
                </FormLabel>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                  <FormControl>
                    <Input
                      id="login-password"
                      type="password"
                      placeholder="••••••••"
                      className="pl-9 h-10 bg-background/50 border-input"
                      disabled={loading}
                      autoComplete="current-password"
                      {...field}
                    />
                  </FormControl>
                </div>
                <FormMessage />
              </FormItem>
            )}
          />

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
      </Form>

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
