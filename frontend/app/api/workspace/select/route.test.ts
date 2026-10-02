import { describe, expect, it, vi, beforeEach } from 'vitest'
import { NextRequest } from 'next/server'
import { POST } from './route'
import * as sessionModule from '@/src/features/auth/model/session'

vi.mock('@/src/features/auth/model/session', () => ({
  getSession: vi.fn(),
  setWorkspaceCookie: vi.fn(),
}))

describe('POST /api/workspace/select', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns 401 if user is not authenticated', async () => {
    vi.mocked(sessionModule.getSession).mockResolvedValue(null)

    const request = new NextRequest('http://localhost:3000/api/workspace/select', {
      method: 'POST',
      body: JSON.stringify({ workspaceId: 'ws-123' }),
    })
    const response = await POST(request)

    expect(response.status).toBe(401)
    const data = await response.json()
    expect(data.code).toBe('UNAUTHENTICATED')
  })

  it('returns 400 if workspaceId is missing or invalid', async () => {
    vi.mocked(sessionModule.getSession).mockResolvedValue({
      userId: 'user-1',
      email: 'user@example.com',
      name: 'User',
      token: 'jwt-token',
      expiresAt: Date.now() + 3600000,
    })

    const request = new NextRequest('http://localhost:3000/api/workspace/select', {
      method: 'POST',
      body: JSON.stringify({}),
    })
    const response = await POST(request)

    expect(response.status).toBe(400)
    const data = await response.json()
    expect(data.code).toBe('INVALID_WORKSPACE')
  })

  it('sets workspace cookie and returns success when workspaceId is valid', async () => {
    vi.mocked(sessionModule.getSession).mockResolvedValue({
      userId: 'user-1',
      email: 'user@example.com',
      name: 'User',
      token: 'jwt-token',
      expiresAt: Date.now() + 3600000,
    })

    const request = new NextRequest('http://localhost:3000/api/workspace/select', {
      method: 'POST',
      body: JSON.stringify({ workspaceId: 'ws-456' }),
    })
    const response = await POST(request)

    expect(response.status).toBe(200)
    const data = await response.json()
    expect(data).toEqual({ success: true, workspaceId: 'ws-456' })
    expect(sessionModule.setWorkspaceCookie).toHaveBeenCalledWith('ws-456')
  })
})
