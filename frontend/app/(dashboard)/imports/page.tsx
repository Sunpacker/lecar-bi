import React from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '../../../src/features/auth/model/session'
import {
  workspaceGateway,
  type CurrentWorkspace,
} from '../../../src/features/workspace/api/workspace-gateway'
import { DataIngestionView } from '../../../src/features/data-ingestion/ui/data-ingestion-view'

export const dynamic = 'force-dynamic'

export default async function ImportsPage() {
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

  const workspaceId = workspaceContext?.workspace.id ?? 'ws-1'

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <div className="mb-6 space-y-2">
        <span className="text-xs font-semibold text-emerald-400 tracking-wider uppercase">
          AUTOBI / DATA INGESTION
        </span>
        <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-foreground">
          Импорт данных
        </h1>
        <p className="text-sm sm:text-base text-muted-foreground max-w-2xl leading-relaxed">
          Загрузка сырых наборов данных продаж и складских остатков, потоковая валидация,
          изоляция некорректных строк и автоматическая проекция в витрины аналитики.
        </p>
      </div>

      <DataIngestionView userId={userId} workspaceId={workspaceId} />
    </main>
  )
}
