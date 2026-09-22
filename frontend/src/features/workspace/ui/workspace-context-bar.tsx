import React from 'react'
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
  return (
    <header
      className="flex items-center justify-between p-4 border-b border-slate-200 bg-slate-50"
      data-testid="workspace-context-bar"
    >
      <div>
        <div className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
          Authenticated as
        </div>
        <div className="text-sm font-medium text-slate-800">
          {context.user.name} ({context.user.email})
        </div>
      </div>
      <div>
        <WorkspaceSwitcher
          currentWorkspaceId={context.workspace.id}
          workspaces={accessibleWorkspaces}
          onSelectWorkspace={() => {}}
        />
      </div>
    </header>
  )
}
