import { proxySupportRequest } from '@/src/features/support/server/support-proxy'

export const dynamic = 'force-dynamic'

type SupportRouteContext = { params: Promise<{ path: string[] }> }

export async function GET(request: Request, context: SupportRouteContext) {
  return await proxyRequest(request, context)
}

export async function POST(request: Request, context: SupportRouteContext) {
  return await proxyRequest(request, context)
}

export async function DELETE(request: Request, context: SupportRouteContext) {
  return await proxyRequest(request, context)
}

async function proxyRequest(request: Request, context: SupportRouteContext) {
  const { path } = await context.params

  return await proxySupportRequest(request, path)
}
