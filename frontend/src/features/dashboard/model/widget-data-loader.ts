import { salesGateway } from '../../sales-analytics/api/sales-gateway'
import { inventoryGateway } from '../../inventory-analytics/api/inventory-gateway'
import type { DashboardFilterValues, WidgetDetail } from '../api/dashboard-gateway'
import {
  mergeFilters,
  resolveDateRange,
  sanitizeFiltersForDataset,
} from './filter-resolver'

export interface WidgetChartPoint {
  name: string
  value: number
  formatted?: string
}

export interface WidgetTableData {
  columns: { key: string; label: string }[]
  rows: Record<string, unknown>[]
}

export interface WidgetDataResult {
  loading: boolean
  error?: string | null
  kpi?: {
    value: number
    formatted: string
    subtitle?: string
  }
  chartData?: WidgetChartPoint[]
  tableData?: WidgetTableData
}

export function formatMetricValue(value: number, metric: string, unit?: string): string {
  const isCurrency =
    unit === 'currency' ||
    metric === 'revenue' ||
    metric === 'gross_profit' ||
    metric === 'average_order_value' ||
    metric === 'stock_value'

  const isPercent = unit === 'percent' || metric === 'margin_rate'

  if (isCurrency) {
    return new Intl.NumberFormat('ru-RU', {
      style: 'currency',
      currency: 'RUB',
      maximumFractionDigits: 0,
    })
      .format(value)
      .replace(/\s/g, ' ')
      .replace('руб.', '₽')
      .trim()
  }

  if (isPercent) {
    const percentVal = value > 1 ? value : value * 100
    return `${percentVal.toFixed(1)}%`
  }

  return new Intl.NumberFormat('ru-RU').format(value).replace(/\s/g, ' ')
}

function mapStockHealth(
  health?: 'in_stock' | 'low_stock' | 'out_of_stock' | 'overstock' | null,
): 'out_of_stock' | 'critical' | 'optimal' | 'overstock' | undefined {
  if (!health) return undefined
  switch (health) {
    case 'low_stock':
      return 'critical'
    case 'in_stock':
      return 'optimal'
    case 'out_of_stock':
      return 'out_of_stock'
    case 'overstock':
      return 'overstock'
    default:
      return undefined
  }
}

const inFlightRequests = new Map<string, Promise<unknown>>()

/**
 * Coalesces concurrent in-flight requests within the same render cycle.
 * Automatically deletes the promise from the map upon completion or failure,
 * preventing memory leaks and avoiding cross-session / cross-workspace persistence.
 */
export function coalesceInFlightRequest<T>(
  key: string,
  fetcher: () => Promise<T>,
): Promise<T> {
  const existing = inFlightRequests.get(key)
  if (existing) {
    return existing as Promise<T>
  }

  const promise = fetcher().finally(() => {
    inFlightRequests.delete(key)
  })

  inFlightRequests.set(key, promise)
  return promise
}

export function clearInFlightRequests(): void {
  inFlightRequests.clear()
}

