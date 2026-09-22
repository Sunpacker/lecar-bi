import React from 'react'
import { notFound, redirect } from 'next/navigation'
import { getSession } from '../../../../src/features/auth/model/session'
import { workspaceGateway } from '../../../../src/features/workspace/api/workspace-gateway'
import { dashboardGateway } from '../../../../src/features/dashboard/api/dashboard-gateway'
import { DashboardViewer } from '../../../../src/features/dashboard/ui/dashboard-viewer'

export const dynamic = 'force-dynamic'

interface DashboardDetailPageProps {
  params: Promise<{ id: string }>
}

export default async function DashboardDetailPage({ params }: DashboardDetailPageProps) {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const { id } = await params
  const userId = session.userId
  const currentWorkspace = await workspaceGateway
    .getCurrentWorkspace(userId)
    .catch(() => null)
  const workspaceId = currentWorkspace?.workspace.id ?? 'ws-1'

  let dashboard
  try {
    dashboard = await dashboardGateway.getById(id, userId, workspaceId)
  } catch {
    notFound()
  }

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <DashboardViewer dashboard={dashboard} userId={userId} workspaceId={workspaceId} />
    </main>
  )
}
