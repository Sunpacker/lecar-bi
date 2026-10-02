import { NextResponse } from 'next/server'
import { authGateway } from '@/src/features/auth/api/auth-gateway'
import { createSession, setWorkspaceCookie } from '@/src/features/auth/model/session'
import { getBackendApiUrl } from '@/src/shared/config/backend-url'

export async function POST(request: Request) {
  try {
    const body = await request.json()
    const { email, password } = body

    if (
      !email ||
      typeof email !== 'string' ||
      !password ||
      typeof password !== 'string'
    ) {
      return NextResponse.json(
        { message: 'Укажите email и пароль', code: 'VALIDATION_ERROR' },
        { status: 422 },
      )
    }

    const { user, token } = await authGateway.login({
      email: email.trim(),
      password,
    })

    await createSession({ ...user, token })

    try {
      const wsRes = await fetch(`${getBackendApiUrl()}/workspaces/current`, {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
        },
        signal: AbortSignal.timeout(5000),
      })
      if (wsRes.ok) {
        const wsData = await wsRes.json()
        if (wsData?.workspace?.id) {
          await setWorkspaceCookie(wsData.workspace.id)
        }
      }
    } catch {
      // Non-blocking: workspace can also be resolved dynamically by routes
    }

    return NextResponse.json({ user })
  } catch (error: unknown) {
    const targetUrl = getBackendApiUrl()
    console.error('[auth/login] Login failed:', {
      targetUrl,
      error: error instanceof Error ? error.message : error,
      cause:
        error instanceof Error && (error as any).cause
          ? String((error as any).cause)
          : undefined,
    })

    let message = error instanceof Error ? error.message : 'Ошибка аутентификации'
    let status =
      typeof error === 'object' && error !== null && 'status' in error
        ? Number((error as { status: number }).status)
        : 401

    let code =
      typeof error === 'object' && error !== null && 'code' in error
        ? String((error as { code: string }).code)
        : 'INVALID_CREDENTIALS'

    if (
      (error instanceof TypeError && error.message.includes('fetch failed')) ||
      message.includes('fetch failed')
    ) {
      message = 'Сервис аналитики временно недоступен. Проверьте подключение к бэкенду.'
      code = 'BACKEND_UNAVAILABLE'
      status = 503
    }

    return NextResponse.json(
      {
        message,
        code,
        debug: {
          targetUrl,
          detail: error instanceof Error ? error.message : String(error),
          cause:
            error instanceof Error && (error as any).cause
              ? String((error as any).cause)
              : undefined,
        },
      },
      { status: status || 401 },
    )
  }
}
