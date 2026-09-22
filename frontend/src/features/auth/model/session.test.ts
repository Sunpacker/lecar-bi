import { describe, expect, it } from 'vitest'
import { serializeSession, parseSessionValue, SESSION_DURATION_SECONDS } from './session'

describe('session', () => {
  const mockUser = {
    id: 'user-1',
    email: 'elena@autobi.internal',
    name: 'Elena Rostova',
  }

  it('serializes and parses session correctly', () => {
    const serialized = serializeSession(mockUser)
    expect(serialized).toBeTypeOf('string')

    const parsed = parseSessionValue(serialized)
    expect(parsed).not.toBeNull()
    expect(parsed?.userId).toBe('user-1')
    expect(parsed?.email).toBe('elena@autobi.internal')
    expect(parsed?.name).toBe('Elena Rostova')
    expect(parsed?.expiresAt).toBeGreaterThan(Date.now())
  })

  it('returns null for empty or invalid session values', () => {
    expect(parseSessionValue(undefined)).toBeNull()
    expect(parseSessionValue('')).toBeNull()
    expect(parseSessionValue('invalid-base64-content')).toBeNull()
    expect(
      parseSessionValue(Buffer.from('{"foo":"bar"}').toString('base64url')),
    ).toBeNull()
  })

  it('returns null for expired session', () => {
    const expiredPayload = {
      userId: 'user-1',
      email: 'elena@autobi.internal',
      name: 'Elena Rostova',
      expiresAt: Date.now() - 10000,
    }
    const expiredSerialized = Buffer.from(JSON.stringify(expiredPayload)).toString(
      'base64url',
    )

    expect(parseSessionValue(expiredSerialized)).toBeNull()
  })

  it('has a 7-day session duration', () => {
    expect(SESSION_DURATION_SECONDS).toBe(60 * 60 * 24 * 7)
  })
})
