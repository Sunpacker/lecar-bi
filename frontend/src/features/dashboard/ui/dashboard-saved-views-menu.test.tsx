import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { DashboardSavedViewsMenu } from './dashboard-saved-views-menu'
import { dashboardGateway, type DashboardSavedView } from '../api/dashboard-gateway'
import { WorkspaceAccessProvider } from '../../workspace/ui/workspace-access-provider'

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    createSavedView: vi.fn(),
    updateSavedView: vi.fn(),
    deleteSavedView: vi.fn(),
  },
}))

const mockViews: DashboardSavedView[] = [
  {
    id: 'view-1',
    dashboard_id: 'dash-1',
    name: 'Основной обзор',
    filters: { date_range: '30d' },
    is_default: true,
    created_at: '2026-09-23T10:00:00Z',
    updated_at: '2026-09-23T10:00:00Z',
  },
  {
    id: 'view-2',
    dashboard_id: 'dash-1',
    name: 'Северный регион',
    filters: { date_range: '90d', region_id: 'reg-2' },
    is_default: false,
    created_at: '2026-09-23T10:00:00Z',
    updated_at: '2026-09-23T10:00:00Z',
  },
]

describe('DashboardSavedViewsMenu', () => {
  const onSelectView = vi.fn()
  const onViewsUpdated = vi.fn()

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders active view name and lists saved views', () => {
    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view']}>
        <DashboardSavedViewsMenu
          dashboardId="dash-1"
          userId="user-1"
          workspaceId="ws-1"
          currentFilters={{ date_range: '30d' }}
          activeView={mockViews[0]}
          savedViews={mockViews}
          onSelectView={onSelectView}
          onViewsUpdated={onViewsUpdated}
        />
      </WorkspaceAccessProvider>,
    )

    expect(screen.getByText('Основной обзор')).toBeInTheDocument()
  })

  it('hides save view button when user lacks dashboards.manage capability (viewer)', () => {
    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view']}>
        <DashboardSavedViewsMenu
          dashboardId="dash-1"
          userId="user-1"
          workspaceId="ws-1"
          currentFilters={{ date_range: '30d' }}
          activeView={mockViews[0]}
          savedViews={mockViews}
          onSelectView={onSelectView}
          onViewsUpdated={onViewsUpdated}
        />
      </WorkspaceAccessProvider>,
    )

    expect(screen.queryByRole('button', { name: /Сохранить представление/i })).toBeNull()
  })

  it('allows saving current filters as a new saved view when user has dashboards.manage capability', async () => {
    vi.mocked(dashboardGateway.createSavedView).mockResolvedValueOnce({
      id: 'view-3',
      dashboard_id: 'dash-1',
      name: 'Новый пресет',
      filters: { date_range: '180d' },
      is_default: false,
      created_at: '2026-09-23T12:00:00Z',
      updated_at: '2026-09-23T12:00:00Z',
    })

    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view', 'dashboards.manage']}>
        <DashboardSavedViewsMenu
          dashboardId="dash-1"
          userId="user-1"
          workspaceId="ws-1"
          currentFilters={{ date_range: '180d' }}
          activeView={null}
          savedViews={mockViews}
          onSelectView={onSelectView}
          onViewsUpdated={onViewsUpdated}
        />
      </WorkspaceAccessProvider>,
    )

    // Open save modal / form
    fireEvent.click(screen.getByRole('button', { name: /Сохранить представление/i }))

    // Fill name input
    const input = screen.getByPlaceholderText('Название представления')
    fireEvent.change(input, { target: { value: 'Новый пресет' } })

    // Submit save
    fireEvent.click(screen.getByRole('button', { name: /Подтвердить сохранение/i }))

    await waitFor(() => {
      expect(dashboardGateway.createSavedView).toHaveBeenCalledWith(
        'dash-1',
        'user-1',
        {
          name: 'Новый пресет',
          filters: { date_range: '180d' },
          is_default: false,
        },
        'ws-1',
      )
    })
  })
})
