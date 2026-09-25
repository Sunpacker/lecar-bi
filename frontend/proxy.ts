import { NextResponse, type NextRequest } from 'next/server'
import {
  SESSION_COOKIE_NAME,
  parseSessionValue,
} from './src/features/auth/model/session-token'
import { sanitizeOrGenerateRequestId } from './src/shared/observability/request-id'

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl
  const requestId = sanitizeOrGenerateRequestId(request.headers.get('x-request-id'))

  const requestHeaders = new Headers(request.headers)
  requestHeaders.set('x-request-id', requestId)

  const sessionCookie = request.cookies.get(SESSION_COOKIE_NAME)?.value
  const session = parseSessionValue(sessionCookie)
  const isAuthenticated = session !== null

  if (pathname === '/login') {
    if (isAuthenticated) {
      const redirectRes = NextResponse.redirect(new URL('/', request.url))
      redirectRes.headers.set('x-request-id', requestId)
      return redirectRes
    }
    const nextRes = NextResponse.next({ request: { headers: requestHeaders } })
    nextRes.headers.set('x-request-id', requestId)
    return nextRes
  }

  if (
    pathname.startsWith('/api/auth') ||
    pathname.startsWith('/api/health') ||
    pathname.startsWith('/api/backend') ||
    pathname.startsWith('/api/notifications') ||
    pathname.startsWith('/invite') ||
    pathname.startsWith('/_next') ||
    pathname.includes('.')
  ) {
    const nextRes = NextResponse.next({ request: { headers: requestHeaders } })
    nextRes.headers.set('x-request-id', requestId)
    return nextRes
  }

  // All other pages require authentication
  if (!isAuthenticated) {
    const loginUrl = new URL('/login', request.url)
    const redirectRes = NextResponse.redirect(loginUrl)
    redirectRes.headers.set('x-request-id', requestId)
    return redirectRes
  }

  const nextRes = NextResponse.next({ request: { headers: requestHeaders } })
  nextRes.headers.set('x-request-id', requestId)
  return nextRes
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
}
