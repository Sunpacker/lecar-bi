'use client'

import React from 'react'
import { useSearchParams } from 'next/navigation'
import { InventoryTabsNav } from './inventory-tabs-nav'
import { InventoryDashboard } from './inventory-dashboard'
import { AbcXyzView } from './abc-xyz-view'

interface InventoryTabsContainerProps {
  userId: string
  workspaceId: string
}

export function InventoryTabsContainer({
  userId,
  workspaceId,
}: InventoryTabsContainerProps) {
  const searchParams = useSearchParams()
  const tab = searchParams.get('tab')
  const activeTab: 'overview' | 'abc-xyz' = tab === 'abc-xyz' ? 'abc-xyz' : 'overview'

  return (
    <div>
      <InventoryTabsNav activeTab={activeTab} />
      {activeTab === 'abc-xyz' ? (
        <AbcXyzView userId={userId} workspaceId={workspaceId} />
      ) : (
        <InventoryDashboard userId={userId} workspaceId={workspaceId} />
      )}
    </div>
  )
}
