import type { components } from '../../../shared/api/generated/schema'
import { analyticsClient } from '../../../shared/api/analytics-client'

const ANALYTICS_HEALTH_TIMEOUT_MS = 3_000

export type AnalyticsHealth = components['schemas']['HealthResponse']

export interface AnalyticsHealthGateway {
  read(): Promise<AnalyticsHealth>
}

export const analyticsHealthGateway: AnalyticsHealthGateway = {
  async read(): Promise<AnalyticsHealth> {
    const { data, error, response } = await analyticsClient.GET('/health', {
      signal: AbortSignal.timeout(ANALYTICS_HEALTH_TIMEOUT_MS),
    })

    if (!response.ok || error || !data) throw new Error('Analytics health check failed')

    return data
  },
}
