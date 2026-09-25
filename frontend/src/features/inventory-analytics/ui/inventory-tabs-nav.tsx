'use client'

import React from 'react'
import Link from 'next/link'
import { useSearchParams } from 'next/navigation'
import { LayoutGridIcon, BoxesIcon, TrendingUpIcon } from 'lucide-react'

interface InventoryTabsNavProps {
  activeTab: 'overview' | 'abc-xyz' | 'forecast'
}

export function InventoryTabsNav({ activeTab }: InventoryTabsNavProps) {
  const searchParams = useSearchParams()

  const createTabUrl = (tab: 'overview' | 'abc-xyz' | 'forecast') => {
    const params = new URLSearchParams(searchParams.toString())
    if (tab === 'overview') {
      params.delete('tab')
    } else {
      params.set('tab', tab)
    }
    const query = params.toString()
    return `/inventory${query ? `?${query}` : ''}`
  }

  return (
    <div className="flex items-center gap-2 border-b border-border/60 pb-3 mb-6">
      <Link
        href={createTabUrl('overview')}
        className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-sm font-medium transition-colors ${
          activeTab === 'overview'
            ? 'bg-primary/10 text-primary border border-primary/20 shadow-xs'
            : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
        }`}
      >
        <BoxesIcon className="h-4 w-4" />
        Обзор остатков
      </Link>
      <Link
        href={createTabUrl('abc-xyz')}
        className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-sm font-medium transition-colors ${
          activeTab === 'abc-xyz'
            ? 'bg-primary/10 text-primary border border-primary/20 shadow-xs'
            : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
        }`}
      >
        <LayoutGridIcon className="h-4 w-4" />
        ABC / XYZ Анализ
      </Link>
      <Link
        href={createTabUrl('forecast')}
        className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-sm font-medium transition-colors ${
          activeTab === 'forecast'
            ? 'bg-primary/10 text-primary border border-primary/20 shadow-xs'
            : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
        }`}
      >
        <TrendingUpIcon className="h-4 w-4" />
        Прогноз спроса
      </Link>
    </div>
  )
}
