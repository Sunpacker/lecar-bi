import React from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '../../../src/features/auth/model/session'
import { workspaceGateway } from '../../../src/features/workspace/api/workspace-gateway'
import { dashboardGateway } from '../../../src/features/dashboard/api/dashboard-gateway'
import { DashboardListView } from '../../../src/features/dashboard/ui/dashboard-list-view'

export const dynamic = 'force-dynamic'

export default async function DashboardsPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const userId = session.userId
  const currentWorkspace = await workspaceGateway.getCurrentWorkspace().catch(() => null)
  const workspaceId = currentWorkspace?.workspace.id ?? 'ws-1'

  const dashboards = await dashboardGateway.list(userId, workspaceId).catch(() => [])

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <DashboardListView
        initialDashboards={dashboards}
        userId={userId}
        workspaceId={workspaceId}
      />
    </main>
  )
}
