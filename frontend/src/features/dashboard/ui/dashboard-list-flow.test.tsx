import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { DashboardListView } from './dashboard-list-view'
import { dashboardGateway, type DashboardSummary } from '../api/dashboard-gateway'
import { WorkspaceAccessProvider } from '../../workspace/ui/workspace-access-provider'

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    create: vi.fn(),
    delete: vi.fn(),
  },
}))

const mockInitialList: DashboardSummary[] = [
  {
    id: 'd-1',
    workspace_id: 'ws-1',
    title: 'Дашборд директора',
    description: 'Сводные данные компании',
    widget_count: 3,
    created_at: '2026-09-22T08:00:00Z',
    updated_at: '2026-09-22T08:00:00Z',
  },
]

describe('DashboardListView Full Flow', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.spyOn(window, 'confirm').mockReturnValue(true)
  })

  it('creates a new dashboard via Sheet form and renders new card', async () => {
    vi.mocked(dashboardGateway.create).mockResolvedValueOnce({
      id: 'd-new',
      workspace_id: 'ws-1',
      title: 'Аналитика оптовых продаж',
      description: 'Показатели B2B сегмента',
      widgets: [],
      created_at: '2026-09-23T07:00:00Z',
      updated_at: '2026-09-23T07:00:00Z',
    })

    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view', 'dashboards.manage']}>
        <DashboardListView
          initialDashboards={mockInitialList}
          userId="user-1"
          workspaceId="ws-1"
        />
      </WorkspaceAccessProvider>,
    )

    expect(screen.getByText('Дашборд директора')).toBeInTheDocument()

    // Click "Создать дашборд" button
    const openSheetBtn = screen.getByRole('button', { name: /Создать дашборд/i })
    fireEvent.click(openSheetBtn)

    expect(screen.getByRole('heading', { name: 'Новый дашборд' })).toBeInTheDocument()

    const titleInput = screen.getByLabelText(/Название/i)
    const descInput = screen.getByLabelText(/Описание/i)

    fireEvent.change(titleInput, { target: { value: 'Аналитика оптовых продаж' } })
    fireEvent.change(descInput, { target: { value: 'Показатели B2B сегмента' } })

    const submitBtn = screen.getByRole('button', { name: /Сохранить/i })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(dashboardGateway.create).toHaveBeenCalledWith(
        'user-1',
        {
          title: 'Аналитика оптовых продаж',
          description: 'Показатели B2B сегмента',
        },
        'ws-1',
      )
      expect(screen.getByText('Аналитика оптовых продаж')).toBeInTheDocument()
      expect(screen.getByText('Показатели B2B сегмента')).toBeInTheDocument()
    })
  })

  it('deletes dashboard after user confirms prompt and removes card', async () => {
    vi.mocked(dashboardGateway.delete).mockResolvedValueOnce(undefined)

    render(
      <WorkspaceAccessProvider capabilities={['dashboards.view', 'dashboards.manage']}>
        <DashboardListView
          initialDashboards={mockInitialList}
          userId="user-1"
          workspaceId="ws-1"
        />
      </WorkspaceAccessProvider>,
    )

    expect(screen.getByText('Дашборд директора')).toBeInTheDocument()

    const deleteBtn = screen.getByRole('button', { name: 'Удалить дашборд' })
    fireEvent.click(deleteBtn)

    expect(window.confirm).toHaveBeenCalledWith(
      'Вы уверены, что хотите удалить этот дашборд?',
    )

    await waitFor(() => {
      expect(dashboardGateway.delete).toHaveBeenCalledWith('d-1', 'user-1', 'ws-1')
      expect(screen.queryByText('Дашборд директора')).not.toBeInTheDocument()
      expect(screen.getByText('Нет доступных дашбордов')).toBeInTheDocument()
    })
  })
})
