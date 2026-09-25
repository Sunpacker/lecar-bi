import React from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '@/src/features/auth/model/session'
import { SecurityForm } from '@/src/features/profile/ui/security-form'

export const dynamic = 'force-dynamic'

export default async function SecuritySettingsPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  return (
    <div className="space-y-6">
      <SecurityForm />
    </div>
  )
}
