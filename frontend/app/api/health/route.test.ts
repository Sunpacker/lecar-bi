import { describe, expect, it } from 'vitest'

import { GET } from './route'

describe('GET /api/health', function () {
  it('reports that the web process is ready', async function () {
    const response = GET()

    expect(response.status).toBe(200)
    await expect(response.json()).resolves.toEqual({ status: 'ok', service: 'web' })
  })
})
