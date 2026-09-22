import { cookies } from 'next/headers'

export const SESSION_COOKIE_NAME = 'autobi_session'
export const SESSION_DURATION_SECONDS = 60 * 60 * 24 * 7 // 7 days

export interface SessionUser {
  userId: string
  email: string
  name: string
  expiresAt: number
}

export function serializeSession(user: {
  id: string
  email: string
  name: string
}): string {
  const payload: SessionUser = {
    userId: user.id,
    email: user.email,
    name: user.name,
    expiresAt: Date.now() + SESSION_DURATION_SECONDS * 1000,
  }

  return Buffer.from(JSON.stringify(payload)).toString('base64url')
}

export function parseSessionValue(cookieValue: string | undefined): SessionUser | null {
  if (!cookieValue) {
    return null
  }

  try {
    const decoded = Buffer.from(cookieValue, 'base64url').toString('utf-8')
    const session = JSON.parse(decoded) as SessionUser

    if (!session.userId || !session.expiresAt || session.expiresAt < Date.now()) {
      return null
    }

    return session
  } catch {
    return null
  }
}

export async function createSession(user: {
  id: string
  email: string
  name: string
}): Promise<void> {
  const cookieStore = await cookies()
  const encoded = serializeSession(user)

  cookieStore.set(SESSION_COOKIE_NAME, encoded, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    path: '/',
    maxAge: SESSION_DURATION_SECONDS,
  })
}

export async function getSession(): Promise<SessionUser | null> {
  const cookieStore = await cookies()
  const cookie = cookieStore.get(SESSION_COOKIE_NAME)
  return parseSessionValue(cookie?.value)
}

export async function deleteSession(): Promise<void> {
  const cookieStore = await cookies()
  cookieStore.delete(SESSION_COOKIE_NAME)
}
