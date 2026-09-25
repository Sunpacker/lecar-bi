import { NextRequest, NextResponse } from 'next/server'
import { getSession } from '@/src/features/auth/model/session'

const ANALYTICS_INTERNAL_URL =
  process.env.ANALYTICS_INTERNAL_URL || 'http://localhost:8080/api/v2'

// Список мутационных методов для CSRF
const MUTATION_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

async function proxyHandler(
  request: NextRequest,
  { params }: { params: Promise<{ path: string[] }> },
) {
  const session = await getSession()

  if (!session) {
    return NextResponse.json(
      { message: 'Сессия истекла', code: 'SESSION_EXPIRED' },
      { status: 401 },
    )
  }

  const { path } = await params
  const targetPath = path.join('/')
  const url = new URL(`${ANALYTICS_INTERNAL_URL}/${targetPath}`)

  // Forward query params
  request.nextUrl.searchParams.forEach((value, key) => {
    url.searchParams.set(key, value)
  })

  const headers = new Headers()
  headers.set('Authorization', `Bearer ${session.token}`)
  headers.set('Accept', 'application/json')

  // Forward request-id
  const requestId = request.headers.get('x-request-id')
  if (requestId) {
    headers.set('x-request-id', requestId)
  }

  // Forward workspace header
  const workspaceId = request.headers.get('x-workspace-id')
  if (workspaceId) {
    headers.set('X-Workspace-Id', workspaceId)
  }

  // Forward content-type for mutations
  const contentType = request.headers.get('content-type')
  if (contentType) {
    headers.set('Content-Type', contentType)
  }

  try {
    const fetchOptions: RequestInit = {
      method: request.method,
      headers,
      signal: AbortSignal.timeout(15000),
    }

    // Forward body for mutations
    if (MUTATION_METHODS.has(request.method)) {
      if (contentType?.includes('multipart/form-data')) {
        // For file uploads, forward the raw body
        headers.delete('Content-Type') // Let fetch set boundary
        fetchOptions.body = await request.blob()
      } else {
        fetchOptions.body = await request.text()
      }
    }

    const response = await fetch(url.toString(), fetchOptions)

    const responseBody = await response.text()

    return new NextResponse(responseBody, {
      status: response.status,
      headers: {
        'Content-Type': response.headers.get('Content-Type') || 'application/json',
      },
    })
  } catch (error) {
    if (error instanceof Error && error.name === 'TimeoutError') {
      return NextResponse.json(
        { message: 'Сервис недоступен', code: 'BACKEND_TIMEOUT' },
        { status: 504 },
      )
    }
    return NextResponse.json(
      { message: 'Сервис недоступен', code: 'BACKEND_UNAVAILABLE' },
      { status: 502 },
    )
  }
}

export const GET = proxyHandler
export const POST = proxyHandler
export const PUT = proxyHandler
export const PATCH = proxyHandler
export const DELETE = proxyHandler