export async function loadWidgetData(
  widget: WidgetDetail,
  userId: string,
  workspaceId: string,
  dashboardFilters?: DashboardFilterValues | null,
): Promise<WidgetDataResult> {
  const { dataset, metric, dimension } = widget.query_config
  const unit = widget.options?.unit as string | undefined

  // Merge dashboard-level filters with widget-level overrides
  const widgetOverrides: DashboardFilterValues = {
    date_range: widget.query_config.date_range ?? widget.query_config.filters?.date_range,
    date_from: widget.query_config.filters?.date_from,
    date_to: widget.query_config.filters?.date_to,
    category_id: widget.query_config.filters?.category_id,
    region_id: widget.query_config.filters?.region_id,
    warehouse_id: widget.query_config.filters?.warehouse_id,
    stock_health: widget.query_config.filters?.stock_health,
  }

  const merged = mergeFilters(dashboardFilters, widgetOverrides)
  const sanitized = sanitizeFiltersForDataset(merged, dataset)
  const { dateFrom, dateTo } = resolveDateRange(
    sanitized.date_range,
    sanitized.date_from,
    sanitized.date_to,
  )

  try {
    if (dataset === 'sales') {
      const salesFilters = {
        dateFrom,
        dateTo,
        categoryId: sanitized.category_id || undefined,
        regionId: sanitized.region_id || undefined,
      }

      const salesKey = `sales_overview:${workspaceId}:${salesFilters.dateFrom ?? ''}:${salesFilters.dateTo ?? ''}:${salesFilters.categoryId ?? ''}:${salesFilters.regionId ?? ''}`
      const fetchSalesOverview = () =>
        coalesceInFlightRequest(salesKey, () =>
          salesGateway.getOverview(userId, workspaceId, salesFilters),
        )

      if (widget.type === 'kpi_card') {
        const overview = await fetchSalesOverview()
        let val = 0
        switch (metric) {
          case 'revenue':
            val = overview.summary.total_revenue
            break
          case 'order_count':
            val = overview.summary.order_count
            break
          case 'average_order_value':
            val = overview.summary.average_order_value
            break
          case 'gross_profit':
            val = overview.summary.gross_profit
            break
          case 'margin_rate':
            val = overview.summary.margin_rate
            break
          default:
            val = overview.summary.total_revenue
        }
        return {
          loading: false,
          kpi: {
            value: val,
            formatted: formatMetricValue(val, metric, unit),
            subtitle: sanitized.date_range
              ? `Период: ${sanitized.date_range}`
              : undefined,
          },
        }
      }

      if (widget.type === 'line_chart' || dimension === 'date') {
        const overview = await fetchSalesOverview()
        const chartData: WidgetChartPoint[] = overview.trend.map((pt) => {
          const val = metric === 'order_count' ? pt.order_count : pt.revenue
          return {
            name: pt.date,
            value: val,
            formatted: formatMetricValue(val, metric, unit),
          }
        })
        return { loading: false, chartData }
      }

      if (widget.type === 'donut_chart' || widget.type === 'bar_chart') {
        const overview = await fetchSalesOverview()
        if (dimension === 'region') {
          const chartData: WidgetChartPoint[] = overview.regions.map((reg) => ({
            name: reg.region_name,
            value: reg.revenue,
            formatted: formatMetricValue(reg.revenue, 'revenue', 'currency'),
          }))
          return { loading: false, chartData }
        }

        const chartData: WidgetChartPoint[] = overview.categories.map((cat) => ({
          name: cat.category_name,
          value: cat.revenue,
          formatted: formatMetricValue(cat.revenue, 'revenue', 'currency'),
        }))
        return { loading: false, chartData }
      }

      if (widget.type === 'table') {
        const records = await salesGateway.getRecords(userId, workspaceId, {
          ...salesFilters,
          page: 1,
          perPage: 10,
        })
        return {
          loading: false,
          tableData: {
            columns: [
              { key: 'order_number', label: 'Номер заказа' },
              { key: 'order_date', label: 'Дата' },
              { key: 'product_name', label: 'Товар' },
              { key: 'total_price', label: 'Сумма' },
            ],
            rows: records.items.map((item) => ({
              order_number: item.order_number,
              order_date: item.order_date,
              product_name: item.product_name,
              total_price: formatMetricValue(item.total_price, 'revenue', 'currency'),
            })),
          },
        }
      }
    }

    if (dataset === 'inventory') {
      const warehouseId = sanitized.warehouse_id || undefined
      const stockHealth = mapStockHealth(sanitized.stock_health)

      const invKey = `inventory_summary:${workspaceId}:${warehouseId ?? ''}:${dateTo ?? ''}`
      const fetchInventorySummary = () =>
        coalesceInFlightRequest(invKey, () =>
          inventoryGateway.getSummary(userId, workspaceId, {
            warehouseId,
            asOfDate: dateTo,
          }),
        )

      if (widget.type === 'kpi_card') {
        const summaryRes = await fetchInventorySummary()
        let val = 0
        switch (metric) {
          case 'stock_quantity':
            val = summaryRes.summary.total_quantity_on_hand
            break
          case 'stock_value':
            val = summaryRes.summary.total_inventory_value
            break
          case 'out_of_stock_count':
            val = summaryRes.summary.out_of_stock_count
            break
          case 'overstock_count':
            val = summaryRes.summary.overstock_count
            break
          default:
            val = summaryRes.summary.total_quantity_on_hand
        }
        return {
          loading: false,
          kpi: {
            value: val,
            formatted: formatMetricValue(val, metric, unit),
          },
        }
      }

      if (widget.type === 'bar_chart' || widget.type === 'donut_chart') {
        if (dimension === 'warehouse') {
          const summaryRes = await fetchInventorySummary()
          const chartData: WidgetChartPoint[] = summaryRes.warehouses.map((wh) => {
            const val = metric === 'stock_value' ? wh.total_value : wh.total_quantity
            return {
              name: wh.warehouse_name,
              value: val,
              formatted: formatMetricValue(val, metric, unit),
            }
          })
          return { loading: false, chartData }
        }

        if (dimension === 'abc_class' || dimension === 'xyz_class') {
          const periodDays =
            sanitized.date_range === '30d'
              ? 30
              : sanitized.date_range === '180d'
                ? 180
                : sanitized.date_range === '365d'
                  ? 365
                  : 90
          const abcKey = `abc_summary:${workspaceId}:${warehouseId ?? ''}:${sanitized.category_id ?? ''}:${periodDays}`
          const abcSummary = await coalesceInFlightRequest(abcKey, () =>
            inventoryGateway.getAbcXyzSummary(userId, workspaceId, {
              warehouseId,
              categoryId: sanitized.category_id || undefined,
              periodDays,
            }),
          )
          const dist =
            dimension === 'abc_class'
              ? abcSummary.data.abc_distribution
              : abcSummary.data.xyz_distribution
          const chartData: WidgetChartPoint[] = dist.map((d) => ({
            name: `Класс ${d.class}`,
            value: d.revenue,
            formatted: formatMetricValue(d.revenue, 'revenue', 'currency'),
          }))
          return { loading: false, chartData }
        }
      }

      if (widget.type === 'table') {
        const itemsRes = await inventoryGateway.getItems(userId, workspaceId, {
          warehouseId,
          stockHealth,
          page: 1,
          perPage: 10,
        })
        return {
          loading: false,
          tableData: {
            columns: [
              { key: 'product_name', label: 'Товар' },
              { key: 'product_sku', label: 'Артикул' },
              { key: 'quantity_available', label: 'Доступно' },
              { key: 'inventory_value', label: 'Стоимость' },
            ],
            rows: itemsRes.items.map((item) => ({
              product_name: item.product_name,
              product_sku: item.product_sku,
              quantity_available: formatMetricValue(
                item.quantity_available,
                'stock_quantity',
              ),
              inventory_value: formatMetricValue(
                item.inventory_value,
                'stock_value',
                'currency',
              ),
            })),
          },
        }
      }
    }

    return { loading: false }
  } catch (err: unknown) {
    const message =
      err instanceof Error ? err.message : 'Ошибка при загрузке данных виджета'
    return {
      loading: false,
      error: message,
    }
  }
}
