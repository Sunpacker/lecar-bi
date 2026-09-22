'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { LogOut, Loader2 } from 'lucide-react'
import type { CurrentWorkspace, Workspace } from '../api/workspace-gateway'
import { WorkspaceSwitcher } from './workspace-switcher'
import { Button } from '@/components/ui/button'

interface WorkspaceContextBarProps {
  context: CurrentWorkspace
  accessibleWorkspaces: Workspace[]
}

export function WorkspaceContextBar({
  context,
  accessibleWorkspaces,
}: WorkspaceContextBarProps) {
  const router = useRouter()
  const [currentId, setCurrentId] = useState(context.workspace.id)
  const [loggingOut, setLoggingOut] = useState(false)

  const handleLogout = async () => {
    setLoggingOut(true)
    try {
      await fetch('/api/auth/logout', { method: 'POST' })
      router.push('/login')
      router.refresh()
    } finally {
      setLoggingOut(false)
    }
  }

  return (
    <header
      className="flex flex-wrap items-center justify-between gap-4 p-4 border border-border bg-card/60 rounded-xl mb-6 backdrop-blur shadow-xs"
      data-testid="workspace-context-bar"
    >
      <div>
        <div className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
          Пользователь
        </div>
        <div className="text-sm font-medium text-foreground">
          {context.user.name}{' '}
          <span className="text-muted-foreground font-normal">
            ({context.user.email})
          </span>
        </div>
      </div>
      <div className="flex items-center gap-3">
        <WorkspaceSwitcher
          currentWorkspaceId={currentId}
          workspaces={accessibleWorkspaces}
          onSelectWorkspace={setCurrentId}
        />
        <Button
          variant="outline"
          size="sm"
          onClick={handleLogout}
          disabled={loggingOut}
          className="text-xs gap-1.5 text-muted-foreground hover:text-destructive hover:border-destructive/40 cursor-pointer"
          data-testid="logout-button"
        >
          {loggingOut ? (
            <Loader2 className="size-3.5 animate-spin" />
          ) : (
            <LogOut className="size-3.5" />
          )}
          <span>Выйти</span>
        </Button>
      </div>
    </header>
  )
}
