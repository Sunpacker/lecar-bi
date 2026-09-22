import { describe, expect, it, vi } from 'vitest'
import { workspaceGateway } from './workspace-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    GET: vi.fn(),
  },
}))

describe('workspaceGateway', () => {
  it('loads accessible workspaces for user', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: {
        items: [
          {
            id: 'ws-1',
            name: 'AutoParts Retail',
            slug: 'autoparts-retail',
            role: 'owner',
          },
        ],
      },
      error: undefined,
      response: new Response(),
    } as never)

    const workspaces = await workspaceGateway.listWorkspaces('user-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith('/workspaces', {
      headers: { 'X-User-Id': 'user-1' },
    })
    expect(workspaces).toEqual([
      { id: 'ws-1', name: 'AutoParts Retail', slug: 'autoparts-retail', role: 'owner' },
    ])
  })

  it('throws error when cross-workspace access is forbidden (403)', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: undefined,
      error: { message: 'Forbidden', code: 'FORBIDDEN' },
      response: new Response(null, { status: 403 }),
    } as never)

    await expect(workspaceGateway.getWorkspaceById('user-1', 'ws-2')).rejects.toThrow(
      'Forbidden',
    )
  })

  it('loads current workspace context', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: {
        user: { id: 'user-1', email: 'elena@autobi.internal', name: 'Elena Rostova' },
        workspace: {
          id: 'ws-1',
          name: 'AutoParts Retail',
          slug: 'autoparts-retail',
          role: 'owner',
        },
      },
      error: undefined,
      response: new Response(),
    } as never)

    const current = await workspaceGateway.getCurrentWorkspace('user-1')

    expect(current.user.id).toBe('user-1')
    expect(current.workspace.id).toBe('ws-1')
  })
})
