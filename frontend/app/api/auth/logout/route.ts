import { NextResponse } from 'next/server'
import { deleteSession } from '@/src/features/auth/model/session'

export async function POST() {
  await deleteSession()
  return NextResponse.json({ success: true })
}
