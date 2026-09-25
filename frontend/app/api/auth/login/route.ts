import { NextResponse } from 'next/server'
import { authGateway } from '@/src/features/auth/api/auth-gateway'
import { createSession } from '@/src/features/auth/model/session'

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

    return NextResponse.json({ user })
  } catch (error: unknown) {
    const message = error instanceof Error ? error.message : 'Ошибка аутентификации'
    const status =
      typeof error === 'object' && error !== null && 'status' in error
        ? Number((error as { status: number }).status)
        : 401

    const code =
      typeof error === 'object' && error !== null && 'code' in error
        ? String((error as { code: string }).code)
        : 'INVALID_CREDENTIALS'

    return NextResponse.json({ message, code }, { status: status || 401 })
  }
}
