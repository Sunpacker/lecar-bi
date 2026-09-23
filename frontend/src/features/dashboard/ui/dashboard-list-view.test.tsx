import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import React from 'react'
import { DashboardListView } from './dashboard-list-view'
import { dashboardGateway, type DashboardSummary } from '../api/dashboard-gateway'
import { WorkspaceAccessProvider } from '../../workspace/ui/workspace-access-provider'

const mockPush = vi.fn()
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
}))

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    create: vi.fn(),
    delete: vi.fn(),
  },
}))

describe('DashboardListView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  const initialDashboards: DashboardSummary[] = [
    {
      id: 'd-1',
      workspace_id: 'ws-1',
      title: 'Сводный обзор бизнеса',
      description: 'Ключевые показатели',
      widget_count: 6,
      created_at: '2026-09-22T10:00:00Z',
      updated_at: '2026-09-22T10:00:00Z',
    },
  ]

  it('renders list of dashboard cards with title and widget count', () => {
    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view']}>
        <DashboardListView
          initialDashboards={initialDashboards}
          userId="user-1"
          workspaceId="ws-1"
        />
      </WorkspaceAccessProvider>,
    )

    expect(screen.getByText('Сводный обзор бизнеса')).toBeDefined()
    expect(screen.getByText('Ключевые показатели')).toBeDefined()
    expect(screen.getByText(/6 виджетов/)).toBeDefined()
  })

  it('hides create and delete controls when user lacks dashboards.manage capability (viewer)', () => {
    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view']}>
        <DashboardListView
          initialDashboards={initialDashboards}
          userId="user-1"
          workspaceId="ws-1"
        />
      </WorkspaceAccessProvider>,
    )

    expect(screen.queryByText('Создать дашборд')).toBeNull()
    expect(screen.queryByLabelText('Удалить дашборд')).toBeNull()
  })

  it('creates new dashboard via modal sheet when user has dashboards.manage capability', async () => {
    vi.mocked(dashboardGateway.create).mockResolvedValueOnce({
      id: 'd-new',
      workspace_id: 'ws-1',
      title: 'Финансовый дашборд',
      description: 'Фин показатели',
      widgets: [],
      created_at: '2026-09-22T12:00:00Z',
      updated_at: '2026-09-22T12:00:00Z',
    })

    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view', 'dashboards.manage']}>
        <DashboardListView
          initialDashboards={initialDashboards}
          userId="user-1"
          workspaceId="ws-1"
        />
      </WorkspaceAccessProvider>,
    )

    expect(screen.getByLabelText('Удалить дашборд')).toBeDefined()

    fireEvent.click(screen.getByText('Создать дашборд'))

    const titleInput = screen.getByLabelText('Название')
    fireEvent.change(titleInput, { target: { value: 'Финансовый дашборд' } })

    const submitBtn = screen.getByRole('button', { name: 'Сохранить' })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(dashboardGateway.create).toHaveBeenCalledWith(
        'user-1',
        { title: 'Финансовый дашборд', description: null },
        'ws-1',
      )
    })
  })
})
