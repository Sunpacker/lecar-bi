import { describe, it, expect, vi, beforeEach } from 'vitest'
import { dashboardGateway } from './dashboard-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    GET: vi.fn(),
    POST: vi.fn(),
    PUT: vi.fn(),
    DELETE: vi.fn(),
  },
}))

describe('dashboardGateway', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches list of dashboards with workspace header', async () => {
    const mockList = {
      items: [
        {
          id: 'dash-1',
          workspace_id: 'ws-1',
          title: 'Сводный дашборд',
          description: 'Описание дашборда',
          widget_count: 4,
          created_at: '2026-09-22T10:00:00Z',
          updated_at: '2026-09-22T10:00:00Z',
        },
      ],
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockList,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.list('user-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith('/dashboards', {
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockList.items)
  })

  it('fetches single dashboard by ID', async () => {
    const mockDetail = {
      dashboard: {
        id: 'dash-1',
        workspace_id: 'ws-1',
        title: 'Сводный дашборд',
        description: null,
        widgets: [],
        created_at: '2026-09-22T10:00:00Z',
        updated_at: '2026-09-22T10:00:00Z',
      },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockDetail,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.getById('dash-1', 'user-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith('/dashboards/{id}', {
      params: {
        path: { id: 'dash-1' },
      },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockDetail.dashboard)
  })

  it('creates new dashboard', async () => {
    const mockCreated = {
      dashboard: {
        id: 'dash-new',
        workspace_id: 'ws-1',
        title: 'Новый дашборд',
        description: 'Новое описание',
        widgets: [],
        created_at: '2026-09-22T12:00:00Z',
        updated_at: '2026-09-22T12:00:00Z',
      },
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: mockCreated,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.create(
      'user-1',
      { title: 'Новый дашборд', description: 'Новое описание' },
      'ws-1',
    )

    expect(analyticsClient.POST).toHaveBeenCalledWith('/dashboards', {
      body: { title: 'Новый дашборд', description: 'Новое описание' },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockCreated.dashboard)
  })

  it('updates dashboard', async () => {
    const mockUpdated = {
      dashboard: {
        id: 'dash-1',
        workspace_id: 'ws-1',
        title: 'Обновлённый дашборд',
        description: 'Новое описание',
        widgets: [],
        created_at: '2026-09-22T10:00:00Z',
        updated_at: '2026-09-22T13:00:00Z',
      },
    }

    vi.mocked(analyticsClient.PUT).mockResolvedValueOnce({
      data: mockUpdated,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await dashboardGateway.update(
      'dash-1',
      'user-1',
      { title: 'Обновлённый дашборд', description: 'Новое описание', widgets: [] },
      'ws-1',
    )

    expect(analyticsClient.PUT).toHaveBeenCalledWith('/dashboards/{id}', {
      params: { path: { id: 'dash-1' } },
      body: { title: 'Обновлённый дашборд', description: 'Новое описание', widgets: [] },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockUpdated.dashboard)
  })

  it('deletes dashboard', async () => {
    vi.mocked(analyticsClient.DELETE).mockResolvedValueOnce({
      data: undefined,
      error: undefined,
      response: new Response(null, { status: 204 }),
    } as never)

    await dashboardGateway.delete('dash-1', 'user-1', 'ws-1')

    expect(analyticsClient.DELETE).toHaveBeenCalledWith('/dashboards/{id}', {
      params: { path: { id: 'dash-1' } },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
  })

  it('throws descriptive error on failure', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: undefined,
      error: { message: 'Dashboard not found', code: 'NOT_FOUND' },
      response: new Response(null, { status: 404 }),
    } as never)

    await expect(dashboardGateway.getById('non-existent', 'user-1', 'ws-1')).rejects.toThrow(
      'Dashboard not found',
    )
  })
})
