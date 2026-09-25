import { sanitizeOrGenerateRequestId } from '../../../src/shared/observability/request-id'
import { logInfo } from '../../../src/shared/lib/logger'

export function GET(request?: Request): Response {
  const requestId = sanitizeOrGenerateRequestId(request?.headers.get('x-request-id'))

  logInfo('Health check probe requested', {
    requestId,
    operation: 'GET /api/health',
  })

  return Response.json(
    { status: 'ok', service: 'web' },
    {
      headers: {
        'x-request-id': requestId,
      },
    },
  )
}
