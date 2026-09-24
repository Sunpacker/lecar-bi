import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { serializeSession, SESSION_COOKIE_NAME } from '@/src/features/auth/model/session'
import { proxySupportRequest } from '@/src/features/support/server/support-proxy'
import { GET } from './route'

const cookieStore = vi.hoisted(() => ({ get: vi.fn() }))

vi.mock('next/headers', () => ({ cookies: vi.fn(async () => cookieStore) }))

describe('support BFF', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    process.env.SESSION_SECRET = 'test-session-secret-with-at-least-32-characters'
    process.env.SUPPORT_BFF_SHARED_SECRET =
      'server-only-support-secret-with-at-least-32-characters'
    cookieStore.get.mockReturnValue(undefined)
  })

  afterEach(() => vi.restoreAllMocks())

  it('rejects a request without a verified Next session', async () => {
    const upstream = vi.spyOn(globalThis, 'fetch')

    const response = await GET(
      new Request('http://frontend.test/api/support/conversations'),
      context(['conversations']),
    )

    expect(response.status).toBe(401)
    expect(upstream).not.toHaveBeenCalled()
  })

  it('uses the signed session identity and server credential for upstream access', async () => {
    await authenticateAs({
      id: 'session-user',
      email: 'user@example.test',
      name: 'User',
    })
    const upstream = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValue(Response.json({ items: [], next_cursor: null }))
    const request = new Request(
      'http://frontend.test/api/support/conversations?per_page=20',
      {
        headers: {
          'X-User-Id': 'forged-victim',
          'X-Support-BFF-Key': 'forged-secret',
          'X-Workspace-Id': 'workspace-1',
        },
      },
    )

    const response = await GET(request, context(['conversations']))

    expect(response.status).toBe(200)
    const [url, init] = upstream.mock.calls[0]
    const headers = new Headers(init?.headers)
    expect(url).toBe('http://localhost:8080/api/v1/support/conversations?per_page=20')
    expect(headers.get('X-User-Id')).toBe('session-user')
    expect(headers.get('X-Support-BFF-Key')).toBe(
      'server-only-support-secret-with-at-least-32-characters',
    )
    expect(headers.get('X-Workspace-Id')).toBe('workspace-1')
  })

  it('preserves a JSON authorization error before opening an event stream', async () => {
    await authenticateAs({
      id: 'session-user',
      email: 'user@example.test',
      name: 'User',
    })
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      Response.json({ code: 'FORBIDDEN' }, { status: 403 }),
    )

    const response = await proxySupportRequest(
      new Request('http://frontend.test/api/support/generations/generation-1/events'),
      ['generations', 'generation-1', 'events'],
      true,
    )

    expect(response.status).toBe(403)
    expect(response.headers.get('Content-Type')).toContain('application/json')
    expect(response.headers.get('Content-Type')).not.toContain('text/event-stream')
  })
})

function context(path: string[]) {
  return { params: Promise.resolve({ path }) }
}

async function authenticateAs(user: {
  id: string
  email: string
  name: string
}): Promise<void> {
  const value = await serializeSession(user)
  cookieStore.get.mockImplementation((name: string) =>
    name === SESSION_COOKIE_NAME ? { value } : undefined,
  )
}
