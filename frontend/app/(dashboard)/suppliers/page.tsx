import React, { Suspense } from 'react'
import { redirect } from 'next/navigation'
import {
  workspaceGateway,
  type CurrentWorkspace,
} from '../../../src/features/workspace/api/workspace-gateway'
import { SupplierTabsContainer } from '../../../src/features/supplier-analytics/ui/supplier-tabs-container'
import { getSession } from '../../../src/features/auth/model/session'
import { Skeleton } from '@/components/ui/skeleton'

export const dynamic = 'force-dynamic'

export default async function SuppliersPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const userId = session.userId

  let workspaceContext: CurrentWorkspace | null = null

  try {
    workspaceContext = await workspaceGateway.getCurrentWorkspace()
  } catch {
    // Backend may not have database seeded or may be starting
  }

  const workspaceId = workspaceContext?.workspace.id || 'ws-1'

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <div className="mb-6 space-y-2">
        <span className="text-xs font-semibold text-emerald-400 tracking-wider uppercase">
          AUTOBI / SUPPLIER INTELLIGENCE
        </span>
        <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-foreground">
          Аналитика поставщиков
        </h1>
        <p className="text-sm sm:text-base text-muted-foreground max-w-2xl leading-relaxed">
          Мониторинг дисциплины и надежности поставок: соблюдение сроков (On-Time
          Delivery), полнота выполнения заказов (Fill Rate), уровень дефектности и рейтинг
          надежности контрагентов.
        </p>
      </div>

      <Suspense
        fallback={
          <div className="space-y-4 py-4">
            <Skeleton className="h-10 w-64 rounded-md" />
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
        <SupplierTabsContainer userId={userId} workspaceId={workspaceId} />
      </Suspense>
    </main>
  )
}
