import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type Workspace = components['schemas']['WorkspaceResponse']
export type CurrentWorkspace = components['schemas']['CurrentWorkspaceResponse']
export type User = components['schemas']['UserResponse']
export type WorkspaceRole = components['schemas']['WorkspaceRole']
export type WorkspaceCapability = components['schemas']['WorkspaceCapability']
export type WorkspaceMember = components['schemas']['WorkspaceMemberResponse']

export class WorkspaceApiError extends Error {
  constructor(
    message: string,
    public readonly code?: string,
    public readonly status?: number,
  ) {
    super(message)
    this.name = 'WorkspaceApiError'
  }
}

export const workspaceGateway = {
  async listWorkspaces(userId: string): Promise<Workspace[]> {
    const { data, error } = await analyticsClient.GET('/workspaces', {
      headers: { 'X-User-Id': userId },
    })

    if (error || !data) {
      throw new Error(error?.message ?? 'Failed to load accessible workspaces')
    }

    return data.items
  },

  async getCurrentWorkspace(
    userId: string,
    requestedWorkspaceId?: string,
  ): Promise<CurrentWorkspace> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (requestedWorkspaceId) {
      headers['X-Workspace-Id'] = requestedWorkspaceId
    }

    const { data, error } = await analyticsClient.GET('/workspaces/current', {
      headers,
    })

    if (error || !data) {
      throw new Error(error?.message ?? 'Failed to load current workspace')
    }

    return data
  },

  async getWorkspaceById(userId: string, workspaceId: string): Promise<Workspace> {
    const { data, error } = await analyticsClient.GET('/workspaces/{id}', {
      params: { path: { id: workspaceId } },
      headers: { 'X-User-Id': userId },
    })

    if (error || !data) {
      throw new Error(error?.message ?? `Failed to load workspace ${workspaceId}`)
    }

    return data
  },

  async listWorkspaceMembers(
    userId: string,
    workspaceId: string,
  ): Promise<WorkspaceMember[]> {
    const { data, error, response } = await analyticsClient.GET(
      '/workspaces/{workspaceId}/members',
      {
        params: { path: { workspaceId } },
        headers: {
          'X-User-Id': userId,
          'X-Workspace-Id': workspaceId,
        },
      },
    )

    if (error || !data) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new WorkspaceApiError(
        errPayload?.message ?? `Failed to load members for workspace ${workspaceId}`,
        errPayload?.code,
        response?.status,
      )
    }

    return data.items
  },

  async changeMemberRole(
    userId: string,
    workspaceId: string,
    memberUserId: string,
    role: WorkspaceRole,
  ): Promise<WorkspaceMember> {
    const { data, error, response } = await analyticsClient.PATCH(
      '/workspaces/{workspaceId}/members/{userId}/role',
      {
        params: { path: { workspaceId, userId: memberUserId } },
        headers: {
          'X-User-Id': userId,
          'X-Workspace-Id': workspaceId,
        },
        body: { role },
      },
    )

    if (error || !data) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new WorkspaceApiError(
        errPayload?.message ?? 'Failed to update member role',
        errPayload?.code,
        response?.status,
      )
    }

    return data.member
  },
}
