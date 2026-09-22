import React, { Suspense } from 'react'
import { loadHealthStatus } from '../src/features/system-health/model/load-health-status'
import { HealthStatus } from '../src/features/system-health/ui/health-status'
import { WorkspaceContextBar } from '../src/features/workspace/ui/workspace-context-bar'
import {
  workspaceGateway,
  type CurrentWorkspace,
  type Workspace,
} from '../src/features/workspace/api/workspace-gateway'
import { SalesDashboard } from '../src/features/sales-analytics/ui/sales-dashboard'

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
      <div className="workspace-hero">
        <span className="eyebrow">AUTOBI / SALES ANALYTICS</span>
        <h1>Analytics workspace</h1>
        <p>
          Сквозная BI-аналитика продаж: выручка, динамика заказов, средний чек и
          регионально-категорийные срезы на реальных данных PostgreSQL.
        </p>
      </div>

      <Suspense
        fallback={
          <div className="dashboard-loading-skeleton">
            <div className="skeleton-line" style={{ width: '40%' }} />
            <div className="skeleton-grid">
              <div className="skeleton-card" />
              <div className="skeleton-card" />
              <div className="skeleton-card" />
              <div className="skeleton-card" />
            </div>
            <div className="skeleton-card skeleton-card--large" />
          </div>
        }
      >
        {workspaceContext ? (
          <SalesDashboard
            userId={demoUserId}
            workspaceId={workspaceContext.workspace.id}
          />
        ) : (
          <SalesDashboard userId={demoUserId} workspaceId="ws-1" />
        )}
      </Suspense>

      <footer className="page-footer">
        <HealthStatus status={healthStatus} />
      </footer>
    </main>
  )
}
