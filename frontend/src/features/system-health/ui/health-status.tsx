import { Badge } from '@/components/ui/badge'
import type { HealthStatus as HealthStatusModel } from '../model/load-health-status'

type HealthStatusProps = {
  status: HealthStatusModel
}

export function HealthStatus({ status }: HealthStatusProps) {
  if (status.availability === 'unavailable') {
    return (
      <Badge
        variant="destructive"
        className="h-auto py-1.5 px-3 text-xs gap-2 font-normal"
        data-testid="health-status-badge"
      >
        <span className="size-2 rounded-full bg-destructive inline-block" />
        Analytics API is unavailable
      </Badge>
    )
  }

  return (
    <Badge
      variant="outline"
      className="h-auto py-1.5 px-3 text-xs gap-2 font-normal border-emerald-800/40 bg-emerald-950/20 text-emerald-400"
      data-testid="health-status-badge"
    >
      <span className="size-2 rounded-full bg-emerald-400 inline-block" />
      Analytics API is ready · {status.service} {status.version}
    </Badge>
  )
}
