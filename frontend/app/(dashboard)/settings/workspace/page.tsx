import React from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '@/src/features/auth/model/session'
import {
  workspaceGateway,
  type CurrentWorkspace,
} from '@/src/features/workspace/api/workspace-gateway'
import { hasCapability } from '@/src/features/workspace/model/workspace-access'
import { WorkspaceSettingsForm } from '@/src/features/workspace/ui/workspace-settings-form'

export const dynamic = 'force-dynamic'

export default async function WorkspaceSettingsPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  let workspaceContext: CurrentWorkspace | null = null

  try {
    workspaceContext = await workspaceGateway.getCurrentWorkspace()
  } catch {
    // fallback
  }

  if (!workspaceContext) {
    return (
      <div className="rounded-lg border border-border p-6 text-center text-muted-foreground">
        Не удалось загрузить данные рабочего пространства.
      </div>
    )
  }

  const canManage = hasCapability(
    workspaceContext.workspace.capabilities,
    'workspace.settings.manage',
  )

  return (
    <div className="space-y-6">
      <WorkspaceSettingsForm
        workspace={workspaceContext.workspace}
        canManage={canManage}
      />
    </div>
  )
}
