import { describe, expect, it } from 'vitest'

import { GET } from './route'

describe('GET /api/health', function () {
  it('reports that the web process is ready and returns x-request-id', async function () {
    const request = new Request('http://localhost:3000/api/health', {
      headers: { 'x-request-id': 'custom-health-req-1' },
    })
    const response = GET(request)

    expect(response.status).toBe(200)
    expect(response.headers.get('x-request-id')).toBe('custom-health-req-1')
    await expect(response.json()).resolves.toEqual({ status: 'ok', service: 'web' })
  })

  it('generates a new x-request-id if not provided', async function () {
    const response = GET()

    expect(response.status).toBe(200)
    const header = response.headers.get('x-request-id')
    expect(header).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/,
    )
    await expect(response.json()).resolves.toEqual({ status: 'ok', service: 'web' })
  })
})
