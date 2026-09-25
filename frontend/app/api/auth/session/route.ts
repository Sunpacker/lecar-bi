import { NextResponse } from 'next/server'
import {
  getSession,
  createSession,
  setWorkspaceCookie,
} from '@/src/features/auth/model/session'

export async function GET() {
  const session = await getSession()
  return NextResponse.json({ session })
}

export async function POST(request: Request) {
  try {
    const body = await request.json()
    const { user, token, workspaceId } = body

    if (!user || !token) {
      return NextResponse.json({ message: 'Invalid session payload' }, { status: 400 })
    }

    await createSession({
      id: user.id,
      email: user.email,
      name: user.name,
      token,
    })

    if (workspaceId) {
      await setWorkspaceCookie(workspaceId)
    }

    return NextResponse.json({ success: true, user })
  } catch {
    return NextResponse.json({ message: 'Failed to create session' }, { status: 500 })
  }
}
