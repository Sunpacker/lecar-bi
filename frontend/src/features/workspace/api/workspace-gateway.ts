import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type Workspace = components['schemas']['WorkspaceResponse']
export type CurrentWorkspace = components['schemas']['CurrentWorkspaceResponse']
export type User = components['schemas']['UserResponse']
export type WorkspaceRole = components['schemas']['WorkspaceRole']
export type WorkspaceCapability = components['schemas']['WorkspaceCapability']
export type WorkspaceMember = components['schemas']['WorkspaceMemberResponse']
export type Invitation = components['schemas']['InvitationResponse']
export type InvitationStatus = components['schemas']['InvitationStatus']
export type InvitationPublic = components['schemas']['InvitationPublicResponse']
export type AcceptInvitationResult = components['schemas']['AcceptInvitationResponse']

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
  async listWorkspaces(): Promise<Workspace[]> {
    const { data, error } = await analyticsClient.GET('/workspaces')

    if (error || !data) {
      throw new Error(error?.message ?? 'Failed to load accessible workspaces')
    }

    return data.items
  },

  async getCurrentWorkspace(requestedWorkspaceId?: string): Promise<CurrentWorkspace> {
    const headers: Record<string, string> = {}
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

  async getWorkspaceById(workspaceId: string): Promise<Workspace> {
    const { data, error } = await analyticsClient.GET('/workspaces/{id}', {
      params: { path: { id: workspaceId } },
    })

    if (error || !data) {
      throw new Error(error?.message ?? `Failed to load workspace ${workspaceId}`)
    }

    return data
  },

  async renameWorkspace(workspaceId: string, name: string): Promise<Workspace> {
    const { data, error, response } = await analyticsClient.PATCH('/workspaces/{id}', {
      params: { path: { id: workspaceId } },
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      body: { name },
    })

    if (error || !data) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new WorkspaceApiError(
        errPayload?.message ?? 'Failed to rename workspace',
        errPayload?.code,
        response?.status,
      )
    }

    return data
  },

  async listWorkspaceMembers(workspaceId: string): Promise<WorkspaceMember[]> {
    const { data, error, response } = await analyticsClient.GET(
      '/workspaces/{workspaceId}/members',
      {
        params: { path: { workspaceId } },
        headers: {
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
    workspaceId: string,
    memberUserId: string,
    role: WorkspaceRole,
  ): Promise<WorkspaceMember> {
    const { data, error, response } = await analyticsClient.PATCH(
      '/workspaces/{workspaceId}/members/{userId}/role',
      {
        params: { path: { workspaceId, userId: memberUserId } },
        headers: {
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

  async listInvitations(workspaceId: string): Promise<Invitation[]> {
    const { data, error, response } = await analyticsClient.GET(
      '/workspaces/{workspaceId}/invitations',
      {
        params: { path: { workspaceId } },
        headers: {
          'X-Workspace-Id': workspaceId,
        },
      },
    )

    if (error || !data) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new WorkspaceApiError(
        errPayload?.message ?? 'Failed to load invitations',
        errPayload?.code,
        response?.status,
      )
    }

    return data.items
  },

  async createInvitation(
    workspaceId: string,
    email: string,
    role: 'member' | 'viewer',
  ): Promise<Invitation> {
    const { data, error, response } = await analyticsClient.POST(
      '/workspaces/{workspaceId}/invitations',
      {
        params: { path: { workspaceId } },
        headers: {
          'X-Workspace-Id': workspaceId,
        },
        body: { email, role },
      },
    )

    if (error || !data) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new WorkspaceApiError(
        errPayload?.message ?? 'Failed to create invitation',
        errPayload?.code,
        response?.status,
      )
    }

    return data
  },

  async resendInvitation(workspaceId: string, invitationId: string): Promise<Invitation> {
    const { data, error, response } = await analyticsClient.POST(
      '/workspaces/{workspaceId}/invitations/{invitationId}/resend',
      {
        params: { path: { workspaceId, invitationId } },
        headers: {
          'X-Workspace-Id': workspaceId,
        },
      },
    )

    if (error || !data) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new WorkspaceApiError(
        errPayload?.message ?? 'Failed to resend invitation',
        errPayload?.code,
        response?.status,
      )
    }

    return data
  },

  async cancelInvitation(workspaceId: string, invitationId: string): Promise<void> {
    const { error, response } = await analyticsClient.DELETE(
      '/workspaces/{workspaceId}/invitations/{invitationId}',
      {
        params: { path: { workspaceId, invitationId } },
        headers: {
          'X-Workspace-Id': workspaceId,
        },
      },
    )

    if (error) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new WorkspaceApiError(
        errPayload?.message ?? 'Failed to cancel invitation',
        errPayload?.code,
        response?.status,
      )
    }
  },

  async getInvitationDetails(token: string): Promise<InvitationPublic> {
    const { data, error, response } = await analyticsClient.GET('/invitations/{token}', {
      params: { path: { token } },
    })

    if (error || !data) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new WorkspaceApiError(
        errPayload?.message ?? 'Приглашение не найдено или срок действия истёк',
        errPayload?.code,
        response?.status,
      )
    }

    return data
  },

  async acceptInvitation(
    token: string,
    acceptData?: { name?: string; password?: string },
  ): Promise<AcceptInvitationResult> {
    const { data, error, response } = await analyticsClient.POST(
      '/invitations/{token}/accept',
      {
        params: { path: { token } },
        body: acceptData ?? {},
      },
    )

    if (error || !data) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new WorkspaceApiError(
        errPayload?.message ?? 'Не удалось принять приглашение',
        errPayload?.code,
        response?.status,
      )
    }

    return data
  },
}
