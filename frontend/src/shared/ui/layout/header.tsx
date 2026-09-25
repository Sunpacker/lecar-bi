'use client'

import React from 'react'
import { LayoutDashboard, Menu, Moon, Sun } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { WorkspaceContextBar } from '../../../features/workspace/ui/workspace-context-bar'
import { NotificationBell } from '../../../features/notifications/ui/notification-bell'
import {
  type CurrentWorkspace,
  type Workspace,
} from '../../../features/workspace/api/workspace-gateway'
import { NavigationList } from './sidebar'

interface HeaderProps {
  workspaceContext: CurrentWorkspace | null
  accessibleWorkspaces: Workspace[]
}

function ThemeToggle() {
  const [theme, setTheme] = React.useState<'light' | 'dark'>(() => {
    if (typeof document !== 'undefined') {
      return document.documentElement.classList.contains('dark') ? 'dark' : 'light'
    }
    return 'dark'
  })

  const toggleTheme = () => {
    const nextTheme = theme === 'dark' ? 'light' : 'dark'
    setTheme(nextTheme)
    document.documentElement.classList.toggle('dark', nextTheme === 'dark')
    document.documentElement.style.colorScheme = nextTheme
    window.localStorage.setItem('autobi-theme', nextTheme)
  }

  return (
    <Button
      variant="ghost"
      size="icon"
      aria-label="Переключить цветовую тему"
      onClick={toggleTheme}
      className="size-9 text-muted-foreground hover:text-foreground"
    >
      <Sun className="h-4 w-4 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0" />
      <Moon className="absolute h-4 w-4 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
      <span className="sr-only">Переключить цветовую тему</span>
    </Button>
  )
}

export function Header({ workspaceContext, accessibleWorkspaces }: HeaderProps) {
  const [open, setOpen] = React.useState(false)

  return (
    <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-x-4 border-b border-border bg-background/95 backdrop-blur px-4 shadow-2xs sm:px-6 lg:px-8">
      <Sheet open={open} onOpenChange={setOpen}>
        <SheetTrigger
          render={<Button variant="ghost" size="icon" className="lg:hidden" />}
        >
          <Menu className="h-5 w-5" />
          <span className="sr-only">Open menu</span>
        </SheetTrigger>

        <SheetContent side="left" className="w-64 p-0">
          <div className="flex h-16 shrink-0 items-center gap-x-2 px-6 border-b border-border">
            <LayoutDashboard className="h-6 w-6 text-emerald-500" />
            <SheetTitle className="font-bold text-lg tracking-tight m-0">
              AutoBI
            </SheetTitle>
          </div>

          <nav className="flex-1 px-3 py-4 overflow-y-auto">
            <NavigationList onItemClick={() => setOpen(false)} />
          </nav>
        </SheetContent>
      </Sheet>

      <div className="flex min-w-0 flex-1 items-center justify-between gap-3">
        <div className="min-w-0 flex-1">
          {workspaceContext && (
            <WorkspaceContextBar
              context={workspaceContext}
              accessibleWorkspaces={accessibleWorkspaces}
            />
          )}
        </div>
        <div className="flex items-center gap-1 sm:gap-2">
          <NotificationBell />
          <ThemeToggle />
        </div>
      </div>
    </header>
  )
}
