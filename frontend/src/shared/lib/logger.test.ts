import { describe, it, expect } from 'vitest'
import { formatLogEntry } from './logger'

describe('Structured Logger', () => {
  it('formats entry with standard JSON schema', () => {
    const entry = formatLogEntry('info', 'Page rendered', {
      requestId: 'req-abc',
      operation: 'GET /dashboard',
      context: { userId: 'usr-1' },
    })

    expect(entry.service).toBe('web')
    expect(entry.level).toBe('INFO')
    expect(entry.message).toBe('Page rendered')
    expect(entry.request_id).toBe('req-abc')
    expect(entry.operation).toBe('GET /dashboard')
    expect(entry.context).toEqual({ userId: 'usr-1' })
    expect(entry.timestamp).toMatch(/^\d{4}-\d{2}-\d{2}T/)
  })

  it('redacts sensitive fields in context', () => {
    const entry = formatLogEntry('info', 'Auth action', {
      context: { password: 'secret', token: 'bearer', safe: 123 },
    })

    expect(entry.context.password).toBe('[REDACTED]')
    expect(entry.context.token).toBe('[REDACTED]')
    expect(entry.context.safe).toBe(123)
  })
})
