import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type AlertRule = components['schemas']['AlertRule']
export type Alert = components['schemas']['Alert']
export type AlertSeverity = components['schemas']['AlertSeverity']
export type AlertStatus = components['schemas']['AlertStatus']
export type AlertRuleListResponse = components['schemas']['AlertRuleListResponse']
export type AlertRuleDetailResponse = components['schemas']['AlertRuleDetailResponse']
export type AlertListResponse = components['schemas']['AlertListResponse']
export type AlertDetailResponse = components['schemas']['AlertDetailResponse']
export type AlertSummaryResponse = components['schemas']['AlertSummaryResponse']
export type AlertEvaluationResultResponse =
  components['schemas']['AlertEvaluationResultResponse']
export type CreateAlertRuleRequest = components['schemas']['CreateAlertRuleRequest']
export type UpdateAlertRuleRequest = components['schemas']['UpdateAlertRuleRequest']
export interface ResolveAlertRequest {
  resolution_note?: string
}

export interface GetAlertsParams {
  status?: 'all' | 'active' | 'open' | 'acknowledged' | 'resolved'
  severity?: AlertSeverity
  warehouseId?: string
  ruleId?: string
  page?: number
  perPage?: number
}

export const alertsGateway = {
  async getRules(
    userId: string,
    workspaceId: string,
    isEnabled?: boolean,
  ): Promise<AlertRuleListResponse> {
    const { data, error } = await analyticsClient.GET('/alert-rules', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      params: {
        query: {
          is_enabled: isEnabled,
        },
      },
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to load alert rules')
    }

    return data
  },

  async createRule(
    userId: string,
    workspaceId: string,
    payload: CreateAlertRuleRequest,
  ): Promise<AlertRuleDetailResponse> {
    const { data, error } = await analyticsClient.POST('/alert-rules', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      body: payload,
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to create alert rule')
    }

    return data
  },

  async getRule(
    userId: string,
    workspaceId: string,
    id: string,
  ): Promise<AlertRuleDetailResponse> {
    const { data, error } = await analyticsClient.GET('/alert-rules/{id}', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      params: {
        path: { id },
      },
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to load alert rule')
    }

    return data
  },

  async updateRule(
    userId: string,
    workspaceId: string,
    id: string,
    payload: UpdateAlertRuleRequest,
  ): Promise<AlertRuleDetailResponse> {
    const { data, error } = await analyticsClient.PUT('/alert-rules/{id}', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      params: {
        path: { id },
      },
      body: payload,
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to update alert rule')
    }

    return data
  },

  async deleteRule(userId: string, workspaceId: string, id: string): Promise<void> {
    const { error } = await analyticsClient.DELETE('/alert-rules/{id}', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      params: {
        path: { id },
      },
    })

    if (error) {
      throw new Error((error as any)?.message ?? 'Failed to delete alert rule')
    }
  },

  async toggleRule(
    userId: string,
    workspaceId: string,
    id: string,
  ): Promise<AlertRuleDetailResponse> {
    const { data, error } = await analyticsClient.POST('/alert-rules/{id}/toggle', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      params: {
        path: { id },
      },
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to toggle alert rule')
    }

    return data
  },

  async evaluateRules(
    userId: string,
    workspaceId: string,
  ): Promise<AlertEvaluationResultResponse> {
    const { data, error } = await analyticsClient.POST('/alert-rules/evaluate', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to evaluate alert rules')
    }

    return data
  },

  async getAlerts(
    userId: string,
    workspaceId: string,
    params?: GetAlertsParams,
  ): Promise<AlertListResponse> {
    const { data, error } = await analyticsClient.GET('/alerts', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      params: {
        query: {
          status: params?.status,
          severity: params?.severity,
          warehouse_id: params?.warehouseId,
          rule_id: params?.ruleId,
          page: params?.page,
          per_page: params?.perPage,
        },
      },
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to load alerts')
    }

    return data
  },

  async getSummary(userId: string, workspaceId: string): Promise<AlertSummaryResponse> {
    const { data, error } = await analyticsClient.GET('/alerts/summary', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to load alert summary')
    }

    return data
  },

  async getAlert(
    userId: string,
    workspaceId: string,
    id: string,
  ): Promise<AlertDetailResponse> {
    const { data, error } = await analyticsClient.GET('/alerts/{id}', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      params: {
        path: { id },
      },
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to load alert')
    }

    return data
  },

  async acknowledgeAlert(
    userId: string,
    workspaceId: string,
    id: string,
  ): Promise<AlertDetailResponse> {
    const { data, error } = await analyticsClient.POST('/alerts/{id}/acknowledge', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      params: {
        path: { id },
      },
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to acknowledge alert')
    }

    return data
  },

  async resolveAlert(
    userId: string,
    workspaceId: string,
    id: string,
    payload?: ResolveAlertRequest,
  ): Promise<AlertDetailResponse> {
    const { data, error } = await analyticsClient.POST('/alerts/{id}/resolve', {
      headers: {
        'X-Workspace-Id': workspaceId,
      },
      params: {
        path: { id },
      },
      body: payload ?? {},
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to resolve alert')
    }

    return data
  },
}
