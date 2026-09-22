'use client'

import React from 'react'
import { LayoutDashboard, Menu } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { WorkspaceContextBar } from '../../../features/workspace/ui/workspace-context-bar'
import {
  type CurrentWorkspace,
  type Workspace,
} from '../../../features/workspace/api/workspace-gateway'
import { NavigationList } from './sidebar'

interface HeaderProps {
  workspaceContext: CurrentWorkspace | null
  accessibleWorkspaces: Workspace[]
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
      </div>
    </header>
  )
}
