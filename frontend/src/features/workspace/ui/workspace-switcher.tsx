'use client'

import React from 'react'
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
    <div className="flex items-center gap-3" data-testid="workspace-switcher">
      <Label
        htmlFor="workspace-select"
        className="text-xs uppercase text-muted-foreground font-semibold tracking-wider whitespace-nowrap"
      >
        Current Workspace
      </Label>
      <div className="relative">
        <select
          id="workspace-select"
          aria-label="Current Workspace"
          value={currentWorkspaceId}
          onChange={(e) => onSelectWorkspace(e.target.value)}
          className="h-8 rounded-lg border border-input bg-card px-3 pr-8 py-1 text-sm font-medium text-foreground outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 appearance-none dark:bg-input/30"
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
        <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">
          ▼
        </span>
      </div>
    </div>
  )
}
