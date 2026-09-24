'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { LogOut, Loader2, Moon, Sun } from 'lucide-react'

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
  const [switchingWorkspace, setSwitchingWorkspace] = useState(false)

  const handleWorkspaceChange = async (workspaceId: string) => {
    setSwitchingWorkspace(true)
    setCurrentId(workspaceId)

    try {
      const response = await fetch('/api/workspace/current', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ workspace_id: workspaceId }),
      })
      if (!response.ok) throw new Error('Workspace switch failed')
      router.refresh()
    } finally {
      setSwitchingWorkspace(false)
    }
  }

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
    <div
      className="flex w-full items-center justify-between gap-4"
      data-testid="workspace-context-bar"
    >
      <div className="flex items-center gap-3">
        <WorkspaceSwitcher
          currentWorkspaceId={currentId}
          workspaces={accessibleWorkspaces}
          onSelectWorkspace={handleWorkspaceChange}
        />
        {switchingWorkspace && (
          <Loader2 aria-label="Переключение workspace" className="size-4 animate-spin" />
        )}
      </div>

      <div className="flex items-center gap-3 sm:gap-4">
        <ThemeToggle />

        <div className="hidden sm:flex items-center gap-2.5 text-left">
          <div className="size-7 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 text-xs font-semibold select-none">
            {context.user.name ? context.user.name.charAt(0).toUpperCase() : 'U'}
          </div>
          <div className="flex flex-col">
            <span className="text-xs font-medium text-foreground leading-tight">
              {context.user.name}
            </span>
            <span className="text-[11px] text-muted-foreground leading-tight">
              {context.user.email}
            </span>
          </div>
        </div>

        <Button
          variant="destructive"
          size="sm"
          onClick={handleLogout}
          disabled={loggingOut}
          className="h-8 px-2.5 text-xs gap-1.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 cursor-pointer transition-colors"
          data-testid="logout-button"
        >
          {loggingOut ? (
            <Loader2 className="size-3.5 animate-spin" />
          ) : (
            <LogOut className="size-3.5" />
          )}

          <span className="hidden sm:inline">Выйти</span>
        </Button>
      </div>
    </div>
  )
}

const THEME_STORAGE_KEY = 'autobi-theme'
function ThemeToggle() {
  function toggleTheme() {
    const root = document.documentElement
    const nextTheme = root.classList.contains('dark') ? 'light' : 'dark'

    root.classList.toggle('dark', nextTheme === 'dark')
    root.style.colorScheme = nextTheme
    window.localStorage.setItem(THEME_STORAGE_KEY, nextTheme)
  }

  return (
    <Button
      variant="ghost"
      size="icon"
      aria-label="Переключить цветовую тему"
      title="Переключить цветовую тему"
      onClick={toggleTheme}
    >
      <Moon aria-hidden="true" className="dark:hidden" />
      <Sun aria-hidden="true" className="dark:block" />
    </Button>
  )
}
