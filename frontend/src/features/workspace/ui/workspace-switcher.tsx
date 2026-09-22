'use client'

import React from 'react'
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
    <div className="workspace-switcher" data-testid="workspace-switcher">
      <label
        htmlFor="workspace-select"
        className="text-xs uppercase text-slate-500 font-semibold"
      >
        Current Workspace
      </label>
      <select
        id="workspace-select"
        value={currentWorkspaceId}
        onChange={(e) => onSelectWorkspace(e.target.value)}
        className="rounded border border-slate-300 px-3 py-1 text-sm bg-white font-medium"
      >
        {workspaces.map((ws) => (
          <option key={ws.id} value={ws.id}>
            {ws.name} ({ws.role})
          </option>
        ))}
      </select>
    </div>
  )
}
