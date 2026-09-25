import crypto from 'node:crypto'
import { cookies } from 'next/headers'

export const SESSION_COOKIE_NAME = 'autobi_session'
export const SESSION_DURATION_SECONDS = 60 * 60 * 24 * 7 // 7 days
const DEFAULT_SECRET = 'autobi-dev-session-secret-key-change-in-production'

function getSessionSecret(): string {
  return process.env.SESSION_SECRET || DEFAULT_SECRET
}

function computeSignature(payloadBase64: string, secret: string): string {
  return crypto.createHmac('sha256', secret).update(payloadBase64).digest('base64url')
}

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

  const payloadBase64 = Buffer.from(JSON.stringify(payload)).toString('base64url')
  const signature = computeSignature(payloadBase64, getSessionSecret())
  return `${payloadBase64}.${signature}`
}

export function parseSessionValue(cookieValue: string | undefined): SessionUser | null {
  if (!cookieValue) {
    return null
  }

  try {
    let payloadBase64: string
    if (cookieValue.includes('.')) {
      const parts = cookieValue.split('.')
      if (parts.length !== 2) {
        return null
      }
      payloadBase64 = parts[0]
      const expectedSignature = computeSignature(payloadBase64, getSessionSecret())
      const providedSignature = parts[1]

      if (expectedSignature.length !== providedSignature.length) {
        return null
      }
      const expectedBuffer = Buffer.from(expectedSignature)
      const providedBuffer = Buffer.from(providedSignature)
      if (!crypto.timingSafeEqual(expectedBuffer, providedBuffer)) {
        return null
      }
    } else {
      // Legacy unsigned format support in non-production environments only
      if (process.env.NODE_ENV === 'production') {
        return null
      }
      payloadBase64 = cookieValue
    }

    const decoded = Buffer.from(payloadBase64, 'base64url').toString('utf-8')
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
