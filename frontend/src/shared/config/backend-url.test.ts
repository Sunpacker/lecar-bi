import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { getBackendApiUrl, normalizeBackendUrl, isLocalhost } from './backend-url'

describe('backend-url resolution', () => {
  const originalEnv = { ...process.env }

  beforeEach(() => {
    process.env = { ...originalEnv }
    delete process.env.ANALYTICS_INTERNAL_URL
    delete process.env.NEXT_PUBLIC_ANALYTICS_API_URL
    delete process.env.VERCEL
  })

  afterEach(() => {
    process.env = { ...originalEnv }
  })

  it('normalizes various URL formats correctly', () => {
    expect(normalizeBackendUrl('https://api.veloza.ru')).toBe('https://api.veloza.ru/lecar-bi/api/v1')
    expect(normalizeBackendUrl('https://api.veloza.ru/')).toBe('https://api.veloza.ru/lecar-bi/api/v1')
    expect(normalizeBackendUrl('https://api.veloza.ru/lecar-bi')).toBe('https://api.veloza.ru/lecar-bi/api/v1')
    expect(normalizeBackendUrl('https://api.veloza.ru/lecar-bi/')).toBe('https://api.veloza.ru/lecar-bi/api/v1')
    expect(normalizeBackendUrl('https://api.veloza.ru/lecar-bi/api/v1')).toBe('https://api.veloza.ru/lecar-bi/api/v1')
    expect(normalizeBackendUrl('https://api.veloza.ru/lecar-bi/api/v1/')).toBe('https://api.veloza.ru/lecar-bi/api/v1')
    expect(normalizeBackendUrl('https://api.veloza.ru/api/v1')).toBe('https://api.veloza.ru/api/v1')
  })

  it('detects localhost URLs', () => {
    expect(isLocalhost('http://localhost:8080/api/v1')).toBe(true)
    expect(isLocalhost('http://127.0.0.1:8080/api/v1')).toBe(true)
    expect(isLocalhost('https://api.veloza.ru/lecar-bi/api/v1')).toBe(false)
  })

  it('defaults to localhost when local without env variables', () => {
    expect(getBackendApiUrl()).toBe('http://localhost:8080/api/v1')
  })

  it('defaults to api.veloza.ru when in Vercel even if env points to localhost', () => {
    process.env.VERCEL = '1'
    process.env.NEXT_PUBLIC_ANALYTICS_API_URL = 'http://localhost:8080/api/v1'

    expect(getBackendApiUrl()).toBe('https://api.veloza.ru/lecar-bi/api/v1')
  })

  it('respects valid ANALYTICS_INTERNAL_URL in Vercel', () => {
    process.env.VERCEL = '1'
    process.env.ANALYTICS_INTERNAL_URL = 'https://custom-api.example.com/api/v1'

    expect(getBackendApiUrl()).toBe('https://custom-api.example.com/api/v1')
  })
})
