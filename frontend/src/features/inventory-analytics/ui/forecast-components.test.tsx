import React from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ForecastQualityBadge } from './forecast-quality-badge'
import { ForecastStockRiskCard } from './forecast-stock-risk-card'
import { ForecastMetadata } from './forecast-metadata'
import { ForecastChart } from './forecast-chart'
import { ProductForecastView } from './product-forecast-view'
import { inventoryGateway } from '../api/inventory-gateway'
import type { ForecastResponse } from '../api/inventory-gateway'

vi.mock('../api/inventory-gateway', () => ({
  inventoryGateway: {
    getItems: vi.fn(),
    getForecast: vi.fn(),
  },
}))

describe('Forecast UI Components', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('ForecastQualityBadge', () => {
    it('renders ready status and backtest metrics', () => {
      render(
        <ForecastQualityBadge
          status="ready"
          modelMethod="seasonal_naive_dow"
          modelVersion="1.0.0"
          qualityMetrics={[
            {
              metric_name: 'wape',
              metric_value: 0.185,
              horizon_days: 28,
              evaluation_windows: 4,
            },
            {
              metric_name: 'mae',
              metric_value: 4.2,
              horizon_days: 28,
              evaluation_windows: 4,
            },
            {
              metric_name: 'signed_bias',
              metric_value: 0.5,
              horizon_days: 28,
              evaluation_windows: 4,
            },
            {
              metric_name: 'coverage',
              metric_value: 0.82,
              horizon_days: 28,
              evaluation_windows: 4,
            },
          ]}
        />,
      )

      expect(screen.getByText('Готов к использованию')).toBeDefined()
      expect(screen.getByText(/Сезонный наивный/)).toBeDefined()
      expect(screen.getByText('18.5%')).toBeDefined()
      expect(screen.getByText('4.2 шт')).toBeDefined()
      expect(screen.getByText('+0.5')).toBeDefined()
      expect(screen.getByText('82%')).toBeDefined()
    })

    it('renders stale status warning', () => {
      render(
        <ForecastQualityBadge
          status="stale"
          statusReason="Поступили новые продажи"
          modelMethod="moving_average_28d"
          modelVersion="1.0.0"
        />,
      )

      expect(screen.getByText('Устарел')).toBeDefined()
      expect(screen.getByText('Поступили новые продажи')).toBeDefined()
    })

    it('renders limited by stockouts status', () => {
      render(
        <ForecastQualityBadge
          status="limited_by_stockouts"
          modelMethod="seasonal_naive_dow"
          modelVersion="1.0.0"
        />,
      )

      expect(screen.getByText('Искажен дефицитом')).toBeDefined()
    })

    it('renders insufficient data status', () => {
      render(
        <ForecastQualityBadge
          status="insufficient_data"
          modelMethod="seasonal_naive_dow"
          modelVersion="1.0.0"
        />,
      )

      expect(screen.getByText('Недостаточно данных')).toBeDefined()
    })
  })

  describe('ForecastStockRiskCard', () => {
    const mockStockRisk = {
      current_quantity_available: 150,
      current_safety_stock: 30,
      current_reorder_point: 60,
      estimated_depletion_date: '2026-01-20',
      estimated_reorder_threshold_date: '2026-01-10',
      estimated_order_placement_date: '2026-01-03',
      median_lead_time_days: 7,
      lead_time_source: 'Поставки поставщика ООО АвтоДеталь',
      assumptions: 'Сценарий исчерпания рассчитан без учета будущих поступлений',
    }

    it('renders stock risk metrics, dates and lead time source', () => {
      render(<ForecastStockRiskCard stockRisk={mockStockRisk} asOfDate="2025-12-31" />)

      expect(screen.getByText('150 шт')).toBeDefined()
      expect(screen.getByText('60 шт')).toBeDefined()
      expect(screen.getByText('30 шт')).toBeDefined()
      expect(screen.getByText('7 дн')).toBeDefined()
      expect(screen.getByText('2026-01-20')).toBeDefined()
      expect(screen.getByText('2026-01-10')).toBeDefined()
      expect(screen.getByText('2026-01-03')).toBeDefined()
      expect(
        screen.getByText(/Будущие неподтвержденные поступления не учитываются/),
      ).toBeDefined()
    })

    it('indicates immediate reorder when order date is on or before asOfDate', () => {
      const urgentRisk = {
        ...mockStockRisk,
        estimated_order_placement_date: '2025-12-30',
      }

      render(<ForecastStockRiskCard stockRisk={urgentRisk} asOfDate="2025-12-31" />)

      expect(screen.getAllByText(/Заказать сейчас/i).length).toBeGreaterThan(0)
    })

    it('handles empty stock risk gracefully', () => {
      render(<ForecastStockRiskCard stockRisk={null} asOfDate="2025-12-31" />)

      expect(screen.getByText('Оценка рисков запасов')).toBeDefined()
      expect(screen.getByText(/Данные по остаткам недоступны/)).toBeDefined()
    })
  })

  describe('ForecastMetadata', () => {
    it('renders data freshness dates and assumptions list', () => {
      render(
        <ForecastMetadata
          dataFreshness={{
            sales_date: '2025-12-31',
            inventory_date: '2025-12-31',
            supplier_date: '2025-12-30',
          }}
          asOfDate="2025-12-31"
          generatedAt="2025-12-31T23:59:59Z"
          horizonDays={28}
          assumptions={[
            'Дни дефицита цензурированы',
            'Сценарий исчерпания рассчитан без будущих поступлений',
          ]}
        />,
      )

      expect(screen.getByText('2025-12-31', { selector: '.font-semibold' })).toBeDefined()
      expect(screen.getByText('2025-12-30')).toBeDefined()
      expect(
        screen.getByText(/Горизонт прогнозирования: 28 календарных дней/),
      ).toBeDefined()
      expect(screen.getByText('Дни дефицита цензурированы')).toBeDefined()
      expect(
        screen.getByText('Сценарий исчерпания рассчитан без будущих поступлений'),
      ).toBeDefined()
    })
  })

  describe('ForecastChart', () => {
    it('renders chart title and handles empty data', () => {
      render(
        <ForecastChart
          history={[]}
          forecast={[]}
          asOfDate="2025-12-31"
          productName="Колодки тормозные"
          horizonDays={28}
        />,
      )

      expect(screen.getByText(/Колодки тормозные/)).toBeDefined()
      expect(screen.getByText('Нет данных для построения графика прогноза')).toBeDefined()
    })
  })

  describe('ProductForecastView', () => {
    const mockForecastResponse: ForecastResponse = {
      workspace_id: 'ws-1',
      product_id: 'prod-1',
      product_name: 'Фильтр масляный Pro',
      product_sku: 'FL-001',
      warehouse_id: 'wh-1',
      warehouse_name: 'Склад Север',
      as_of_date: '2025-12-31',
      generated_at: '2025-12-31T23:59:59Z',
      horizon_days: 28,
      model_method: 'seasonal_naive_dow',
      model_version: '1.0.0',
      status: 'ready',
      data_freshness: {
        sales_date: '2025-12-31',
        inventory_date: '2025-12-31',
        supplier_date: '2025-12-31',
      },
      points: [
        {
          date: '2026-01-01',
          point_estimate: 5.0,
          lower_bound: 3.0,
          upper_bound: 7.0,
          interval_level: 0.8,
          is_stockout_day: false,
        },
      ],
      measured_history: [
        {
          date: '2025-12-30',
          point_estimate: 4.5,
          is_stockout_day: false,
        },
      ],
      quality_metrics: [
        {
          metric_name: 'wape',
          metric_value: 0.12,
          horizon_days: 28,
          evaluation_windows: 4,
        },
      ],
      stock_risk: {
        current_quantity_available: 50,
        current_safety_stock: 10,
        current_reorder_point: 20,
        estimated_depletion_date: '2026-01-11',
        estimated_reorder_threshold_date: '2026-01-07',
        estimated_order_placement_date: '2026-01-02',
        median_lead_time_days: 5,
        assumptions: 'Сценарий исчерпания рассчитан без учета будущих поступлений',
      },
      assumptions: ['Расчет исчерпания выполнен без учета ожидаемых поступлений'],
    }

    it('loads and renders complete forecast for specified product and warehouse', async () => {
      vi.mocked(inventoryGateway.getForecast).mockResolvedValueOnce(mockForecastResponse)

      render(
        <ProductForecastView
          userId="user-1"
          workspaceId="ws-1"
          productId="prod-1"
          warehouseId="wh-1"
        />,
      )

      await waitFor(() => {
        expect(screen.getByText(/Фильтр масляный Pro/)).toBeDefined()
      })

      expect(inventoryGateway.getForecast).toHaveBeenCalledWith(
        'user-1',
        'ws-1',
        'prod-1',
        'wh-1',
        { horizonDays: 28 },
      )
      expect(screen.getByText('Готов к использованию')).toBeDefined()
      expect(screen.getByText('50 шт')).toBeDefined()
    })

    it('reloads forecast when horizon button is clicked', async () => {
      vi.mocked(inventoryGateway.getForecast)
        .mockResolvedValueOnce(mockForecastResponse)
        .mockResolvedValueOnce({
          ...mockForecastResponse,
          horizon_days: 14,
        })

      render(
        <ProductForecastView
          userId="user-1"
          workspaceId="ws-1"
          productId="prod-1"
          warehouseId="wh-1"
        />,
      )

      await waitFor(() => {
        expect(screen.getByText(/Фильтр масляный Pro/)).toBeDefined()
      })

      const btn14 = screen.getByText('14 дней')
      fireEvent.click(btn14)

      await waitFor(() => {
        expect(inventoryGateway.getForecast).toHaveBeenCalledWith(
          'user-1',
          'ws-1',
          'prod-1',
          'wh-1',
          { horizonDays: 14 },
        )
      })
    })

    it('renders error message when forecast fails to load', async () => {
      vi.mocked(inventoryGateway.getForecast).mockRejectedValueOnce(
        new Error('Прогноз не найден'),
      )

      render(
        <ProductForecastView
          userId="user-1"
          workspaceId="ws-1"
          productId="prod-1"
          warehouseId="wh-1"
        />,
      )

      await waitFor(() => {
        expect(screen.getByText('Прогноз не сформирован')).toBeDefined()
        expect(screen.getByText('Прогноз не найден')).toBeDefined()
      })
    })
  })
})
