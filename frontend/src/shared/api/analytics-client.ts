import createClient, { type Middleware } from 'openapi-fetch'

import type { paths } from './generated/schema'
import { sanitizeOrGenerateRequestId } from '../observability/request-id'
import {
  SESSION_COOKIE_NAME,
  parseSessionValue,
} from '../../features/auth/model/session-token'

const DEFAULT_HTTP_TIMEOUT_MS = 15000

function getBaseUrl(): string {
  if (typeof window === 'undefined') {
    // Server-side: go directly to backend
    return (
      process.env.ANALYTICS_INTERNAL_URL ||
      process.env.NEXT_PUBLIC_ANALYTICS_API_URL ||
      (process.env.VERCEL
        ? 'https://api.veloza.ru/lecar-bi/api/v1'
        : 'http://localhost:8080/api/v1')
    )
  }
  // Client-side: go through BFF proxy
  return '/api/backend'
}

type NextHeadersModule = {
  headers?: () => Promise<{ get: (name: string) => string | null }>
  cookies?: () => Promise<{ get: (name: string) => { value: string } | undefined }>
}

async function loadNextHeaders(): Promise<NextHeadersModule | null> {
  if (typeof window !== 'undefined') {
    return null
  }
  try {
    const dynamicImport = new Function('specifier', 'return import(specifier)')
    try {
      const mod = (await dynamicImport('next/headers.js')) as NextHeadersModule
      if (mod?.cookies || mod?.headers) {
        return {
          cookies: mod.cookies ?? (mod as any).default?.cookies,
          headers: mod.headers ?? (mod as any).default?.headers,
        }
      }
    } catch {}
    try {
      const mod = (await dynamicImport('next/headers')) as NextHeadersModule
      if (mod?.cookies || mod?.headers) {
        return {
          cookies: mod.cookies ?? (mod as any).default?.cookies,
          headers: mod.headers ?? (mod as any).default?.headers,
        }
      }
    } catch {}
    try {
      const cookiesMod = (await dynamicImport('next/dist/server/request/cookies.js')) as {
        cookies?: () => any
        default?: { cookies?: () => any }
      }
      const headersMod = (await dynamicImport('next/dist/server/request/headers.js')) as {
        headers?: () => any
        default?: { headers?: () => any }
      }
      const cookiesFn = cookiesMod?.cookies ?? cookiesMod?.default?.cookies
      const headersFn = headersMod?.headers ?? headersMod?.default?.headers
      if (cookiesFn || headersFn) {
        return {
          cookies: cookiesFn,
          headers: headersFn,
        }
      }
    } catch {}
    return null
  } catch {
    return null
  }
}

const requestIdMiddleware: Middleware = {
  async onRequest({ request }) {
    if (!request.headers.has('x-request-id')) {
      let requestId: string | null = null
      if (typeof window === 'undefined') {
        try {
          const nextHeaders = await loadNextHeaders()
          if (nextHeaders?.headers) {
            const headerList = await nextHeaders.headers()
            requestId = headerList.get('x-request-id')
          }
        } catch {
          // outside active server request context or in test environment
        }
      }
      request.headers.set('x-request-id', sanitizeOrGenerateRequestId(requestId))
    }
    return request
  },
}

const authMiddleware: Middleware = {
  async onRequest({ request }) {
    if (typeof window === 'undefined') {
      try {
        const nextHeaders = await loadNextHeaders()
        if (nextHeaders?.cookies) {
          const cookieStore = await nextHeaders.cookies()
          const sessionCookie = cookieStore.get(SESSION_COOKIE_NAME)?.value
          const session = parseSessionValue(sessionCookie)
          if (session?.token) {
            request.headers.set('Authorization', `Bearer ${session.token}`)
          }
        }
      } catch {
        // outside request context
      }
    }
    return request
  },
}

export const analyticsClient = createClient<paths>({
  baseUrl: getBaseUrl(),
  fetch: (request: Request) => {
    if (
      !request.signal &&
      typeof AbortSignal !== 'undefined' &&
      'timeout' in AbortSignal
    ) {
      return fetch(request, { signal: AbortSignal.timeout(DEFAULT_HTTP_TIMEOUT_MS) })
    }
    return fetch(request)
  },
})

analyticsClient.use(requestIdMiddleware)
analyticsClient.use(authMiddleware)
