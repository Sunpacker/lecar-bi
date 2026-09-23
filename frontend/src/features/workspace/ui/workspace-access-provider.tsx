'use client'

import React, { createContext, useContext, useMemo } from 'react'
import {
  hasAllCapabilities,
  hasAnyCapability,
  hasCapability,
  type WorkspaceCapability,
} from '../model/workspace-access'

interface WorkspaceAccessContextValue {
  readonly capabilities: readonly WorkspaceCapability[]
  hasCapability: (capability: WorkspaceCapability) => boolean
  hasAllCapabilities: (required: readonly WorkspaceCapability[]) => boolean
  hasAnyCapability: (required: readonly WorkspaceCapability[]) => boolean
}

const DEFAULT_VALUE: WorkspaceAccessContextValue = {
  capabilities: [],
  hasCapability: () => false,
  hasAllCapabilities: () => false,
  hasAnyCapability: () => false,
}

const WorkspaceAccessContext = createContext<WorkspaceAccessContextValue>(DEFAULT_VALUE)

interface WorkspaceAccessProviderProps {
  capabilities?: readonly WorkspaceCapability[] | null
  children: React.ReactNode
}

export function WorkspaceAccessProvider({
  capabilities,
  children,
}: WorkspaceAccessProviderProps): React.JSX.Element {
  const value = useMemo<WorkspaceAccessContextValue>(() => {
    const caps = capabilities ?? []
    return {
      capabilities: caps,
      hasCapability: (cap) => hasCapability(caps, cap),
      hasAllCapabilities: (req) => hasAllCapabilities(caps, req),
      hasAnyCapability: (req) => hasAnyCapability(caps, req),
    }
  }, [capabilities])

  return (
    <WorkspaceAccessContext.Provider value={value}>
      {children}
    </WorkspaceAccessContext.Provider>
  )
}

export function useWorkspaceAccess(): WorkspaceAccessContextValue {
  return useContext(WorkspaceAccessContext)
}
