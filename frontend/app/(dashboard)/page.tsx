import React, { Suspense } from 'react'
import { redirect } from 'next/navigation'
import Image from 'next/image'
import { MapPin, Store } from 'lucide-react'
import {
  workspaceGateway,
  type CurrentWorkspace,
  type Workspace,
} from '../../src/features/workspace/api/workspace-gateway'
import { SalesDashboard } from '../../src/features/sales-analytics/ui/sales-dashboard'
import { getSession } from '../../src/features/auth/model/session'
import { Skeleton } from '@/components/ui/skeleton'

export const dynamic = 'force-dynamic'

export default async function HomePage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const userId = session.userId

  let workspaceContext: CurrentWorkspace | null = null
  let accessibleWorkspaces: Workspace[] = []

  try {
    workspaceContext = await workspaceGateway.getCurrentWorkspace(userId)
    accessibleWorkspaces = await workspaceGateway.listWorkspaces(userId)
  } catch {
    // Backend may not have database seeded or may be starting
  }

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <div className="relative mb-8 overflow-hidden rounded-2xl border border-border bg-card/50 p-6 sm:p-8 backdrop-blur-xs shadow-xs">
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-8 items-center">
          <div className="space-y-3 lg:col-span-7">
            <div className="inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-400">
              <span className="size-1.5 rounded-full bg-emerald-400 animate-pulse" />
              Розничная сеть LECAR Store &amp; LADA Деталь
            </div>
            <h1 className="text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-foreground">
              Аналитическое пространство продаж
            </h1>
            <p className="text-sm sm:text-base text-muted-foreground leading-relaxed max-w-xl">
              Сквозная BI-аналитика розничной сети: выручка, динамика заказов, средний чек и
              регионально-категорийные срезы на основе реальных данных торговых точек LECAR.
            </p>
            <div className="flex flex-wrap items-center gap-2.5 pt-1 text-xs text-muted-foreground">
              <div className="inline-flex items-center gap-1.5 rounded-md bg-muted/40 border border-border/60 px-2.5 py-1">
                <MapPin className="size-3.5 text-rose-500" />
                <span className="text-foreground font-medium">Флагманский хаб</span>
                <span className="text-muted-foreground/60">•</span>
                <span>Тольятти</span>
              </div>
              <div className="inline-flex items-center gap-1.5 rounded-md bg-muted/40 border border-border/60 px-2.5 py-1">
                <Store className="size-3.5 text-emerald-400" />
                <span>Торговая сеть онлайн</span>
              </div>
            </div>
          </div>

          <div className="lg:col-span-5">
            <div className="group relative overflow-hidden rounded-xl border border-border/80 bg-muted/20 shadow-md">
              <div className="relative aspect-[2.85/1] w-full">
                <Image
                  src="/lecar_store_37538391e3.jpeg"
                  alt="Флагманский розничный магазин LECAR Store и LADA Деталь"
                  fill
                  priority
                  className="object-cover object-center transition-transform duration-700 ease-out group-hover:scale-[1.03]"
                  sizes="(max-width: 1024px) 100vw, 45vw"
                />
                <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-background/80 via-transparent to-transparent opacity-60 transition-opacity duration-300 group-hover:opacity-40" />
              </div>
              <div className="absolute bottom-2 left-2 right-2 flex items-center justify-between px-2.5 py-1 rounded-md bg-background/80 backdrop-blur-md border border-border/60 text-[11px] text-muted-foreground pointer-events-none">
                <span className="font-medium text-foreground">LECAR Store #01</span>
                <span>Флагманский фасад</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <Suspense
        fallback={
          <div className="space-y-4 py-4">
            <Skeleton className="h-6 w-48 rounded-md" />
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <Skeleton className="h-28 rounded-xl" />
              <Skeleton className="h-28 rounded-xl" />
              <Skeleton className="h-28 rounded-xl" />
              <Skeleton className="h-28 rounded-xl" />
            </div>
            <Skeleton className="h-56 rounded-xl w-full" />
          </div>
        }
      >
        {workspaceContext ? (
          <SalesDashboard userId={userId} workspaceId={workspaceContext.workspace.id} />
        ) : (
          <SalesDashboard userId={userId} workspaceId="ws-1" />
        )}
      </Suspense>
    </main>
  )
}
