'use client'

import React from 'react'
import { useSearchParams } from 'next/navigation'
import { InventoryTabsNav } from './inventory-tabs-nav'
import { InventoryDashboard } from './inventory-dashboard'
import { AbcXyzView } from './abc-xyz-view'
import { ProductForecastView } from './product-forecast-view'

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
  const productId = searchParams.get('product_id') ?? undefined
  const warehouseId = searchParams.get('warehouse_id') ?? undefined

  let activeTab: 'overview' | 'abc-xyz' | 'forecast' = 'overview'
  if (tab === 'abc-xyz') {
    activeTab = 'abc-xyz'
  } else if (tab === 'forecast') {
    activeTab = 'forecast'
  }

  return (
    <div>
      <InventoryTabsNav activeTab={activeTab} />
      {activeTab === 'abc-xyz' ? (
        <AbcXyzView userId={userId} workspaceId={workspaceId} />
      ) : activeTab === 'forecast' ? (
        <ProductForecastView
          userId={userId}
          workspaceId={workspaceId}
          productId={productId}
          warehouseId={warehouseId}
        />
      ) : (
        <InventoryDashboard userId={userId} workspaceId={workspaceId} />
      )}
    </div>
  )
}
