import { afterEach, describe, it, expect, vi } from 'vitest'
import { sanitizeOrGenerateRequestId } from './request-id'

describe('sanitizeOrGenerateRequestId', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('returns valid incoming request id', () => {
    const validId = 'client-req-12345'
    expect(sanitizeOrGenerateRequestId(validId)).toBe(validId)
  })

  it('generates a uuid v4 when header is null or empty', () => {
    const id = sanitizeOrGenerateRequestId(null)
    expect(id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/)
  })

  it('replaces invalid header characters with a generated uuid', () => {
    const invalidId = 'bad<script>id!@#'
    const id = sanitizeOrGenerateRequestId(invalidId)
    expect(id).not.toBe(invalidId)
    expect(id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/)
  })

  it('generates a valid uuid when crypto.randomUUID is unavailable', () => {
    vi.stubGlobal('crypto', {
      getRandomValues(bytes: Uint8Array) {
        bytes.set([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15])
        return bytes
      },
    })

    expect(sanitizeOrGenerateRequestId()).toBe('00010203-0405-4607-8809-0a0b0c0d0e0f')
  })
})
