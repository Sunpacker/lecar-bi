import { salesGateway } from '../../sales-analytics/api/sales-gateway'
import { inventoryGateway } from '../../inventory-analytics/api/inventory-gateway'
import type { WidgetDetail } from '../api/dashboard-gateway'

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

function resolveDateRangeFilters(dateRange?: string | null): {
  dateFrom?: string
  dateTo?: string
} {
  if (!dateRange || dateRange === 'all') {
    return {}
  }

  const daysMap: Record<string, number> = {
    '30d': 30,
    '90d': 90,
    '180d': 180,
    '365d': 365,
  }

  const days = daysMap[dateRange]
  if (!days) return {}

  const to = new Date()
  const from = new Date()
  from.setDate(to.getDate() - days)

  return {
    dateFrom: from.toISOString().split('T')[0],
    dateTo: to.toISOString().split('T')[0],
  }
}

export async function loadWidgetData(
  widget: WidgetDetail,
  userId: string,
  workspaceId: string,
): Promise<WidgetDataResult> {
  const { dataset, metric, dimension, date_range } = widget.query_config
  const unit = widget.options?.unit as string | undefined

  try {
    if (dataset === 'sales') {
      const filters = resolveDateRangeFilters(date_range)

      if (widget.type === 'kpi_card') {
        const overview = await salesGateway.getOverview(userId, workspaceId, filters)
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
            subtitle: date_range ? `Период: ${date_range}` : undefined,
          },
        }
      }

      if (widget.type === 'line_chart' || dimension === 'date') {
        const overview = await salesGateway.getOverview(userId, workspaceId, filters)
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
        const overview = await salesGateway.getOverview(userId, workspaceId, filters)
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
          ...filters,
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
      if (widget.type === 'kpi_card') {
        const summaryRes = await inventoryGateway.getSummary(userId, workspaceId)
        let val = 0
        switch (metric) {
          case 'stock_quantity':
            val = summaryRes.summary.total_quantity_on_hand
            break
          case 'stock_value':
            val = summaryRes.summary.total_inventory_value
            break
          case 'out_of_stock_count':
            val = summaryRes.summary.out_of_stock_items
            break
          case 'overstock_count':
            val = summaryRes.summary.overstock_items
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
          const summaryRes = await inventoryGateway.getSummary(userId, workspaceId)
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
          const abcSummary = await inventoryGateway.getAbcXyzSummary(userId, workspaceId)
          const dist =
            dimension === 'abc_class' ? abcSummary.abc_distribution : abcSummary.xyz_distribution
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
              quantity_available: formatMetricValue(item.quantity_available, 'stock_quantity'),
              inventory_value: formatMetricValue(item.inventory_value, 'stock_value', 'currency'),
            })),
          },
        }
      }
    }

    return { loading: false }
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Ошибка при загрузке данных виджета'
    return {
      loading: false,
      error: message,
    }
  }
}
