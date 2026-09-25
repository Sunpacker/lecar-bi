import createClient, { type Middleware } from 'openapi-fetch'

import type { paths } from './generated/schema'
import { sanitizeOrGenerateRequestId } from '../observability/request-id'

const DEFAULT_HTTP_TIMEOUT_MS = 15000

function getBaseUrl(): string {
  if (typeof window === 'undefined') {
    // Server-side: go directly to backend
    return process.env.ANALYTICS_INTERNAL_URL || 'http://localhost:8080/api/v2'
  }
  // Client-side: go through BFF proxy
  return '/api/backend'
}

const requestIdMiddleware: Middleware = {
  async onRequest({ request }) {
    if (!request.headers.has('x-request-id')) {
      let requestId: string | null = null
      if (typeof window === 'undefined') {
        try {
          const { headers } = await import('next/headers')
          const headerList = await headers()
          requestId = headerList.get('x-request-id')
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
        const { getSession } = await import('@/src/features/auth/model/session')
        const session = await getSession()
        if (session?.token) {
          request.headers.set('Authorization', `Bearer ${session.token}`)
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
