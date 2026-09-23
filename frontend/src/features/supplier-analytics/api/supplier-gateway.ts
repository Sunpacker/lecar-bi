import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type SupplierOverviewResponse = components['schemas']['SupplierOverviewResponse']
export type SupplierSummary = components['schemas']['SupplierSummary']
export type DeliveryStatusBreakdownItem =
  components['schemas']['DeliveryStatusBreakdownItem']
export type SupplierTrendPoint = components['schemas']['SupplierTrendPoint']
export type SupplierPerformanceResponse =
  components['schemas']['SupplierPerformanceResponse']
export type SupplierPerformanceItem = components['schemas']['SupplierPerformanceItem']
export type SupplierDeliveriesResponse =
  components['schemas']['SupplierDeliveriesResponse']
export type SupplierDeliveryItem = components['schemas']['SupplierDeliveryItem']
export type SupplierFilterOptionsResponse =
  components['schemas']['SupplierFilterOptionsResponse']
export type DeliveryStatus = components['schemas']['DeliveryStatus']
export type SupplierReliabilityTier = components['schemas']['SupplierReliabilityTier']

export interface SupplierOverviewParams {
  dateFrom?: string
  dateTo?: string
  supplierId?: string
  warehouseId?: string
}

export interface SupplierPerformanceParams {
  dateFrom?: string
  dateTo?: string
  warehouseId?: string
  search?: string
  page?: number
  perPage?: number
  sortBy?:
    | 'supplier_name'
    | 'total_deliveries'
    | 'total_spend'
    | 'on_time_rate'
    | 'fulfillment_rate'
    | 'defect_rate'
    | 'avg_lead_time_days'
    | 'reliability_score'
  sortDirection?: 'asc' | 'desc'
}

export interface SupplierDeliveriesParams {
  supplierId?: string
  warehouseId?: string
  status?: DeliveryStatus
  dateFrom?: string
  dateTo?: string
  search?: string
  page?: number
  perPage?: number
  sortBy?:
    | 'order_date'
    | 'expected_delivery_date'
    | 'actual_delivery_date'
    | 'lead_time_days'
    | 'delay_days'
    | 'total_purchase_cost'
  sortDirection?: 'asc' | 'desc'
}

export const supplierGateway = {
  async getOverview(
    userId: string,
    workspaceId: string,
    params?: SupplierOverviewParams,
  ): Promise<SupplierOverviewResponse> {
    const { data, error } = await analyticsClient.GET('/analytics/suppliers/overview', {
      headers: {
        'X-User-Id': userId,
        'X-Workspace-Id': workspaceId,
      },
      params: {
        query: {
          date_from: params?.dateFrom,
          date_to: params?.dateTo,
          supplier_id: params?.supplierId,
          warehouse_id: params?.warehouseId,
        },
      },
    })

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to load supplier overview',
      )
    }

    return data
  },

  async getPerformance(
    userId: string,
    workspaceId: string,
    params?: SupplierPerformanceParams,
  ): Promise<SupplierPerformanceResponse> {
    const { data, error } = await analyticsClient.GET(
      '/analytics/suppliers/performance',
      {
        headers: {
          'X-User-Id': userId,
          'X-Workspace-Id': workspaceId,
        },
        params: {
          query: {
            date_from: params?.dateFrom,
            date_to: params?.dateTo,
            warehouse_id: params?.warehouseId,
            search: params?.search,
            page: params?.page,
            per_page: params?.perPage,
            sort_by: params?.sortBy,
            sort_direction: params?.sortDirection,
          },
        },
      },
    )

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to load supplier performance',
      )
    }

    return data
  },

  async getDeliveries(
    userId: string,
    workspaceId: string,
    params?: SupplierDeliveriesParams,
  ): Promise<SupplierDeliveriesResponse> {
    const { data, error } = await analyticsClient.GET('/analytics/suppliers/deliveries', {
      headers: {
        'X-User-Id': userId,
        'X-Workspace-Id': workspaceId,
      },
      params: {
        query: {
          supplier_id: params?.supplierId,
          warehouse_id: params?.warehouseId,
          status: params?.status,
          date_from: params?.dateFrom,
          date_to: params?.dateTo,
          search: params?.search,
          page: params?.page,
          per_page: params?.perPage,
          sort_by: params?.sortBy,
          sort_direction: params?.sortDirection,
        },
      },
    })

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to load supplier deliveries',
      )
    }

    return data
  },

  async getFilters(
    userId: string,
    workspaceId: string,
  ): Promise<SupplierFilterOptionsResponse> {
    const { data, error } = await analyticsClient.GET('/analytics/suppliers/filters', {
      headers: {
        'X-User-Id': userId,
        'X-Workspace-Id': workspaceId,
      },
    })

    if (error || !data) {
      throw new Error(
        (error as { message?: string })?.message ?? 'Failed to load supplier filters',
      )
    }

    return data
  },
}
