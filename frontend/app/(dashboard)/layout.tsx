import React from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '../../src/features/auth/model/session'
import { loadHealthStatus } from '../../src/features/system-health/model/load-health-status'
import {
  workspaceGateway,
  type CurrentWorkspace,
  type Workspace,
} from '../../src/features/workspace/api/workspace-gateway'
import { Sidebar } from '../../src/shared/ui/layout/sidebar'
import { Header } from '../../src/shared/ui/layout/header'
import { Footer } from '../../src/shared/ui/layout/footer'
import { WorkspaceAccessProvider } from '../../src/features/workspace/ui/workspace-access-provider'

export const dynamic = 'force-dynamic'

export default async function DashboardLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const userId = session.userId
  const healthStatus = await loadHealthStatus()

  let workspaceContext: CurrentWorkspace | null = null
  let accessibleWorkspaces: Workspace[] = []

  try {
    workspaceContext = await workspaceGateway.getCurrentWorkspace()
    accessibleWorkspaces = await workspaceGateway.listWorkspaces()
  } catch {
    // Backend may not have database seeded or may be starting
  }

  return (
    <WorkspaceAccessProvider capabilities={workspaceContext?.workspace.capabilities}>
      <div className="flex h-screen overflow-hidden bg-background">
        <Sidebar />
        <div className="flex flex-1 flex-col overflow-hidden">
          <Header
            workspaceContext={workspaceContext}
            accessibleWorkspaces={accessibleWorkspaces}
          />
          <div className="flex flex-1 flex-col overflow-y-auto">
            {children}
            <Footer status={healthStatus} />
          </div>
        </div>
      </div>
    </WorkspaceAccessProvider>
  )
}
