import React from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '../../../src/features/auth/model/session'
import { workspaceGateway } from '../../../src/features/workspace/api/workspace-gateway'
import { AlertsView } from '../../../src/features/alerts/ui/alerts-view'

export const dynamic = 'force-dynamic'

export default async function AlertsPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const userId = session.userId
  const currentWorkspace = await workspaceGateway.getCurrentWorkspace().catch(() => null)
  const workspaceId = currentWorkspace?.workspace.id ?? 'ws-1'

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <div className="mb-6 space-y-2">
        <span className="text-xs font-semibold text-rose-500 tracking-wider uppercase">
          AUTOBI / INCIDENT MANAGEMENT & ALERTING
        </span>
        <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-foreground">
          Алерты и дефицит
        </h1>
        <p className="text-sm sm:text-base text-muted-foreground max-w-2xl leading-relaxed">
          Мониторинг складских рисков: выявление товаров в аут-оф-сток, критического
          дефицита по дням продаж (DOS), залежалого запаса и управление инцидентами.
        </p>
      </div>

      <AlertsView userId={userId} workspaceId={workspaceId} />
    </main>
  )
}
