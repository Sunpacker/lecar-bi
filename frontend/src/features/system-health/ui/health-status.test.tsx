import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { HealthStatus } from './health-status'

describe('HealthStatus', function () {
  it('renders the connected analytics service', function () {
    render(
      <HealthStatus
        status={{ availability: 'available', service: 'analytics', version: 'v1' }}
      />,
    )

    expect(screen.getByText('Analytics API is ready · analytics v1')).toBeVisible()
  })

  it('renders a safe fallback when analytics is unavailable', function () {
    render(<HealthStatus status={{ availability: 'unavailable' }} />)

    expect(screen.getByText('Analytics API is unavailable')).toBeVisible()
  })
})
