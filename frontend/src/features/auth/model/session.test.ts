import { describe, expect, it } from 'vitest'
import { parseSessionValue, serializeSession, type SessionUser } from './session'

describe('session serialization and security', () => {
  it('serializes and parses valid signed session', () => {
    const user = {
      id: 'user-1',
      email: 'test@autobi.internal',
      name: 'Test User',
    }

    const token = serializeSession(user)
    expect(token).toContain('.')

    const parsed = parseSessionValue(token)
    expect(parsed).not.toBeNull()
    expect(parsed?.userId).toBe('user-1')
    expect(parsed?.email).toBe('test@autobi.internal')
    expect(parsed?.name).toBe('Test User')
    expect(parsed?.expiresAt).toBeGreaterThan(Date.now())
  })

  it('rejects tampered session payload', () => {
    const user = {
      id: 'user-1',
      email: 'test@autobi.internal',
      name: 'Test User',
    }

    const token = serializeSession(user)
    const [payloadBase64, signature] = token.split('.')

    // Tamper with payload to elevate privilege to user-2
    const decoded = JSON.parse(
      Buffer.from(payloadBase64, 'base64url').toString('utf-8'),
    ) as SessionUser
    decoded.userId = 'user-2'
    const tamperedPayloadBase64 = Buffer.from(JSON.stringify(decoded)).toString(
      'base64url',
    )

    const forgedToken = `${tamperedPayloadBase64}.${signature}`
    expect(parseSessionValue(forgedToken)).toBeNull()
  })

  it('rejects invalid signature', () => {
    const user = {
      id: 'user-1',
      email: 'test@autobi.internal',
      name: 'Test User',
    }

    const token = serializeSession(user)
    const [payloadBase64] = token.split('.')
    const invalidToken = `${payloadBase64}.invalid-signature`

    expect(parseSessionValue(invalidToken)).toBeNull()
  })

  it('rejects expired session', () => {
    const expiredPayload: SessionUser = {
      userId: 'user-1',
      email: 'test@autobi.internal',
      name: 'Test User',
      expiresAt: Date.now() - 10000,
    }

    const payloadBase64 = Buffer.from(JSON.stringify(expiredPayload)).toString(
      'base64url',
    )
    // Even if unsigned legacy or with test secret, expired sessions must be rejected
    expect(parseSessionValue(payloadBase64)).toBeNull()
  })

  it('handles null, empty, or garbage input gracefully', () => {
    expect(parseSessionValue(undefined)).toBeNull()
    expect(parseSessionValue('')).toBeNull()
    expect(parseSessionValue('not-a-valid-session')).toBeNull()
    expect(parseSessionValue('malformed.payload.with.too.many.dots')).toBeNull()
  })
})
