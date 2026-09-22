import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type InventorySummaryResponse = components['schemas']['InventorySummaryResponse']
export type InventorySummary = components['schemas']['InventorySummary']
export type StockHealthBreakdownItem = components['schemas']['StockHealthBreakdownItem']
export type WarehouseStockBreakdownItem =
  components['schemas']['WarehouseStockBreakdownItem']
export type InventoryItemsResponse = components['schemas']['InventoryItemsResponse']
export type InventoryItem = components['schemas']['InventoryItem']
export type InventoryFilterOptionsResponse =
  components['schemas']['InventoryFilterOptionsResponse']

export interface InventorySummaryParams {
  warehouseId?: string
  asOfDate?: string
}

export interface InventoryItemsParams {
  warehouseId?: string
  stockHealth?: 'out_of_stock' | 'critical' | 'optimal' | 'overstock'
  search?: string
  page?: number
  perPage?: number
  sortBy?:
    | 'product_name'
    | 'quantity_on_hand'
    | 'quantity_available'
    | 'inventory_value'
    | 'sales_velocity'
    | 'days_of_stock'
  sortDirection?: 'asc' | 'desc'
}

export const inventoryGateway = {
  async getSummary(
    userId: string,
    workspaceId: string,
    params?: InventorySummaryParams,
  ): Promise<InventorySummaryResponse> {
    const { data, error } = await analyticsClient.GET('/analytics/inventory/summary', {
      headers: {
        'X-User-Id': userId,
        'X-Workspace-Id': workspaceId,
      },
      params: {
        query: {
          warehouse_id: params?.warehouseId,
          as_of_date: params?.asOfDate,
        },
      },
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to load inventory summary')
    }

    return data
  },

  async getItems(
    userId: string,
    workspaceId: string,
    params?: InventoryItemsParams,
  ): Promise<InventoryItemsResponse> {
    const { data, error } = await analyticsClient.GET('/analytics/inventory/items', {
      headers: {
        'X-User-Id': userId,
        'X-Workspace-Id': workspaceId,
      },
      params: {
        query: {
          warehouse_id: params?.warehouseId,
          stock_health: params?.stockHealth,
          search: params?.search,
          page: params?.page,
          per_page: params?.perPage,
          sort_by: params?.sortBy,
          sort_direction: params?.sortDirection,
        },
      },
    })

    if (error || !data) {
      throw new Error((error as any)?.message ?? 'Failed to load inventory items')
    }

    return data
  },

  async getFilters(
    userId: string,
    workspaceId: string,
  ): Promise<InventoryFilterOptionsResponse> {
    const { data, error } = await analyticsClient.GET('/analytics/inventory/filters', {
      headers: {
        'X-User-Id': userId,
        'X-Workspace-Id': workspaceId,
      },
    })

    if (error || !data) {
      throw new Error(
        (error as any)?.message ?? 'Failed to load inventory filter options',
      )
    }

    return data
  },
}
