import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import React from 'react'
import { DashboardViewer } from './dashboard-viewer'
import type { DashboardDetail } from '../api/dashboard-gateway'

vi.mock('./dashboard-grid', () => ({
  DashboardGrid: () => <div data-testid="dashboard-grid-mock">Mock Grid</div>,
}))

describe('DashboardViewer', () => {
  const mockDashboard: DashboardDetail = {
    id: 'dash-1',
    workspace_id: 'ws-1',
    title: 'Оперативный дашборд',
    description: 'Показатели реального времени',
    widgets: [
      {
        id: 'w-1',
        title: 'Выручка',
        type: 'kpi_card',
        query_config: { dataset: 'sales', metric: 'revenue' },
        position: { x: 0, y: 0, w: 3, h: 2 },
        options: {},
      },
    ],
    created_at: '2026-09-22T10:00:00Z',
    updated_at: '2026-09-22T10:00:00Z',
  }

  it('renders dashboard title, description, and grid', () => {
    render(
      <DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />,
    )

    expect(screen.getByText('Оперативный дашборд')).toBeDefined()
    expect(screen.getByText('Показатели реального времени')).toBeDefined()
    expect(screen.getByTestId('dashboard-grid-mock')).toBeDefined()
    expect(screen.getByText('Назад к списку')).toBeDefined()
  })
})
