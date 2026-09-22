import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type SalesOverview = components['schemas']['SalesOverviewResponse']
export type SalesSummary = components['schemas']['SalesSummary']
export type SalesTrendPoint = components['schemas']['SalesTrendPoint']
export type SalesCategoryBreakdown = components['schemas']['SalesCategoryBreakdown']
export type SalesRegionBreakdown = components['schemas']['SalesRegionBreakdown']
export type SalesFilterOptions = components['schemas']['SalesFilterOptionsResponse']

export interface SalesFilterParams {
  dateFrom?: string
  dateTo?: string
  categoryId?: string
  regionId?: string
}

export const salesGateway = {
  async getOverview(
    userId: string,
    workspaceId?: string,
    filters?: SalesFilterParams,
  ): Promise<SalesOverview> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/analytics/sales/overview', {
      params: {
        query: {
          date_from: filters?.dateFrom,
          date_to: filters?.dateTo,
          category_id: filters?.categoryId,
          region_id: filters?.regionId,
        },
      },
      headers,
    })

    if (error || !data) {
      throw new Error(error?.message ?? 'Failed to load sales overview')
    }

    return data
  },

  async getFilterOptions(
    userId: string,
    workspaceId?: string,
  ): Promise<SalesFilterOptions> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/analytics/sales/filters', {
      headers,
    })

    if (error || !data) {
      throw new Error(error?.message ?? 'Failed to load sales filter options')
    }

    return data
  },
}
