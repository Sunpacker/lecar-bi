import React from 'react'
import { HealthStatus } from '../../../features/system-health/ui/health-status'
import type { HealthStatus as HealthStatusModel } from '../../../features/system-health/model/load-health-status'

interface FooterProps {
  status: HealthStatusModel
}

export function Footer({ status }: FooterProps) {
  return (
    <footer className="mt-auto py-6 px-4 sm:px-6 lg:px-8 border-t border-border flex justify-between items-center bg-background">
      <div className="text-sm text-muted-foreground">
        &copy; {new Date().getFullYear()} AutoBI
      </div>
      <HealthStatus status={status} />
    </footer>
  )
}
