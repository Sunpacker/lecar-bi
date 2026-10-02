import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { NextRequest } from 'next/server'
import { GET, POST } from './route'
import * as sessionModule from '@/src/features/auth/model/session'

vi.mock('@/src/features/auth/model/session', () => ({
  getSession: vi.fn(),
  getWorkspaceCookie: vi.fn(),
  setWorkspaceCookie: vi.fn(),
}))

describe('Notifications API Proxy (/api/notifications/[...path])', () => {
  const originalFetch = global.fetch

  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    global.fetch = originalFetch
  })

  it('returns 401 UNAUTHENTICATED when session is missing', async () => {
    vi.mocked(sessionModule.getSession).mockResolvedValue(null)

    const request = new NextRequest(
      'http://localhost:3000/api/notifications/notifications/unread-count',
    )
    const response = await GET(request, {
      params: Promise.resolve({ path: ['notifications', 'unread-count'] }),
    })

    expect(response.status).toBe(401)
    const data = await response.json()
    expect(data.code).toBe('UNAUTHENTICATED')
  })

  it('auto-resolves workspace from backend when cookie and header are missing for unread-count', async () => {
    vi.mocked(sessionModule.getSession).mockResolvedValue({
      userId: 'user-123',
      email: 'user@example.com',
      name: 'User',
      token: 'jwt-token-abc',
      expiresAt: Date.now() + 3600000,
    })
    vi.mocked(sessionModule.getWorkspaceCookie).mockResolvedValue(null)

    global.fetch = vi.fn().mockImplementation(async (url: string | URL) => {
      const urlStr = url.toString()
      if (urlStr.includes('/workspaces/current')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            user: { id: 'user-123' },
            workspace: { id: 'ws-resolved-456' },
          }),
        }
      }
      if (urlStr.includes('/notifications/unread-count')) {
        return {
          ok: true,
          status: 200,
          headers: new Headers({ 'Content-Type': 'application/json' }),
          text: async () => JSON.stringify({ unread_count: 5 }),
        }
      }
      return { ok: false, status: 404, text: async () => '' }
    })

    const request = new NextRequest(
      'http://localhost:3000/api/notifications/notifications/unread-count',
    )
    const response = await GET(request, {
      params: Promise.resolve({ path: ['notifications', 'unread-count'] }),
    })

    expect(response.status).toBe(200)
    const data = await response.json()
    expect(data).toEqual({ unread_count: 5 })
    expect(sessionModule.setWorkspaceCookie).toHaveBeenCalledWith('ws-resolved-456')
  })

  it('uses x-workspace-id header directly without calling backend workspaces/current', async () => {
    vi.mocked(sessionModule.getSession).mockResolvedValue({
      userId: 'user-123',
      email: 'user@example.com',
      name: 'User',
      token: 'jwt-token-abc',
      expiresAt: Date.now() + 3600000,
    })
    vi.mocked(sessionModule.getWorkspaceCookie).mockResolvedValue(null)

    let capturedHeaders: Headers | undefined
    global.fetch = vi
      .fn()
      .mockImplementation(async (url: string | URL, init?: RequestInit) => {
        capturedHeaders = new Headers(init?.headers)
        return {
          ok: true,
          status: 200,
          headers: new Headers({ 'Content-Type': 'application/json' }),
          text: async () => JSON.stringify({ unread_count: 3 }),
        }
      })

    const request = new NextRequest(
      'http://localhost:3000/api/notifications/notifications/unread-count',
      {
        headers: { 'x-workspace-id': 'ws-from-header' },
      },
    )
    const response = await GET(request, {
      params: Promise.resolve({ path: ['notifications', 'unread-count'] }),
    })

    expect(response.status).toBe(200)
    expect(capturedHeaders?.get('X-Workspace-Id')).toBe('ws-from-header')
  })

  it('uses workspace from cookie when provided', async () => {
    vi.mocked(sessionModule.getSession).mockResolvedValue({
      userId: 'user-123',
      email: 'user@example.com',
      name: 'User',
      token: 'jwt-token-abc',
      expiresAt: Date.now() + 3600000,
    })
    vi.mocked(sessionModule.getWorkspaceCookie).mockResolvedValue('ws-from-cookie')

    let capturedHeaders: Headers | undefined
    global.fetch = vi
      .fn()
      .mockImplementation(async (url: string | URL, init?: RequestInit) => {
        capturedHeaders = new Headers(init?.headers)
        return {
          ok: true,
          status: 200,
          headers: new Headers({ 'Content-Type': 'application/json' }),
          text: async () => JSON.stringify({ unread_count: 2 }),
        }
      })

    const request = new NextRequest(
      'http://localhost:3000/api/notifications/notifications/unread-count',
    )
    const response = await GET(request, {
      params: Promise.resolve({ path: ['notifications', 'unread-count'] }),
    })

    expect(response.status).toBe(200)
    expect(capturedHeaders?.get('X-Workspace-Id')).toBe('ws-from-cookie')
  })

  it('normalizes targetPath when called as /api/notifications/unread-count', async () => {
    vi.mocked(sessionModule.getSession).mockResolvedValue({
      userId: 'user-123',
      email: 'user@example.com',
      name: 'User',
      token: 'jwt-token-abc',
      expiresAt: Date.now() + 3600000,
    })
    vi.mocked(sessionModule.getWorkspaceCookie).mockResolvedValue('ws-1')

    let fetchedUrl = ''
    global.fetch = vi.fn().mockImplementation(async (url: string | URL) => {
      fetchedUrl = url.toString()
      return {
        ok: true,
        status: 200,
        headers: new Headers({ 'Content-Type': 'application/json' }),
        text: async () => JSON.stringify({ unread_count: 1 }),
      }
    })

    const request = new NextRequest(
      'http://localhost:3000/api/notifications/unread-count',
    )
    const response = await GET(request, {
      params: Promise.resolve({ path: ['unread-count'] }),
    })

    expect(response.status).toBe(200)
    expect(fetchedUrl).toContain('/notifications/unread-count')
  })

  it('falls back gracefully to unread_count 0 when workspace cannot be resolved and no workspace cookie exists', async () => {
    vi.mocked(sessionModule.getSession).mockResolvedValue({
      userId: 'user-123',
      email: 'user@example.com',
      name: 'User',
      token: 'jwt-token-abc',
      expiresAt: Date.now() + 3600000,
    })
    vi.mocked(sessionModule.getWorkspaceCookie).mockResolvedValue(null)

    // Backend /workspaces/current fails
    global.fetch = vi.fn().mockImplementation(async () => {
      return {
        ok: false,
        status: 500,
        text: async () => 'Internal Error',
      }
    })

    const request = new NextRequest(
      'http://localhost:3000/api/notifications/notifications/unread-count',
    )
    const response = await GET(request, {
      params: Promise.resolve({ path: ['notifications', 'unread-count'] }),
    })

    expect(response.status).toBe(200)
    const data = await response.json()
    expect(data).toEqual({ unread_count: 0 })
  })

  it('returns 400 MISSING_WORKSPACE for mutation methods when workspace cannot be resolved', async () => {
    vi.mocked(sessionModule.getSession).mockResolvedValue({
      userId: 'user-123',
      email: 'user@example.com',
      name: 'User',
      token: 'jwt-token-abc',
      expiresAt: Date.now() + 3600000,
    })
    vi.mocked(sessionModule.getWorkspaceCookie).mockResolvedValue(null)

    // Backend fails
    global.fetch = vi.fn().mockImplementation(async () => {
      return { ok: false, status: 500, text: async () => '' }
    })

    const request = new NextRequest(
      'http://localhost:3000/api/notifications/notifications/read-all',
      {
        method: 'POST',
        body: JSON.stringify({}),
      },
    )
    const response = await POST(request, {
      params: Promise.resolve({ path: ['notifications', 'read-all'] }),
    })

    expect(response.status).toBe(400)
    const data = await response.json()
    expect(data.code).toBe('MISSING_WORKSPACE')
  })
})
