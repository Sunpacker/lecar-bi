import {
  analyticsHealthGateway,
  type AnalyticsHealthGateway,
} from '../api/analytics-health-gateway'

export type HealthStatus =
  | { availability: 'available'; service: string; version: string }
  | { availability: 'unavailable' }

export async function loadHealthStatus(
  gateway: AnalyticsHealthGateway = analyticsHealthGateway,
): Promise<HealthStatus> {
  try {
    return await readAvailableStatus(gateway)
  } catch {
    return createUnavailableStatus()
  }
}

async function readAvailableStatus(
  gateway: AnalyticsHealthGateway,
): Promise<HealthStatus> {
  const health = await gateway.read()

  return {
    availability: 'available',
    service: health.service,
    version: health.version,
  }
}

function createUnavailableStatus(): HealthStatus {
  return { availability: 'unavailable' }
}
