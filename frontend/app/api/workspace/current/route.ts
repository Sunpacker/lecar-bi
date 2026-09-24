import { NextResponse } from 'next/server'
import { z } from 'zod'

import { getSession } from '@/src/features/auth/model/session'
import { SELECTED_WORKSPACE_COOKIE } from '@/src/features/workspace/model/selected-workspace'
import { workspaceGateway } from '@/src/features/workspace/api/workspace-gateway'

const requestSchema = z.object({ workspace_id: z.string().min(1).max(64) })

export async function POST(request: Request) {
  const session = await getSession()
  if (!session) return NextResponse.json({ code: 'UNAUTHENTICATED' }, { status: 401 })

  const parsed = requestSchema.safeParse(await request.json())
  if (!parsed.success)
    return NextResponse.json({ code: 'VALIDATION_ERROR' }, { status: 422 })

  await workspaceGateway.getWorkspaceById(session.userId, parsed.data.workspace_id)
  const response = NextResponse.json({ workspace_id: parsed.data.workspace_id })
  response.cookies.set(SELECTED_WORKSPACE_COOKIE, parsed.data.workspace_id, {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    path: '/',
    maxAge: 60 * 60 * 24 * 30,
  })

  return response
}
