import React from 'react'
import { redirect } from 'next/navigation'
import { BarChart3 } from 'lucide-react'
import { getSession } from '@/src/features/auth/model/session'
import { LoginForm } from '@/src/features/auth/ui/login-form'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'

export const dynamic = 'force-dynamic'

export const metadata = {
  title: 'Вход в систему — AutoBI',
  description: 'Авторизация в аналитической платформе AutoBI',
}

export default async function LoginPage() {
  const session = await getSession()
  if (session) {
    redirect('/')
  }

  return (
    <main className="min-h-screen flex flex-col items-center justify-center p-4 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-900 via-background to-background">
      <div className="w-full max-w-md space-y-6">
        <div className="flex flex-col items-center text-center space-y-2">
          <div className="size-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shadow-lg shadow-emerald-500/5 mb-1">
            <BarChart3 className="size-6 text-emerald-400" />
          </div>
          <div className="flex items-center gap-2">
            <Badge
              variant="outline"
              className="text-emerald-400 border-emerald-500/30 text-[10px] uppercase font-mono tracking-wider"
            >
              AutoBI Analytics
            </Badge>
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
            Вход в систему
          </h1>
          <p className="text-xs sm:text-sm text-muted-foreground max-w-xs">
            Сквозная BI-аналитика и операционные метрики автобизнеса
          </p>
        </div>

        <Card className="border-border/60 bg-card/80 backdrop-blur-xl shadow-2xl shadow-black/40">
          <CardHeader className="pb-4">
            <CardTitle className="text-base font-semibold">Учетные данные</CardTitle>
            <CardDescription className="text-xs">
              Введите ваш корпоративный email и пароль
            </CardDescription>
          </CardHeader>
          <CardContent>
            <LoginForm />
          </CardContent>
        </Card>

        <p className="text-center text-[11px] text-muted-foreground/70">
          Платформа корпоративной бизнес-аналитики AutoBI &copy;{' '}
          {new Date().getFullYear()}
        </p>
      </div>
    </main>
  )
}
