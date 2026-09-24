import { getSession } from '@/src/features/auth/model/session'
import { env } from '@/src/shared/config/env'

const FORWARDED_REQUEST_HEADERS = [
  'Accept',
  'Content-Type',
  'Idempotency-Key',
  'Last-Event-ID',
  'X-Workspace-Id',
] as const

const FORWARDED_RESPONSE_HEADERS = ['Content-Type', 'Retry-After'] as const

export async function proxySupportRequest(
  request: Request,
  path: string[],
  stream = false,
): Promise<Response> {
  const session = await getSession()
  if (!session) return Response.json({ code: 'UNAUTHENTICATED' }, { status: 401 })

  const transportSecret = process.env.SUPPORT_BFF_SHARED_SECRET
  if (!transportSecret || transportSecret.length < 32) {
    return Response.json({ code: 'SUPPORT_TRANSPORT_UNAVAILABLE' }, { status: 503 })
  }

  const upstreamHeaders = forwardedHeaders(request.headers)
  upstreamHeaders.set('X-User-Id', session.userId)
  upstreamHeaders.set('X-Support-BFF-Key', transportSecret)
  const upstream = await fetch(upstreamUrl(request.url, path), {
    method: request.method,
    headers: upstreamHeaders,
    body: await requestBody(request),
    cache: 'no-store',
    redirect: 'manual',
    signal: request.signal,
  })

  return new Response(upstream.body, {
    status: upstream.status,
    headers: responseHeaders(upstream.headers, stream && upstream.ok),
  })
}

function forwardedHeaders(source: Headers): Headers {
  const result = new Headers()
  for (const name of FORWARDED_REQUEST_HEADERS) {
    const value = source.get(name)
    if (value) result.set(name, value)
  }

  return result
}

function upstreamUrl(requestUrl: string, path: string[]): string {
  const request = new URL(requestUrl)
  const encodedPath = path.map((segment) => encodeURIComponent(segment)).join('/')

  return `${env.analyticsApiUrl}/support/${encodedPath}${request.search}`
}

async function requestBody(request: Request): Promise<ArrayBuffer | undefined> {
  if (request.method === 'GET' || request.method === 'HEAD') return undefined
  const body = await request.arrayBuffer()

  return body.byteLength === 0 ? undefined : body
}

function responseHeaders(source: Headers, stream: boolean): Headers {
  const result = new Headers()
  for (const name of FORWARDED_RESPONSE_HEADERS) {
    const value = source.get(name)
    if (value) result.set(name, value)
  }
  result.set('Cache-Control', 'no-store')
  if (stream) {
    result.set('Content-Type', 'text/event-stream')
    result.set('X-Accel-Buffering', 'no')
  }

  return result
}
