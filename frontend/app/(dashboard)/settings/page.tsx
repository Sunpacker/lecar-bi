import React from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '@/src/features/auth/model/session'
import { workspaceGateway } from '@/src/features/workspace/api/workspace-gateway'
import { ProfileForm } from '@/src/features/profile/ui/profile-form'
import { ThemeSettings } from '@/src/features/profile/ui/theme-settings'

export const dynamic = 'force-dynamic'

export default async function ProfileSettingsPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  let user = {
    id: session.userId,
    email: session.email,
    name: session.name,
  }

  try {
    const currentWorkspace = await workspaceGateway.getCurrentWorkspace()
    if (currentWorkspace?.user) {
      user = {
        id: currentWorkspace.user.id,
        email: currentWorkspace.user.email,
        name: currentWorkspace.user.name,
      }
    }
  } catch {
    // fallback to session user
  }

  return (
    <div className="space-y-6">
      <ProfileForm initialUser={user} />
      <ThemeSettings />
    </div>
  )
}
