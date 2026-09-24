import { cookies } from 'next/headers'

export const SESSION_COOKIE_NAME = 'autobi_session'
export const SESSION_DURATION_SECONDS = 60 * 60 * 24 * 7 // 7 days

export interface SessionUser {
  userId: string
  email: string
  name: string
  expiresAt: number
}

export async function serializeSession(user: {
  id: string
  email: string
  name: string
}): Promise<string> {
  const payload: SessionUser = {
    userId: user.id,
    email: user.email,
    name: user.name,
    expiresAt: Date.now() + SESSION_DURATION_SECONDS * 1000,
  }

  const encodedPayload = encodeBase64Url(
    new TextEncoder().encode(JSON.stringify(payload)),
  )
  const signature = await sign(encodedPayload)

  return `${encodedPayload}.${encodeBase64Url(signature)}`
}

export async function parseSessionValue(
  cookieValue: string | undefined,
): Promise<SessionUser | null> {
  if (!cookieValue) return null

  try {
    const [encodedPayload, encodedSignature, extra] = cookieValue.split('.')
    if (!encodedPayload || !encodedSignature || extra) return null
    const valid = await verify(encodedPayload, decodeBase64Url(encodedSignature))
    if (!valid) return null

    const decoded = new TextDecoder().decode(decodeBase64Url(encodedPayload))
    const session = JSON.parse(decoded) as SessionUser

    if (!session.userId || !session.expiresAt || session.expiresAt < Date.now())
      return null

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
  const encoded = await serializeSession(user)

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
  return await parseSessionValue(cookie?.value)
}

export async function deleteSession(): Promise<void> {
  const cookieStore = await cookies()
  cookieStore.delete(SESSION_COOKIE_NAME)
}

async function sign(value: string): Promise<Uint8Array> {
  const result = await crypto.subtle.sign(
    'HMAC',
    await signingKey(),
    new TextEncoder().encode(value),
  )

  return new Uint8Array(result)
}

async function verify(value: string, signature: Uint8Array): Promise<boolean> {
  return await crypto.subtle.verify(
    'HMAC',
    await signingKey(),
    signature,
    new TextEncoder().encode(value),
  )
}

async function signingKey(): Promise<CryptoKey> {
  const secret = process.env.SESSION_SECRET
  if (!secret || secret.length < 32)
    throw new Error('SESSION_SECRET must contain at least 32 characters')

  return await crypto.subtle.importKey(
    'raw',
    new TextEncoder().encode(secret),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign', 'verify'],
  )
}

function encodeBase64Url(bytes: Uint8Array): string {
  let binary = ''
  for (const byte of bytes) binary += String.fromCharCode(byte)

  return btoa(binary).replaceAll('+', '-').replaceAll('/', '_').replace(/=+$/u, '')
}

function decodeBase64Url(value: string): Uint8Array {
  const normalized = value.replaceAll('-', '+').replaceAll('_', '/')
  const padded = normalized.padEnd(Math.ceil(normalized.length / 4) * 4, '=')
  const binary = atob(padded)

  return Uint8Array.from(binary, (character) => character.charCodeAt(0))
}
