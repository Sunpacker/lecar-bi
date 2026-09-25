import { sanitizeOrGenerateRequestId } from '../../../src/shared/observability/request-id'
import { logInfo } from '../../../src/shared/lib/logger'

export function GET(request?: Request): Response {
  const requestId = sanitizeOrGenerateRequestId(request?.headers.get('x-request-id'))
  const url = request ? new URL(request.url) : null
  const probe = url?.searchParams.get('probe')

  logInfo('Health check probe requested', {
    requestId,
    operation: probe ? `GET /api/health?probe=${probe}` : 'GET /api/health',
  })

  const body: Record<string, string> = { status: 'ok', service: 'web' }
  if (probe) {
    body.probe = probe
  }

  return Response.json(body, {
    headers: {
      'x-request-id': requestId,
    },
  })
}
