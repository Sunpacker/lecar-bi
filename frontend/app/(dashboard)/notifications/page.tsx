import React from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '@/src/features/auth/model/session'
import { NotificationsList } from '@/src/features/notifications/ui/notifications-list'

export const dynamic = 'force-dynamic'

export default async function NotificationsPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <div className="mb-6 space-y-2">
        <span className="text-xs font-semibold text-emerald-400 tracking-wider uppercase">
          AUTOBI / УВЕДОМЛЕНИЯ
        </span>
        <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-foreground">
          Центр уведомлений
        </h1>
        <p className="text-sm sm:text-base text-muted-foreground max-w-2xl leading-relaxed">
          Просмотр уведомлений и важных событий текущего рабочего пространства.
        </p>
      </div>

      <div className="max-w-4xl">
        <NotificationsList />
      </div>
    </main>
  )
}
