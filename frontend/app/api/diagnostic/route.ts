import { NextResponse } from 'next/server'
import { getBackendApiUrl } from '@/src/shared/config/backend-url'

export async function GET() {
  const targetBackendUrl = getBackendApiUrl()

  let healthDirect: Record<string, any> = {}
  let healthLecar: Record<string, any> = {}

  // 1. Probe the resolved backend URL
  try {
    const startTime = Date.now()
    const res = await fetch(`${targetBackendUrl}/health`, {
      signal: AbortSignal.timeout(5000),
    })
    const elapsed = Date.now() - startTime
    const text = await res.text()
    let parsed: any = null
    try {
      parsed = JSON.parse(text)
    } catch {
      parsed = text
    }
    healthDirect = {
      ok: res.ok,
      status: res.status,
      elapsedMs: elapsed,
      response: parsed,
    }
  } catch (err: unknown) {
    healthDirect = {
      ok: false,
      error: err instanceof Error ? err.message : String(err),
      cause:
        err instanceof Error && (err as any).cause
          ? String((err as any).cause)
          : undefined,
    }
  }

  // 2. Also probe standard VPS path directly as fallback test
  try {
    const startTime = Date.now()
    const res = await fetch('https://api.veloza.ru/lecar-bi/api/v1/health', {
      signal: AbortSignal.timeout(5000),
    })
    const elapsed = Date.now() - startTime
    const text = await res.text()
    let parsed: any = null
    try {
      parsed = JSON.parse(text)
    } catch {
      parsed = text
    }
    healthLecar = {
      ok: res.ok,
      status: res.status,
      elapsedMs: elapsed,
      response: parsed,
    }
  } catch (err: unknown) {
    healthLecar = {
      ok: false,
      error: err instanceof Error ? err.message : String(err),
      cause:
        err instanceof Error && (err as any).cause
          ? String((err as any).cause)
          : undefined,
    }
  }

  return NextResponse.json({
    timestamp: new Date().toISOString(),
    environment: {
      isVercel: Boolean(process.env.VERCEL),
      nodeEnv: process.env.NODE_ENV,
      vercelUrl: process.env.VERCEL_URL ?? null,
      vercelEnv: process.env.VERCEL_ENV ?? null,
    },
    variables: {
      hasAnalyticsInternalUrl: Boolean(process.env.ANALYTICS_INTERNAL_URL),
      analyticsInternalUrlRaw: process.env.ANALYTICS_INTERNAL_URL ?? null,
      hasNextPublicAnalyticsApiUrl: Boolean(process.env.NEXT_PUBLIC_ANALYTICS_API_URL),
      nextPublicAnalyticsApiUrlRaw: process.env.NEXT_PUBLIC_ANALYTICS_API_URL ?? null,
      resolvedBackendUrl: targetBackendUrl,
    },
    probes: {
      resolvedTargetProbe: healthDirect,
      directVpsLecarProbe: healthLecar,
    },
  })
}
