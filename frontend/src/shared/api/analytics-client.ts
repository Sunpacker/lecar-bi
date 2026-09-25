import createClient, { type Middleware } from 'openapi-fetch'

import type { paths } from './generated/schema'
import { env } from '../config/env'
import { sanitizeOrGenerateRequestId } from '../observability/request-id'

const DEFAULT_HTTP_TIMEOUT_MS = 15000

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

export const analyticsClient = createClient<paths>({
  baseUrl: env.analyticsApiUrl,
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
