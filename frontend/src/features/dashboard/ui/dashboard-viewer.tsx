'use client'

import React from 'react'
import Link from 'next/link'
import { Button } from '@/components/ui/button'
import { ArrowLeft, RefreshCw, LayoutDashboard } from 'lucide-react'
import type { DashboardDetail } from '../api/dashboard-gateway'
import { DashboardGrid } from './dashboard-grid'

interface DashboardViewerProps {
  dashboard: DashboardDetail
  userId: string
  workspaceId: string
}

export function DashboardViewer({ dashboard, userId, workspaceId }: DashboardViewerProps) {
  const [refreshKey, setRefreshKey] = React.useState(0)

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border/60">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <Link href="/dashboards">
              <Button variant="ghost" size="sm" className="h-8 px-2 text-xs gap-1.5 text-muted-foreground hover:text-foreground">
                <ArrowLeft className="size-3.5" />
                <span>Назад к списку</span>
              </Button>
            </Link>
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
            <LayoutDashboard className="size-6 text-emerald-500" />
            {dashboard.title}
          </h1>
          {dashboard.description && (
            <p className="text-sm text-muted-foreground leading-relaxed">
              {dashboard.description}
            </p>
          )}
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setRefreshKey((k) => k + 1)}
            className="h-9 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
          >
            <RefreshCw className="size-3.5" />
            <span>Обновить данные</span>
          </Button>
        </div>
      </div>

      <DashboardGrid
        key={refreshKey}
        widgets={dashboard.widgets}
        userId={userId}
        workspaceId={workspaceId}
      />
    </div>
  )
}
