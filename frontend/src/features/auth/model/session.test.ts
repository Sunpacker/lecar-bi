import { describe, expect, it, vi } from 'vitest'
import { serializeSession, parseSessionValue, SESSION_DURATION_SECONDS } from './session'

describe('session', () => {
  process.env.SESSION_SECRET = 'test-session-secret-with-at-least-32-characters'
  const mockUser = {
    id: 'user-1',
    email: 'elena@autobi.internal',
    name: 'Elena Rostova',
  }

  it('serializes and parses session correctly', async () => {
    const serialized = await serializeSession(mockUser)
    expect(serialized).toBeTypeOf('string')

    const parsed = await parseSessionValue(serialized)
    expect(parsed).not.toBeNull()
    expect(parsed?.userId).toBe('user-1')
    expect(parsed?.email).toBe('elena@autobi.internal')
    expect(parsed?.name).toBe('Elena Rostova')
    expect(parsed?.expiresAt).toBeGreaterThan(Date.now())
  })

  it('returns null for empty, invalid or tampered session values', async () => {
    await expect(parseSessionValue(undefined)).resolves.toBeNull()
    await expect(parseSessionValue('')).resolves.toBeNull()
    await expect(parseSessionValue('invalid-base64-content')).resolves.toBeNull()

    const serialized = await serializeSession(mockUser)
    await expect(parseSessionValue(`${serialized}tampered`)).resolves.toBeNull()
  })

  it('returns null when a valid session expires', async () => {
    vi.useFakeTimers()
    const serialized = await serializeSession(mockUser)
    vi.advanceTimersByTime(SESSION_DURATION_SECONDS * 1000 + 1)

    await expect(parseSessionValue(serialized)).resolves.toBeNull()
    vi.useRealTimers()
  })

  it('has a 7-day session duration', () => {
    expect(SESSION_DURATION_SECONDS).toBe(60 * 60 * 24 * 7)
  })
})
