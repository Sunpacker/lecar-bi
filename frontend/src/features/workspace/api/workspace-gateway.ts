import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type Workspace = components['schemas']['WorkspaceResponse']
export type CurrentWorkspace = components['schemas']['CurrentWorkspaceResponse']
export type User = components['schemas']['UserResponse']

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
}
