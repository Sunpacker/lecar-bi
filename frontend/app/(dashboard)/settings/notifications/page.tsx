import React from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '@/src/features/auth/model/session'
import { NotificationPreferencesForm } from '@/src/features/notifications/ui/notification-preferences-form'

export const dynamic = 'force-dynamic'

export default async function NotificationSettingsPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  return (
    <div className="space-y-6">
      <NotificationPreferencesForm />
    </div>
  )
}
