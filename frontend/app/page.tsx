import { loadHealthStatus } from '../src/features/system-health/model/load-health-status'
import { HealthStatus } from '../src/features/system-health/ui/health-status'
import { WorkspaceContextBar } from '../src/features/workspace/ui/workspace-context-bar'
import {
  workspaceGateway,
  type CurrentWorkspace,
  type Workspace,
} from '../src/features/workspace/api/workspace-gateway'

export const dynamic = 'force-dynamic'

export default async function HomePage() {
  const healthStatus = await loadHealthStatus()

  // Default demo user identity for Phase 2 integration
  const demoUserId = 'user-1'
  let workspaceContext: CurrentWorkspace | null = null
  let accessibleWorkspaces: Workspace[] = []

  try {
    workspaceContext = await workspaceGateway.getCurrentWorkspace(demoUserId)
    accessibleWorkspaces = await workspaceGateway.listWorkspaces(demoUserId)
  } catch {
    // Backend may not have database seeded or may be starting
  }

  return (
    <main className="shell">
      {workspaceContext && (
        <WorkspaceContextBar
          context={workspaceContext}
          accessibleWorkspaces={accessibleWorkspaces}
        />
      )}
      <span className="eyebrow">AUTOBI / IDENTITY & WORKSPACE</span>
      <h1>Analytics workspace</h1>
      <p>
        Пользователь видит только разрешённый workspace, бэкенд обеспечивает
        авторизационную границу владения данными.
      </p>
      <HealthStatus status={healthStatus} />
    </main>
  )
}
