import { proxySupportRequest } from '@/src/features/support/server/support-proxy'

export const dynamic = 'force-dynamic'

export async function GET(
  request: Request,
  context: { params: Promise<{ id: string }> },
) {
  const { id } = await context.params

  return await proxySupportRequest(request, ['generations', id, 'events'], true)
}
