import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { DashboardViewer } from './dashboard-viewer'
import { dashboardGateway, type DashboardDetail } from '../api/dashboard-gateway'
import { WorkspaceAccessProvider } from '../../workspace/ui/workspace-access-provider'

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    update: vi.fn(),
    listSavedViews: vi.fn(),
  },
}))

vi.mock('./dashboard-grid', () => ({
  DashboardGrid: ({ widgets }: { widgets: unknown[] }) => (
    <div data-testid="dashboard-grid-view">Grid View with {widgets.length} widgets</div>
  ),
}))

vi.mock('./widgets/widget-renderer', () => ({
  WidgetRenderer: () => <div>Widget Renderer</div>,
}))

const mockDashboard: DashboardDetail = {
  id: 'dash-1',
  workspace_id: 'ws-1',
  title: 'Сводный дашборд',
  description: 'Аналитика бизнеса',
  created_at: '2026-09-22T10:00:00Z',
  updated_at: '2026-09-22T10:00:00Z',
  widgets: [
    {
      id: 'w-1',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue', date_range: '30d' },
      position: { x: 0, y: 0, w: 4, h: 2 },
      options: {},
    },
  ],
}

describe('DashboardViewer', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(dashboardGateway.listSavedViews).mockResolvedValue([])
  })

  it('renders in view mode with edit button and grid view when user has dashboards.manage capability', () => {
    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view', 'dashboards.manage']}>
        <DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />
      </WorkspaceAccessProvider>,
    )

    expect(screen.getByText('Сводный дашборд')).toBeInTheDocument()
    expect(screen.getByText('Аналитика бизнеса')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Редактировать/i })).toBeInTheDocument()
    expect(screen.getByTestId('dashboard-grid-view')).toBeInTheDocument()
  })

  it('hides edit button when user lacks dashboards.manage capability (viewer)', () => {
    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view']}>
        <DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />
      </WorkspaceAccessProvider>,
    )

    expect(screen.getByText('Сводный дашборд')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Редактировать/i })).toBeNull()
    expect(screen.getByRole('button', { name: /Обновить данные/i })).toBeInTheDocument()
  })

  it('switches to edit mode, allows modifying title and saving changes', async () => {
    vi.mocked(dashboardGateway.update).mockResolvedValueOnce({
      ...mockDashboard,
      title: 'Новый Сводный дашборд',
    })

    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view', 'dashboards.manage']}>
        <DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />
      </WorkspaceAccessProvider>,
    )

    // Switch to edit mode
    const editBtn = screen.getByRole('button', { name: /Редактировать/i })
    fireEvent.click(editBtn)

    expect(screen.getByTestId('dashboard-title-input')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Добавить виджет/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Сохранить/i })).toBeInTheDocument()

    // Change title
    const titleInput = screen.getByTestId('dashboard-title-input')
    fireEvent.change(titleInput, { target: { value: 'Новый Сводный дашборд' } })

    // Save
    const saveBtn = screen.getByRole('button', { name: /Сохранить/i })
    fireEvent.click(saveBtn)

    await waitFor(() => {
      expect(dashboardGateway.update).toHaveBeenCalledWith(
        'dash-1',
        'user-1',
        expect.objectContaining({
          title: 'Новый Сводный дашборд',
        }),
        'ws-1',
      )
    })
  })

  it('allows canceling edit mode and discarding changes', () => {
    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view', 'dashboards.manage']}>
        <DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />
      </WorkspaceAccessProvider>,
    )

    // Switch to edit mode
    fireEvent.click(screen.getByRole('button', { name: /Редактировать/i }))
    const titleInput = screen.getByTestId('dashboard-title-input')
    fireEvent.change(titleInput, { target: { value: 'Изменение' } })

    // Click cancel
    const cancelBtn = screen.getByRole('button', { name: /Отмена/i })
    fireEvent.click(cancelBtn)

    // Back in view mode with original title
    expect(screen.queryByTestId('dashboard-title-input')).not.toBeInTheDocument()
    expect(screen.getByText('Сводный дашборд')).toBeInTheDocument()
  })

  it('renders shared filter bar and saved views menu in view mode', async () => {
    vi.mocked(dashboardGateway.listSavedViews).mockResolvedValueOnce([
      {
        id: 'v-1',
        dashboard_id: 'dash-1',
        name: 'Вид по умолчанию',
        filters: { date_range: '30d' },
        is_default: true,
        created_at: '2026-09-23T10:00:00Z',
        updated_at: '2026-09-23T10:00:00Z',
      },
    ])

    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view']}>
        <DashboardViewer dashboard={mockDashboard} userId="user-1" workspaceId="ws-1" />
      </WorkspaceAccessProvider>,
    )

    await waitFor(() => {
      expect(screen.getByTestId('dashboard-filter-bar')).toBeInTheDocument()
      expect(screen.getByTestId('saved-views-menu')).toBeInTheDocument()
    })
  })
})
