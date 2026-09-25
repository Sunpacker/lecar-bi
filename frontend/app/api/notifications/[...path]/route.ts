import { NextRequest, NextResponse } from 'next/server'
import { getSession, getWorkspaceCookie } from '@/src/features/auth/model/session'

const NOTIFICATION_INTERNAL_URL =
  process.env.NOTIFICATION_INTERNAL_URL || 'http://localhost:8081/api/v1'
const NOTIFICATION_SHARED_SECRET =
  process.env.NOTIFICATION_SHARED_SECRET || 'test-notification-secret-key-12345'

const MUTATION_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

async function notificationProxyHandler(
  request: NextRequest,
  { params }: { params: Promise<{ path: string[] }> },
) {
  const session = await getSession()

  if (!session) {
    return NextResponse.json(
      { message: 'Сессия истекла', code: 'UNAUTHENTICATED' },
      { status: 401 },
    )
  }

  const workspaceId =
    request.headers.get('x-workspace-id') || (await getWorkspaceCookie())

  if (!workspaceId) {
    return NextResponse.json(
      { message: 'Рабочее пространство не выбрано', code: 'MISSING_WORKSPACE' },
      { status: 400 },
    )
  }

  const { path } = await params
  const targetPath = path.join('/')
  const url = new URL(`${NOTIFICATION_INTERNAL_URL}/${targetPath}`)

  request.nextUrl.searchParams.forEach((value, key) => {
    url.searchParams.set(key, value)
  })

  const headers = new Headers()
  headers.set('X-Server-Secret', NOTIFICATION_SHARED_SECRET)
  headers.set('X-User-Id', session.userId)
  headers.set('X-Workspace-Id', workspaceId)
  headers.set('Accept', 'application/json')

  const requestId = request.headers.get('x-request-id')
  if (requestId) {
    headers.set('x-request-id', requestId)
  }

  const contentType = request.headers.get('content-type')
  if (contentType) {
    headers.set('Content-Type', contentType)
  }

  try {
    const fetchOptions: RequestInit = {
      method: request.method,
      headers,
      signal: AbortSignal.timeout(10000),
    }

    if (MUTATION_METHODS.has(request.method)) {
      fetchOptions.body = await request.text()
    }

    const response = await fetch(url.toString(), fetchOptions)
    const responseBody = await response.text()

    return new NextResponse(responseBody, {
      status: response.status,
      headers: {
        'Content-Type': response.headers.get('Content-Type') || 'application/json',
      },
    })
  } catch {
    // Graceful fallback for GET endpoints so UI doesn't crash if notification service is unreachable
    if (request.method === 'GET') {
      if (targetPath === 'notifications/unread-count') {
        return NextResponse.json({ unread_count: 0 }, { status: 200 })
      }
      if (targetPath === 'notifications') {
        return NextResponse.json(
          { items: [], total: 0, page: 1, per_page: 20, unread_count: 0 },
          { status: 200 },
        )
      }
      if (targetPath === 'notification-preferences') {
        return NextResponse.json(
          { preferences: { info: true, warning: true, critical: true } },
          { status: 200 },
        )
      }
    }

    return NextResponse.json(
      {
        message: 'Сервис уведомлений временно недоступен',
        code: 'NOTIFICATION_UNAVAILABLE',
      },
      { status: 503 },
    )
  }
}

export const GET = notificationProxyHandler
export const POST = notificationProxyHandler
export const PUT = notificationProxyHandler
export const PATCH = notificationProxyHandler
export const DELETE = notificationProxyHandler
