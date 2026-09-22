import { NextResponse } from 'next/server'
import { getSession } from '@/src/features/auth/model/session'

export async function GET() {
  const session = await getSession()
  return NextResponse.json({ session })
}
