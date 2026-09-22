import { describe, expect, it } from 'vitest'

import type { AnalyticsHealthGateway } from '../api/analytics-health-gateway'
import { loadHealthStatus } from './load-health-status'

describe('loadHealthStatus', function () {
  it('returns analytics service details when the backend is available', async function () {
    const gateway = createGateway({ status: 'ok', service: 'analytics', version: 'v1' })

    await expect(loadHealthStatus(gateway)).resolves.toEqual({
      availability: 'available',
      service: 'analytics',
      version: 'v1',
    })
  })

  it('returns an unavailable status without exposing transport errors', async function () {
    const gateway = createFailingGateway()

    await expect(loadHealthStatus(gateway)).resolves.toEqual({
      availability: 'unavailable',
    })
  })
})

function createGateway(
  health: Awaited<ReturnType<AnalyticsHealthGateway['read']>>,
): AnalyticsHealthGateway {
  return {
    read: async function () {
      return health
    },
  }
}

function createFailingGateway(): AnalyticsHealthGateway {
  return {
    read: async function () {
      throw new Error('connection refused')
    },
  }
}
