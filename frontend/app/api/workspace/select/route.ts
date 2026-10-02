import { NextResponse } from 'next/server'
import { getSession, setWorkspaceCookie } from '@/src/features/auth/model/session'

export async function POST(request: Request) {
  const session = await getSession()
  if (!session) {
    return NextResponse.json(
      { message: 'Сессия истекла', code: 'UNAUTHENTICATED' },
      { status: 401 },
    )
  }

  try {
    const body = await request.json()
    const { workspaceId } = body

    if (!workspaceId || typeof workspaceId !== 'string') {
      return NextResponse.json(
        { message: 'Некорректный ID рабочего пространства', code: 'INVALID_WORKSPACE' },
        { status: 400 },
      )
    }

    await setWorkspaceCookie(workspaceId)
    return NextResponse.json({ success: true, workspaceId })
  } catch {
    return NextResponse.json(
      { message: 'Ошибка при сохранении рабочего пространства', code: 'ERROR' },
      { status: 500 },
    )
  }
}
