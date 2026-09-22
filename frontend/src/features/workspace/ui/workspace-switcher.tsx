'use client'

import React from 'react'
import { ChevronDown } from 'lucide-react'
import { Label } from '@/components/ui/label'
import type { Workspace } from '../api/workspace-gateway'

interface WorkspaceSwitcherProps {
  currentWorkspaceId: string
  workspaces: Workspace[]
  onSelectWorkspace: (workspaceId: string) => void
}

export function WorkspaceSwitcher({
  currentWorkspaceId,
  workspaces,
  onSelectWorkspace,
}: WorkspaceSwitcherProps) {
  return (
    <div className="flex items-center gap-2 sm:gap-2.5" data-testid="workspace-switcher">
      <Label
        htmlFor="workspace-select"
        className="text-xs text-muted-foreground font-medium whitespace-nowrap hidden sm:inline-block"
      >
        Current Workspace
      </Label>
      <div className="relative">
        <select
          id="workspace-select"
          aria-label="Current Workspace"
          value={currentWorkspaceId}
          onChange={(e) => onSelectWorkspace(e.target.value)}
          className="h-8 rounded-md border border-input bg-background/50 px-2.5 pr-7 py-1 text-xs sm:text-sm font-medium text-foreground outline-none transition-colors hover:bg-accent/40 focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 appearance-none cursor-pointer"
        >
          {workspaces.map((ws) => (
            <option
              key={ws.id}
              value={ws.id}
              className="bg-popover text-popover-foreground"
            >
              {ws.name} ({ws.role})
            </option>
          ))}
        </select>
        <ChevronDown className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
      </div>
    </div>
  )
}
