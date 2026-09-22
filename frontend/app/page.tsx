import React, { Suspense } from 'react'
import { redirect } from 'next/navigation'
import { loadHealthStatus } from '../src/features/system-health/model/load-health-status'
import { HealthStatus } from '../src/features/system-health/ui/health-status'
import { WorkspaceContextBar } from '../src/features/workspace/ui/workspace-context-bar'
import {
  workspaceGateway,
  type CurrentWorkspace,
  type Workspace,
} from '../src/features/workspace/api/workspace-gateway'
import { SalesDashboard } from '../src/features/sales-analytics/ui/sales-dashboard'
import { getSession } from '../src/features/auth/model/session'
import { Skeleton } from '@/components/ui/skeleton'

export const dynamic = 'force-dynamic'

export default async function HomePage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const userId = session.userId
  const healthStatus = await loadHealthStatus()

  let workspaceContext: CurrentWorkspace | null = null
  let accessibleWorkspaces: Workspace[] = []

  try {
    workspaceContext = await workspaceGateway.getCurrentWorkspace(userId)
    accessibleWorkspaces = await workspaceGateway.listWorkspaces(userId)
  } catch {
    // Backend may not have database seeded or may be starting
  }

  return (
    <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-16">
      {workspaceContext && (
        <WorkspaceContextBar
          context={workspaceContext}
          accessibleWorkspaces={accessibleWorkspaces}
        />
      )}
      <div className="my-8 space-y-2">
        <span className="text-xs font-semibold text-emerald-400 tracking-wider uppercase">
          AUTOBI / SALES ANALYTICS
        </span>
        <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-foreground">
          Analytics workspace
        </h1>
        <p className="text-sm sm:text-base text-muted-foreground max-w-2xl leading-relaxed">
          Сквозная BI-аналитика продаж: выручка, динамика заказов, средний чек и
          регионально-категорийные срезы на реальных данных PostgreSQL.
        </p>
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

      <footer className="mt-12 pt-6 border-t border-border flex justify-between items-center">
        <HealthStatus status={healthStatus} />
      </footer>
    </main>
  )
}
