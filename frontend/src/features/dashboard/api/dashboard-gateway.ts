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
export type DashboardFilterValues = components['schemas']['DashboardFilterValues']
export type DashboardSavedView = components['schemas']['DashboardSavedView']
export type CreateDashboardSavedViewRequest =
  components['schemas']['CreateDashboardSavedViewRequest']
export type UpdateDashboardSavedViewRequest =
  components['schemas']['UpdateDashboardSavedViewRequest']

export const dashboardGateway = {
  async list(userId: string, workspaceId?: string): Promise<DashboardSummary[]> {
    const headers: Record<string, string> = {}
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
    const headers: Record<string, string> = {}
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
    const headers: Record<string, string> = {}
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
    const headers: Record<string, string> = {}
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
    const headers: Record<string, string> = {}
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

  async listSavedViews(
    dashboardId: string,
    userId: string,
    workspaceId?: string,
  ): Promise<DashboardSavedView[]> {
    const headers: Record<string, string> = {}
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/dashboards/{dashboardId}/views', {
      params: {
        path: { dashboardId },
      },
      headers,
    })

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to load saved views',
      )
    }

    return data.items
  },

  async getSavedView(
    dashboardId: string,
    viewId: string,
    userId: string,
    workspaceId?: string,
  ): Promise<DashboardSavedView> {
    const headers: Record<string, string> = {}
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET(
      '/dashboards/{dashboardId}/views/{viewId}',
      {
        params: {
          path: { dashboardId, viewId },
        },
        headers,
      },
    )

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to load saved view',
      )
    }

    return data.view
  },

  async createSavedView(
    dashboardId: string,
    userId: string,
    request: CreateDashboardSavedViewRequest,
    workspaceId?: string,
  ): Promise<DashboardSavedView> {
    const headers: Record<string, string> = {}
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.POST(
      '/dashboards/{dashboardId}/views',
      {
        params: {
          path: { dashboardId },
        },
        body: request,
        headers,
      },
    )

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to create saved view',
      )
    }

    return data.view
  },

  async updateSavedView(
    dashboardId: string,
    viewId: string,
    userId: string,
    request: UpdateDashboardSavedViewRequest,
    workspaceId?: string,
  ): Promise<DashboardSavedView> {
    const headers: Record<string, string> = {}
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.PUT(
      '/dashboards/{dashboardId}/views/{viewId}',
      {
        params: {
          path: { dashboardId, viewId },
        },
        body: request,
        headers,
      },
    )

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to update saved view',
      )
    }

    return data.view
  },

  async deleteSavedView(
    dashboardId: string,
    viewId: string,
    userId: string,
    workspaceId?: string,
  ): Promise<void> {
    const headers: Record<string, string> = {}
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { error } = await analyticsClient.DELETE(
      '/dashboards/{dashboardId}/views/{viewId}',
      {
        params: {
          path: { dashboardId, viewId },
        },
        headers,
      },
    )

    if (error) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to delete saved view',
      )
    }
  },
}
