import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import React from 'react'
import { WidgetRenderer } from './widget-renderer'
import { WidgetKpiCard } from './widget-kpi-card'
import * as widgetDataLoader from '../../model/widget-data-loader'
import type { WidgetDetail } from '../../api/dashboard-gateway'

vi.mock('../../model/widget-data-loader', async (importOriginal) => {
  const actual = await importOriginal<typeof widgetDataLoader>()
  return {
    ...actual,
    loadWidgetData: vi.fn(),
  }
})

describe('Widget Components', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders WidgetKpiCard with formatted value and subtitle', () => {
    render(
      <WidgetKpiCard
        title="Общая выручка"
        value="1 250 000 ₽"
        subtitle="За последние 30 дней"
      />,
    )

    expect(screen.getByText('Общая выручка')).toBeDefined()
    expect(screen.getByText('1 250 000 ₽')).toBeDefined()
    expect(screen.getByText('За последние 30 дней')).toBeDefined()
  })

  it('renders WidgetRenderer in loading state before data arrives', () => {
    const widget: WidgetDetail = {
      id: 'w-kpi',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue' },
      position: { x: 0, y: 0, w: 3, h: 2 },
      options: {},
    }

    vi.mocked(widgetDataLoader.loadWidgetData).mockReturnValue(
      new Promise(() => {}), // Never resolves to keep in loading state
    )

    render(<WidgetRenderer widget={widget} userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByText('Выручка')).toBeDefined()
    expect(screen.getByTestId('widget-loading-skeleton')).toBeDefined()
  })

  it('renders WidgetRenderer with KPI card after data loads', async () => {
    const widget: WidgetDetail = {
      id: 'w-kpi',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue' },
      position: { x: 0, y: 0, w: 3, h: 2 },
      options: {},
    }

    vi.mocked(widgetDataLoader.loadWidgetData).mockResolvedValueOnce({
      loading: false,
      kpi: {
        value: 2000000,
        formatted: '2 000 000 ₽',
        subtitle: 'Период: 30d',
      },
    })

    render(<WidgetRenderer widget={widget} userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('2 000 000 ₽')).toBeDefined()
    })
    expect(screen.getByText('Период: 30d')).toBeDefined()
  })

  it('renders WidgetRenderer error state when data load fails', async () => {
    const widget: WidgetDetail = {
      id: 'w-err',
      title: 'Виджет с ошибкой',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue' },
      position: { x: 0, y: 0, w: 3, h: 2 },
      options: {},
    }

    vi.mocked(widgetDataLoader.loadWidgetData).mockResolvedValueOnce({
      loading: false,
      error: 'Ошибка соединения с API',
    })

    render(<WidgetRenderer widget={widget} userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('Ошибка соединения с API')).toBeDefined()
    })
  })
})
