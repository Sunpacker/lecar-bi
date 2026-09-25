import { cookies } from 'next/headers'
import {
  SESSION_COOKIE_NAME,
  WORKSPACE_COOKIE_NAME,
  SESSION_DURATION_SECONDS,
  serializeSession,
  parseSessionValue,
  type SessionUser,
} from './session-token'

export * from './session-token'

export async function createSession(user: {
  id: string
  email: string
  name: string
  token: string
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

export async function setWorkspaceCookie(workspaceId: string): Promise<void> {
  const cookieStore = await cookies()
  cookieStore.set(WORKSPACE_COOKIE_NAME, workspaceId, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    path: '/',
    maxAge: SESSION_DURATION_SECONDS,
  })
}

export async function getWorkspaceCookie(): Promise<string | null> {
  const cookieStore = await cookies()
  const cookie = cookieStore.get(WORKSPACE_COOKIE_NAME)
  return cookie?.value || null
}

export async function deleteWorkspaceCookie(): Promise<void> {
  const cookieStore = await cookies()
  cookieStore.delete(WORKSPACE_COOKIE_NAME)
}
