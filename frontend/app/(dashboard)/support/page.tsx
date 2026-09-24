import React from 'react'
import { redirect } from 'next/navigation'

import { getSession } from '@/src/features/auth/model/session'
import { workspaceGateway } from '@/src/features/workspace/api/workspace-gateway'
import { getSelectedWorkspaceId } from '@/src/features/workspace/model/selected-workspace'
import { SupportChatView } from '@/src/features/support/ui/support-chat-view'

export const dynamic = 'force-dynamic'

export default async function SupportPage() {
  const session = await getSession()
  if (!session) redirect('/login')

  const selectedWorkspaceId = await getSelectedWorkspaceId()
  const current = await workspaceGateway.getCurrentWorkspace(
    session.userId,
    selectedWorkspaceId,
  )

  return (
    <main className="px-4 py-6 pb-16 sm:px-6 lg:px-8">
      <div className="mb-6 space-y-2">
        <span className="text-xs font-semibold uppercase tracking-wider text-emerald-500">
          AUTOBI / SUPPORT
        </span>
        <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
          Помощь по AutoBI
        </h1>
        <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base">
          Ответы по интерфейсу, настройкам и методикам на основе опубликованной
          документации.
        </p>
      </div>

      <SupportChatView workspaceId={current.workspace.id} />
    </main>
  )
}
