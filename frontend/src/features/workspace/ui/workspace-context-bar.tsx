'use client'

import React, { useState } from 'react'
import type { CurrentWorkspace, Workspace } from '../api/workspace-gateway'
import { WorkspaceSwitcher } from './workspace-switcher'

interface WorkspaceContextBarProps {
  context: CurrentWorkspace
  accessibleWorkspaces: Workspace[]
}

export function WorkspaceContextBar({
  context,
  accessibleWorkspaces,
}: WorkspaceContextBarProps) {
  const [currentId, setCurrentId] = useState(context.workspace.id)

  return (
    <header
      className="flex flex-wrap items-center justify-between gap-4 p-4 border border-border bg-card/60 rounded-xl mb-6 backdrop-blur shadow-xs"
      data-testid="workspace-context-bar"
    >
      <div>
        <div className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
          Authenticated as
        </div>
        <div className="text-sm font-medium text-foreground">
          {context.user.name}{' '}
          <span className="text-muted-foreground font-normal">
            ({context.user.email})
          </span>
        </div>
      </div>
      <div>
        <WorkspaceSwitcher
          currentWorkspaceId={currentId}
          workspaces={accessibleWorkspaces}
          onSelectWorkspace={setCurrentId}
        />
      </div>
    </header>
  )
}
