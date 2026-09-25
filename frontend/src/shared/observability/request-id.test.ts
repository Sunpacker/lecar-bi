import { describe, it, expect } from 'vitest'
import { sanitizeOrGenerateRequestId } from './request-id'

describe('sanitizeOrGenerateRequestId', () => {
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
})
