import type { HealthStatus as HealthStatusModel } from '../model/load-health-status'

type HealthStatusProps = {
  status: HealthStatusModel
}

export function HealthStatus({ status }: HealthStatusProps) {
  if (status.availability === 'unavailable') {
    return <div className="status status--unavailable">Analytics API is unavailable</div>
  }

  return (
    <div className="status">
      Analytics API is ready · {status.service} {status.version}
    </div>
  )
}
