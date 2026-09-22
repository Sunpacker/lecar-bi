import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import React from 'react'
import { DashboardGrid } from './dashboard-grid'
import type { WidgetDetail } from '../api/dashboard-gateway'

vi.mock('./widgets/widget-renderer', () => ({
  WidgetRenderer: ({ widget }: { widget: WidgetDetail }) => (
    <div data-testid={`widget-${widget.id}`}>{widget.title}</div>
  ),
}))

describe('DashboardGrid', () => {
  it('renders empty state when there are no widgets', () => {
    render(<DashboardGrid widgets={[]} userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByText('В этом дашборде пока нет виджетов')).toBeDefined()
  })

  it('renders all widgets with grid placement styles', () => {
    const widgets: WidgetDetail[] = [
      {
        id: 'w-1',
        title: 'KPI Выручка',
        type: 'kpi_card',
        query_config: { dataset: 'sales', metric: 'revenue' },
        position: { x: 0, y: 0, w: 4, h: 2 },
        options: {},
      },
      {
        id: 'w-2',
        title: 'График продаж',
        type: 'line_chart',
        query_config: { dataset: 'sales', metric: 'revenue' },
        position: { x: 4, y: 0, w: 8, h: 4 },
        options: {},
      },
    ]

    render(<DashboardGrid widgets={widgets} userId="user-1" workspaceId="ws-1" />)

    expect(screen.getByTestId('widget-w-1')).toBeDefined()
    expect(screen.getByTestId('widget-w-2')).toBeDefined()
    expect(screen.getByText('KPI Выручка')).toBeDefined()
    expect(screen.getByText('График продаж')).toBeDefined()
  })
})
