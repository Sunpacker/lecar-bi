import { describe, expect, it, vi } from 'vitest'
import { workspaceGateway } from './workspace-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    GET: vi.fn(),
    PATCH: vi.fn(),
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

  it('loads workspace members', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: {
        items: [
          {
            user: { id: 'u-1', email: 'owner@autobi.local', name: 'Owner' },
            role: 'owner',
          },
          {
            user: { id: 'u-2', email: 'viewer@autobi.local', name: 'Viewer' },
            role: 'viewer',
          },
        ],
      },
      error: undefined,
      response: new Response(),
    } as never)

    const members = await workspaceGateway.listWorkspaceMembers('u-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/workspaces/{workspaceId}/members',
      {
        params: { path: { workspaceId: 'ws-1' } },
        headers: {
          'X-User-Id': 'u-1',
          'X-Workspace-Id': 'ws-1',
        },
      },
    )
    expect(members).toHaveLength(2)
    expect(members[0].role).toBe('owner')
  })

  it('changes member role', async () => {
    vi.mocked(analyticsClient.PATCH).mockResolvedValueOnce({
      data: {
        member: {
          user: { id: 'u-2', email: 'viewer@autobi.local', name: 'Viewer' },
          role: 'member',
        },
      },
      error: undefined,
      response: new Response(),
    } as never)

    const updated = await workspaceGateway.changeMemberRole(
      'u-1',
      'ws-1',
      'u-2',
      'member',
    )

    expect(analyticsClient.PATCH).toHaveBeenCalledWith(
      '/workspaces/{workspaceId}/members/{userId}/role',
      {
        params: { path: { workspaceId: 'ws-1', userId: 'u-2' } },
        headers: {
          'X-User-Id': 'u-1',
          'X-Workspace-Id': 'ws-1',
        },
        body: { role: 'member' },
      },
    )
    expect(updated.role).toBe('member')
  })

  it('throws WorkspaceApiError on role change failure (e.g. last owner 409)', async () => {
    vi.mocked(analyticsClient.PATCH).mockResolvedValueOnce({
      data: undefined,
      error: { message: 'Cannot demote the sole owner', code: 'LAST_WORKSPACE_OWNER' },
      response: new Response(null, { status: 409 }),
    } as never)

    await expect(
      workspaceGateway.changeMemberRole('u-1', 'ws-1', 'u-1', 'viewer'),
    ).rejects.toMatchObject({
      name: 'WorkspaceApiError',
      message: 'Cannot demote the sole owner',
      code: 'LAST_WORKSPACE_OWNER',
      status: 409,
    })
  })
})
