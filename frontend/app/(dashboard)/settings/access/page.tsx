import React from 'react'
import { redirect } from 'next/navigation'
import { ShieldAlert } from 'lucide-react'
import { getSession } from '../../../../src/features/auth/model/session'
import {
  workspaceGateway,
  type CurrentWorkspace,
} from '../../../../src/features/workspace/api/workspace-gateway'
import { hasCapability } from '../../../../src/features/workspace/model/workspace-access'
import { WorkspaceAccessView } from '../../../../src/features/workspace/ui/workspace-access-view'

export const dynamic = 'force-dynamic'

export default async function WorkspaceAccessPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const userId = session.userId

  let workspaceContext: CurrentWorkspace | null = null

  try {
    workspaceContext = await workspaceGateway.getCurrentWorkspace()
  } catch {
    // Backend fallback
  }

  const capabilities = workspaceContext?.workspace.capabilities ?? []
  const canManageMembers = hasCapability(capabilities, 'workspace.members.manage')

  if (!canManageMembers) {
    return (
      <div className="space-y-6">
        <div className="mb-6 space-y-2">
          <span className="text-xs font-semibold text-emerald-400 tracking-wider uppercase">
            AUTOBI / WORKSPACE
          </span>
          <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-foreground">
            Управление доступом
          </h1>
        </div>

        <div
          role="alert"
          className="flex flex-col items-center justify-center rounded-lg border border-border bg-card p-12 text-center"
          data-testid="access-denied"
        >
          <div className="flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10 text-destructive mb-4">
            <ShieldAlert className="h-6 w-6" />
          </div>
          <h2 className="text-lg font-semibold text-foreground">Доступ ограничен</h2>
          <p className="mt-2 text-sm text-muted-foreground max-w-md">
            У вас нет прав для управления участниками и ролями этого рабочего
            пространства. Обратитесь к владельцу воркспейса.
          </p>
        </div>
      </div>
    )
  }

  const workspaceId = workspaceContext?.workspace.id ?? ''

  return (
    <div className="space-y-6">
      <div className="mb-6 space-y-2">
        <span className="text-xs font-semibold text-emerald-400 tracking-wider uppercase">
          AUTOBI / WORKSPACE
        </span>
        <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-foreground">
          Управление доступом
        </h1>
        <p className="text-sm sm:text-base text-muted-foreground max-w-2xl leading-relaxed">
          Просмотр участников рабочего пространства, управление ролями доступа и отправка
          приглашений.
        </p>
      </div>

      <WorkspaceAccessView
        workspaceId={workspaceId}
        currentUserId={userId}
        canManage={canManageMembers}
      />
    </div>
  )
}
