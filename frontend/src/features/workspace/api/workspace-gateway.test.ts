import { describe, expect, it, vi } from 'vitest'
import { workspaceGateway } from './workspace-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    GET: vi.fn(),
    PATCH: vi.fn(),
    POST: vi.fn(),
    DELETE: vi.fn(),
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

    const workspaces = await workspaceGateway.listWorkspaces()

    expect(analyticsClient.GET).toHaveBeenCalledWith('/workspaces')
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

    await expect(workspaceGateway.getWorkspaceById('ws-2')).rejects.toThrow('Forbidden')
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

    const current = await workspaceGateway.getCurrentWorkspace()

    expect(current.user.id).toBe('user-1')
    expect(current.workspace.id).toBe('ws-1')
  })

  it('renames workspace', async () => {
    vi.mocked(analyticsClient.PATCH).mockResolvedValueOnce({
      data: {
        id: 'ws-1',
        name: 'New Name',
        slug: 'autoparts-retail',
        role: 'owner',
      },
      error: undefined,
      response: new Response(),
    } as never)

    const updated = await workspaceGateway.renameWorkspace('ws-1', 'New Name')

    expect(analyticsClient.PATCH).toHaveBeenCalledWith('/workspaces/{id}', {
      params: { path: { id: 'ws-1' } },
      headers: { 'X-Workspace-Id': 'ws-1' },
      body: { name: 'New Name' },
    })
    expect(updated.name).toBe('New Name')
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

    const members = await workspaceGateway.listWorkspaceMembers('ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/workspaces/{workspaceId}/members',
      {
        params: { path: { workspaceId: 'ws-1' } },
        headers: {
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

    const updated = await workspaceGateway.changeMemberRole('ws-1', 'u-2', 'member')

    expect(analyticsClient.PATCH).toHaveBeenCalledWith(
      '/workspaces/{workspaceId}/members/{userId}/role',
      {
        params: { path: { workspaceId: 'ws-1', userId: 'u-2' } },
        headers: {
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
      workspaceGateway.changeMemberRole('ws-1', 'u-1', 'viewer'),
    ).rejects.toMatchObject({
      name: 'WorkspaceApiError',
      message: 'Cannot demote the sole owner',
      code: 'LAST_WORKSPACE_OWNER',
      status: 409,
    })
  })

  it('lists invitations', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: {
        items: [
          {
            id: 'inv-1',
            workspace_id: 'ws-1',
            email: 'invitee@test.local',
            role: 'member',
            status: 'pending',
            expires_at: '2026-10-02T10:00:00Z',
            created_at: '2026-09-25T10:00:00Z',
          },
        ],
      },
      error: undefined,
      response: new Response(),
    } as never)

    const invitations = await workspaceGateway.listInvitations('ws-1')
    expect(invitations).toHaveLength(1)
    expect(invitations[0].email).toBe('invitee@test.local')
  })

  it('creates invitation', async () => {
    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: {
        id: 'inv-2',
        workspace_id: 'ws-1',
        email: 'new@test.local',
        role: 'viewer',
        status: 'pending',
        expires_at: '2026-10-02T10:00:00Z',
        created_at: '2026-09-25T10:00:00Z',
      },
      error: undefined,
      response: new Response(),
    } as never)

    const inv = await workspaceGateway.createInvitation(
      'ws-1',
      'new@test.local',
      'viewer',
    )
    expect(inv.email).toBe('new@test.local')
  })

  it('resends invitation', async () => {
    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: {
        id: 'inv-1',
        workspace_id: 'ws-1',
        email: 'invitee@test.local',
        role: 'member',
        status: 'pending',
        expires_at: '2026-10-02T12:00:00Z',
        created_at: '2026-09-25T10:00:00Z',
      },
      error: undefined,
      response: new Response(),
    } as never)

    const inv = await workspaceGateway.resendInvitation('ws-1', 'inv-1')
    expect(inv.id).toBe('inv-1')
  })

  it('cancels invitation', async () => {
    vi.mocked(analyticsClient.DELETE).mockResolvedValueOnce({
      data: undefined,
      error: undefined,
      response: new Response(null, { status: 204 }),
    } as never)

    await expect(
      workspaceGateway.cancelInvitation('ws-1', 'inv-1'),
    ).resolves.toBeUndefined()
  })

  it('gets invitation details and accepts invitation', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: {
        email: 'invitee@test.local',
        workspace_name: 'Test WS',
        role: 'member',
        is_expired: false,
        is_existing_user: true,
      },
      error: undefined,
      response: new Response(),
    } as never)

    const details = await workspaceGateway.getInvitationDetails('token123')
    expect(details.workspace_name).toBe('Test WS')

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: {
        user: { id: 'u-5', email: 'invitee@test.local', name: 'Invitee' },
        workspace_id: 'ws-1',
        token: 'new-sanctum-token',
      },
      error: undefined,
      response: new Response(),
    } as never)

    const accepted = await workspaceGateway.acceptInvitation('token123')
    expect(accepted.token).toBe('new-sanctum-token')
  })
})
