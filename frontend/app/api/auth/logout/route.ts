import { NextResponse } from 'next/server'
import {
  deleteSession,
  getSession,
  deleteWorkspaceCookie,
} from '@/src/features/auth/model/session'
import { getBackendApiUrl } from '@/src/shared/config/backend-url'

export async function POST() {
  try {
    const session = await getSession()
    if (session?.token) {
      await fetch(`${getBackendApiUrl()}/auth/logout`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${session.token}`,
        },
        signal: AbortSignal.timeout(5000),
      }).catch(() => {
        // Ignore fetch errors to ensure cookies are still deleted
      })
    }
  } catch (err) {
    // Ignore unexpected errors
  } finally {
    await deleteSession()
    await deleteWorkspaceCookie()
  }

  return NextResponse.json({ success: true })
}
