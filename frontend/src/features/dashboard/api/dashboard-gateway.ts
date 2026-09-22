import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type DashboardSummary = components['schemas']['DashboardSummary']
export type DashboardDetail = components['schemas']['DashboardDetail']
export type WidgetDetail = components['schemas']['WidgetDetail']
export type WidgetInput = components['schemas']['WidgetInput']
export type WidgetQueryConfig = components['schemas']['WidgetQueryConfig']
export type WidgetGridPosition = components['schemas']['WidgetGridPosition']
export type CreateDashboardRequest = components['schemas']['CreateDashboardRequest']
export type UpdateDashboardRequest = components['schemas']['UpdateDashboardRequest']

export const dashboardGateway = {
  async list(userId: string, workspaceId?: string): Promise<DashboardSummary[]> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/dashboards', {
      headers,
    })

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to load dashboards',
      )
    }

    return data.items
  },

  async getById(
    id: string,
    userId: string,
    workspaceId?: string,
  ): Promise<DashboardDetail> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/dashboards/{id}', {
      params: {
        path: { id },
      },
      headers,
    })

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to load dashboard',
      )
    }

    return data.dashboard
  },

  async create(
    userId: string,
    request: CreateDashboardRequest,
    workspaceId?: string,
  ): Promise<DashboardDetail> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.POST('/dashboards', {
      body: request,
      headers,
    })

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to create dashboard',
      )
    }

    return data.dashboard
  },

  async update(
    id: string,
    userId: string,
    request: UpdateDashboardRequest,
    workspaceId?: string,
  ): Promise<DashboardDetail> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.PUT('/dashboards/{id}', {
      params: {
        path: { id },
      },
      body: request,
      headers,
    })

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to update dashboard',
      )
    }

    return data.dashboard
  },

  async delete(id: string, userId: string, workspaceId?: string): Promise<void> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { error } = await analyticsClient.DELETE('/dashboards/{id}', {
      params: {
        path: { id },
      },
      headers,
    })

    if (error) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to delete dashboard',
      )
    }
  },
}
